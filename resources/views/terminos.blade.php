<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Términos y Condiciones — tukipu</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root {
    --primary: #0E2F5A;
    --accent:  #1A4E96;
    --text:    #1a2540;
    --muted:   #5a6a82;
    --border:  #e2e8f0;
    --bg:      #F7F9FC;
    --card:    #ffffff;
  }
  *, *::before, *::after { box-sizing: border-box; }
  body { margin: 0; font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); line-height: 1.75; }

  .tc-header {
    background: linear-gradient(135deg, #0E2F5A 0%, #1A4E96 100%);
    color: #fff; padding: 48px 24px 40px; text-align: center;
  }
  .tc-header .brand { font-size: 22px; font-weight: 900; letter-spacing: -.3px; margin-bottom: 20px; opacity: .85; }
  .tc-logo-wrap { display: inline-block; background: #fff; border-radius: 14px; padding: 10px 18px; margin-bottom: 20px; }
  .tc-logo { height: 48px; max-width: 180px; object-fit: contain; display: block; }
  .tc-header h1 { margin: 0 0 10px; font-size: clamp(24px, 4vw, 36px); font-weight: 800; letter-spacing: -.5px; }
  .tc-header .meta { font-size: 13px; opacity: .65; }

  .tc-body { max-width: 720px; margin: 0 auto; padding: 48px 24px 80px; }

  .tc-toc {
    background: var(--card); border: 1px solid var(--border); border-radius: 14px;
    padding: 22px 26px; margin-bottom: 40px;
  }
  .tc-toc-title { font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.2px; color: var(--muted); margin-bottom: 12px; }
  .tc-toc ol { margin: 0; padding-left: 18px; display: grid; gap: 5px; }
  .tc-toc ol li a { color: var(--accent); text-decoration: none; font-size: 13.5px; font-weight: 500; }
  .tc-toc ol li a:hover { text-decoration: underline; }

  .tc-section { margin-bottom: 40px; scroll-margin-top: 24px; }
  .tc-section h2 {
    font-size: 18px; font-weight: 800; color: var(--primary);
    border-bottom: 2px solid var(--border); padding-bottom: 10px; margin: 0 0 18px;
    display: flex; align-items: center; gap: 10px;
  }
  .tc-section h2 .num {
    width: 28px; height: 28px; border-radius: 7px; background: var(--accent); color: #fff;
    display: grid; place-items: center; font-size: 12px; font-weight: 800; flex-shrink: 0;
  }
  .tc-section p, .tc-section li { font-size: 14px; color: #384054; }
  .tc-section ul { padding-left: 20px; margin: 10px 0; display: grid; gap: 7px; }

  .tc-box {
    border-radius: 10px; padding: 14px 18px; margin: 14px 0; font-size: 13.5px; line-height: 1.65;
  }
  .tc-box.blue  { background: #EBF2FF; border-left: 4px solid #1A4E96; color: #1a3860; }
  .tc-box.green { background: #EDFAF4; border-left: 4px solid #17A673; color: #0e5c3f; }
  .tc-box.amber { background: #FFF8EE; border-left: 4px solid #FFB35C; color: #6b4a10; }

  .tc-footer {
    background: var(--primary); color: rgba(255,255,255,.55);
    text-align: center; padding: 22px; font-size: 12px;
  }
  .tc-footer a { color: rgba(255,255,255,.75); text-decoration: none; }

  @media (max-width: 600px) {
    .tc-body { padding: 28px 16px 60px; }
  }
</style>
</head>
<body>

<header class="tc-header">
  @if(!empty($config['logo']))
    <div class="tc-logo-wrap">
      <img src="{{ asset('storage/' . $config['logo']) }}" alt="{{ $config['nombre'] }}" class="tc-logo">
    </div>
  @else
    <div class="brand">{{ $config['nombre'] }}</div>
  @endif
  <h1>Términos y Condiciones</h1>
  <div class="meta">Última actualización: {{ date('d/m/Y') }}</div>
</header>

<div class="tc-body">

  <nav class="tc-toc">
    <div class="tc-toc-title">Contenido</div>
    <ol>
      <li><a href="#s1">Uso del servicio</a></li>
      <li><a href="#s2">Tu cuenta</a></li>
      <li><a href="#s3">Planes y pagos</a></li>
      <li><a href="#s4">Cancelación y tus datos</a></li>
      <li><a href="#s5">Lo que está prohibido</a></li>
      <li><a href="#s6">Tus datos y privacidad</a></li>
      <li><a href="#s7">Comunicaciones</a></li>
      <li><a href="#s8">Limitación de responsabilidad</a></li>
      <li><a href="#s9">Cambios en los términos</a></li>
      <li><a href="#s10">Contacto</a></li>
    </ol>
  </nav>

  <section class="tc-section" id="s1">
    <h2><span class="num">1</span> Uso del servicio</h2>
    <p><strong>tukipu</strong> es una plataforma de gestión para negocios (ventas, inventario, caja, pedidos y más). Al crear una cuenta aceptas usarla de forma responsable y conforme a lo que se describe en estos términos.</p>
    <p>El servicio está disponible para cualquier persona mayor de 18 años o que cuente con autorización de un adulto responsable.</p>
  </section>

  <section class="tc-section" id="s2">
    <h2><span class="num">2</span> Tu cuenta</h2>
    <p>Al registrarte eres responsable de:</p>
    <ul>
      <li>Mantener tu contraseña segura y no compartirla.</li>
      <li>Todo lo que ocurra dentro de tu cuenta.</li>
      <li>Notificarnos si crees que alguien accedió a tu cuenta sin tu permiso.</li>
    </ul>
    <p>Puedes crear usuarios adicionales dentro de tu empresa para que tu equipo use la plataforma.</p>
  </section>

  <section class="tc-section" id="s3">
    <h2><span class="num">3</span> Planes y pagos</h2>
    <p>tukipu ofrece distintos planes de suscripción. Los precios y las funciones incluidas están detallados en la página de planes al momento de registrarte.</p>
    <ul>
      <li><strong>Prueba gratuita:</strong> algunos planes incluyen días gratis al inicio, sin necesidad de ingresar un método de pago.</li>
      <li><strong>Pagos:</strong> una vez terminada la prueba, deberás realizar el pago según el plan elegido para seguir usando el servicio.</li>
      <li><strong>Precios:</strong> están expresados en soles peruanos (S/) e incluyen IGV cuando corresponda.</li>
      <li><strong>Cambio de plan:</strong> puedes cambiar tu plan en cualquier momento desde tu cuenta.</li>
    </ul>
    <div class="tc-box amber">Si un pago no se realiza dentro del plazo, el acceso al sistema puede suspenderse. Te avisaremos antes de que eso ocurra.</div>
  </section>

  <section class="tc-section" id="s4">
    <h2><span class="num">4</span> Cancelación y tus datos</h2>
    <p>Puedes cancelar tu cuenta cuando quieras desde el panel de configuración o escribiéndonos.</p>
    <div class="tc-box green">
      <strong>Tus datos se conservan por 3 meses después de cancelar o de que venza tu suscripción.</strong> Durante ese tiempo puedes reactivar tu cuenta y recuperar todo — productos, ventas, clientes, configuración — sin perder nada. Pasados los 3 meses, los datos se eliminan de forma permanente. Te enviaremos un aviso por correo antes de que eso suceda.
    </div>
  </section>

  <section class="tc-section" id="s5">
    <h2><span class="num">5</span> Lo que está prohibido</h2>
    <p>Al usar tukipu te comprometes a no:</p>
    <ul>
      <li>Usar el servicio para actividades ilegales.</li>
      <li>Intentar hackear, alterar o dañar la plataforma.</li>
      <li>Registrar datos falsos o de terceros sin su consentimiento.</li>
      <li>Revender o ceder el acceso a otras personas fuera de tu empresa.</li>
      <li>Copiar o extraer el código o diseño de la plataforma.</li>
    </ul>
    <div class="tc-box amber">El incumplimiento de estas reglas puede resultar en la suspensión inmediata de la cuenta.</div>
  </section>

  <section class="tc-section" id="s6">
    <h2><span class="num">6</span> Tus datos y privacidad</h2>
    <p>Para brindarte el servicio necesitamos algunos datos tuyos (nombre, correo, datos de tu empresa, etc.). Aquí te decimos cómo los usamos:</p>
    <ul>
      <li>Los usamos únicamente para hacer funcionar tukipu y brindarte soporte.</li>
      <li><strong>No vendemos tus datos a nadie.</strong></li>
      <li>Los datos de tu empresa (productos, ventas, clientes) son tuyos. tukipu no los usa para ningún otro fin.</li>
      <li>Podemos usar proveedores de infraestructura externos (servidores en la nube) para almacenar y procesar la información, siempre bajo condiciones de confidencialidad.</li>
    </ul>
    <p>Puedes solicitarnos en cualquier momento ver, corregir o eliminar tus datos personales escribiéndonos a <strong>soporte@tukipu.pe</strong>.</p>
  </section>

  <section class="tc-section" id="s7">
    <h2><span class="num">7</span> Comunicaciones</h2>
    <p>Al registrarte aceptas que te enviemos correos relacionados con tu cuenta (avisos de pago, alertas de seguridad, novedades del sistema). También podemos enviarte correos con novedades o promociones de tukipu.</p>
    <div class="tc-box blue">Puedes darte de baja de los correos promocionales en cualquier momento haciendo clic en "Cancelar suscripción" en el pie de cualquier correo, o escribiéndonos. Los correos de cuenta (pagos, seguridad) no se pueden desactivar porque son necesarios para el servicio.</div>
  </section>

  <section class="tc-section" id="s8">
    <h2><span class="num">8</span> Limitación de responsabilidad</h2>
    <p>Hacemos todo lo posible para que tukipu funcione bien, pero no podemos garantizar disponibilidad del 100% del tiempo. Pueden ocurrir interrupciones por mantenimiento o causas fuera de nuestro control.</p>
    <p>tukipu no se hace responsable de pérdidas de negocio o decisiones tomadas en base a la información del sistema. Recomendamos mantener respaldos propios de tu información importante.</p>
  </section>

  <section class="tc-section" id="s9">
    <h2><span class="num">9</span> Cambios en los términos</h2>
    <p>Podemos actualizar estos términos cuando sea necesario. Si los cambios son importantes, te avisaremos por correo con al menos 15 días de anticipación. Seguir usando tukipu después de esa fecha implica que aceptas los nuevos términos.</p>
  </section>

  <section class="tc-section" id="s10">
    <h2><span class="num">10</span> Contacto</h2>
    <p>¿Tienes dudas sobre estos términos o sobre cómo usamos tus datos? Contáctanos:</p>
    <ul>
      @if(!empty($config['telefono']))
        <li>📞 <strong>{{ $config['telefono'] }}</strong></li>
      @endif
    </ul>
  </section>

</div>

<footer class="tc-footer">
  <p>© {{ date('Y') }} {{ $config['nombre'] }} · <a href="{{ url('/registrarse') }}">Crear cuenta</a></p>
</footer>

</body>
</html>
