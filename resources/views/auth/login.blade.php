<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="icon" type="image/png" href="{{ asset('favicon.png') }}?v=2"><link rel="shortcut icon" type="image/png" href="{{ asset('favicon.png') }}?v=2"><link rel="apple-touch-icon" href="{{ asset('favicon.png') }}?v=2"><title>Login · NMS Starcom</title>@vite(['resources/css/app.css'])</head>
<body class="login-page">
<main class="login-shell">
    <section class="login-brand"><img class="login-starcom-logo" src="{{ asset('images/starcom-logo.png') }}" alt="Starcom"><p>UNIVERSAL NETWORK OPERATIONS PLATFORM</p><h1>Visibility for every<br><span>network device.</span></h1><p class="login-copy">Monitoring seluruh perangkat berbasis IP melalui ICMP Ping dan SNMP v1/v2c, dilengkapi topologi regional serta remote access dalam satu control plane.</p><div class="login-signal"><i></i><span>NMS STARCOM CORE</span><b>ONLINE</b></div></section>
    <section class="login-card"><div><p class="eyebrow">SECURE ACCESS</p><h2>Sign in to NMS</h2><p class="login-muted">Gunakan akun yang diberikan Administrator.</p></div>
        @if($errors->any())<div class="login-error">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('login.submit') }}">@csrf
            <label>Username<input name="username" value="{{ old('username') }}" required autofocus autocomplete="username" placeholder="Masukkan username"></label>
            <label>Password<input type="password" name="password" required autocomplete="current-password" placeholder="Masukkan password"></label>
            <label class="remember"><input type="checkbox" name="remember" value="1"> Tetap masuk di perangkat ini</label>
            <button class="button primary login-submit">Sign in →</button>
        </form>
        <small class="login-footer">AUTHORIZED PERSONNEL ONLY · ALL ACCESS IS MONITORED</small>
    </section>
</main></body></html>
