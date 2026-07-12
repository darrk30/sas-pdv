<?php

namespace App\Http\Controllers\Pdv;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function subscribe(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint'  => 'required|string|max:500',
            'keys.p256dh' => 'required|string',
            'keys.auth'   => 'required|string',
        ]);

        $empresa = $this->resolverEmpresa();

        PushSubscription::updateOrCreate(
            [
                'user_id'  => auth()->id(),
                'endpoint' => $request->input('endpoint'),
            ],
            [
                'empresa_id' => $empresa->id,
                'public_key' => $request->input('keys.p256dh'),
                'auth_token' => $request->input('keys.auth'),
            ]
        );

        return response()->json(['ok' => true]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $request->validate(['endpoint' => 'required|string|max:500']);

        PushSubscription::where('user_id', auth()->id())
            ->where('endpoint', $request->input('endpoint'))
            ->delete();

        return response()->json(['ok' => true]);
    }

    private function resolverEmpresa(): Empresa
    {
        $slug = explode('.', request()->getHost())[0];
        return Empresa::where('slug', $slug)->firstOrFail();
    }
}
