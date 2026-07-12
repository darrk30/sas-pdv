<?php

namespace App\Services;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\DB;
use Minishlink\WebPush\WebPush;

class WebPushService
{
    private WebPush $push;

    public function __construct()
    {
        $this->push = new WebPush([
            'VAPID' => [
                'subject'    => config('services.vapid.subject'),
                'publicKey'  => config('services.vapid.public_key'),
                'privateKey' => config('services.vapid.private_key'),
            ],
        ]);

        $this->push->setDefaultOptions(['TTL' => 3600]);
    }

    public function sendToEmpresaAdmins(int $empresaId, string $title, string $body, string $url = ''): void
    {
        // IDs de usuarios con rol Administrador en esta empresa (misma lógica que AppServiceProvider)
        $adminIds = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', 'App\\Models\\User')
            ->where('model_has_roles.empresa_id', $empresaId)
            ->where('roles.name', 'Administrador')
            ->pluck('model_has_roles.model_id');

        if ($adminIds->isEmpty()) {
            return;
        }

        $subscriptions = PushSubscription::where('empresa_id', $empresaId)
            ->whereIn('user_id', $adminIds)
            ->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $payload = json_encode(['title' => $title, 'body' => $body, 'url' => $url]);

        foreach ($subscriptions as $sub) {
            $this->push->queueNotification($sub->toWebPushSubscription(), $payload);
        }

        $staleEndpoints = [];

        foreach ($this->push->flush() as $report) {
            if ($report->isSubscriptionExpired()) {
                $staleEndpoints[] = $report->getRequest()->getUri()->__toString();
            }
        }

        if ($staleEndpoints) {
            PushSubscription::where('empresa_id', $empresaId)
                ->whereIn('endpoint', $staleEndpoints)
                ->delete();
        }
    }
}
