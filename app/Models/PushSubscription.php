<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Minishlink\WebPush\Subscription;

class PushSubscription extends Model
{
    protected $fillable = [
        'empresa_id',
        'user_id',
        'endpoint',
        'public_key',
        'auth_token',
    ];

    public function toWebPushSubscription(): Subscription
    {
        return Subscription::create([
            'endpoint'        => $this->endpoint,
            'publicKey'       => $this->public_key,
            'authToken'       => $this->auth_token,
            'contentEncoding' => 'aesgcm',
        ]);
    }
}
