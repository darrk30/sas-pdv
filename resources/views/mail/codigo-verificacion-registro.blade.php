<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Código de verificación</title>
<style>
  body { margin:0; padding:0; background:#F4F6FA; font-family:'Segoe UI',Arial,sans-serif; }
  .wrap { max-width:520px; margin:40px auto; background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 4px 24px rgba(0,0,0,.08); }
  .header { background:linear-gradient(135deg,#0E2F5A,#1A4E96); padding:36px 40px 28px; text-align:center; }
  .header .logo { font-size:26px; font-weight:900; color:#fff; letter-spacing:-.5px; }
  .header .logo span { color:#FFB35C; }
  .body { padding:36px 40px; }
  .greeting { font-size:16px; color:#1a2540; font-weight:600; margin-bottom:12px; }
  .msg { font-size:14px; color:#4a5568; line-height:1.7; margin-bottom:28px; }
  .code-box { background:#EBF2FF; border:2px dashed #A8C5E8; border-radius:14px; padding:22px; text-align:center; margin-bottom:28px; }
  .code-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1.5px; color:#5b7da6; margin-bottom:8px; }
  .code { font-size:42px; font-weight:900; letter-spacing:10px; color:#0E2F5A; font-family:'Courier New',monospace; }
  .expire { font-size:12px; color:#7a8fa6; margin-top:8px; }
  .warning { background:#FFF8EE; border-left:3px solid #FFB35C; border-radius:6px; padding:12px 16px; font-size:12.5px; color:#7a5a20; margin-bottom:24px; line-height:1.6; }
  .footer { background:#F4F6FA; padding:20px 40px; text-align:center; font-size:11.5px; color:#9aabbf; border-top:1px solid #E8EEF5; }
</style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <div class="logo">tuki<span>pu</span></div>
  </div>
  <div class="body">
    <div class="greeting">Hola, {{ $nombre }} 👋</div>
    <p class="msg">
      Para completar tu registro en <strong>{{ config('app.name') }}</strong>, ingresa el siguiente código de verificación en la pantalla de registro. Este código confirma que el correo electrónico te pertenece.
    </p>
    <div class="code-box">
      <div class="code-label">Tu código de verificación</div>
      <div class="code">{{ $codigo }}</div>
      <div class="expire">Válido por <strong>10 minutos</strong></div>
    </div>
    <div class="warning">
      <strong>¿No solicitaste este código?</strong> Ignora este correo. Nadie puede usar este código sin acceso a tu bandeja de entrada.
    </div>
    <p class="msg" style="margin-bottom:0">Si tienes problemas, escríbenos a <a href="mailto:soporte@{{ parse_url(config('app.url'), PHP_URL_HOST) }}" style="color:#1A4E96">soporte@{{ parse_url(config('app.url'), PHP_URL_HOST) }}</a>.</p>
  </div>
  <div class="footer">
    © {{ date('Y') }} {{ config('app.name') }} · Este es un correo automático, no respondas a este mensaje.
  </div>
</div>
</body>
</html>
