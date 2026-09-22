<?php

namespace App\Notifications\Auth;

use Filament\Auth\Notifications\ResetPassword as FilamentResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class RestablecerContrasenaNotification extends FilamentResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $expires = config('auth.passwords.' . config('auth.defaults.passwords') . '.expire', 60);
        $name    = $notifiable->name ?? 'usuario';

        return (new MailMessage)
            ->subject('Restablece tu contraseña — Tukipu')
            ->greeting("¡Hola, {$name}!")
            ->line('Recibimos una solicitud para restablecer la contraseña de tu cuenta.')
            ->action('Restablecer contraseña', $this->url)
            ->line("Este enlace expirará en {$expires} minutos.")
            ->line('Si no solicitaste un cambio de contraseña, puedes ignorar este correo.')
            ->salutation('Saludos,<br>El equipo de Tukipu');
    }
}
