<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Application information and build details for NMS Starcom.">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}?v=2">
    <title>About · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/about.js'])
</head>
<body class="operations-app about-app">
<aside class="sidebar" aria-label="Primary navigation">
    <div class="brand starcom-brand"><img src="{{ asset('images/starcom-logo.png') }}" alt="Starcom"><div><strong>NMS STARCOM</strong><small>NETWORK MONITORING</small></div></div>
    <nav>
        <a class="nav-link nav-anchor" href="{{ route('dashboard') }}"><span>⌂</span> Dashboard</a>
        <div class="nav-group"><button class="nav-link nav-parent" type="button" aria-expanded="false"><span>⌘</span> Topology <i>⌄</i></button><div class="nav-submenu"><a class="nav-link nav-child nav-anchor" href="{{ route('dashboard') }}#topology"><span>⌖</span> Map Monitoring</a></div></div>
        <div class="nav-group"><button class="nav-link nav-parent" type="button" aria-expanded="false"><span>▦</span> Device Management <i>⌄</i></button><div class="nav-submenu"><a class="nav-link nav-child nav-anchor" href="{{ route('dashboard') }}#devices"><span>▤</span> Devices</a></div></div>
        @if(auth()->user()->isAdministrator())
            <a class="nav-link nav-anchor" href="{{ route('projects.index') }}"><span>▣</span> Project Management</a>
            <a class="nav-link nav-anchor" href="{{ route('users.index') }}"><span>♙</span> User Management</a>
        @endif
        <a class="nav-link nav-anchor active" href="{{ route('about') }}"><span>ⓘ</span> About</a>
    </nav>
    <div class="side-status"><i></i><div><small>APPLICATION INFO</small><strong>{{ $build['channel'] }}</strong><span>{{ $build['version'] }}</span></div></div>
</aside>

<main class="about-page">
    <header class="app-header about-header">
        <div class="about-brand"><span class="about-mark">NMS</span><strong>{{ config('app.name') }}</strong></div>
        <div class="header-actions">
            <span class="about-path">SYSTEM / ABOUT</span>
            <button class="theme-toggle" type="button" data-theme-toggle aria-pressed="false"><span data-theme-icon>☀</span><span data-theme-label>Light</span></button>
            <a class="button ghost button-link" href="{{ route('dashboard') }}">← Dashboard</a>
        </div>
    </header>

    <section class="about-intro">
        <div>
            <p class="eyebrow">Application Information</p>
            <h1>About this<br>application.</h1>
            <p class="about-intro-copy">Operational software for device inventory, availability monitoring, SNMP telemetry, project topology, and infrastructure management.</p>
        </div>
        <div class="about-release">
            <p>Current release</p>
            <strong>{{ $build['version'] }}</strong>
            <span>build {{ $build['id'] }}</span>
        </div>
    </section>

    <div class="about-content">
        <section class="about-section">
            <div class="about-section-heading"><h2>Application</h2><span>01</span></div>
            <dl class="about-information">
                <div><dt>Product</dt><dd>{{ config('app.name') }}</dd></div>
                <div><dt>Version</dt><dd class="about-mono">{{ $build['version'] }}</dd></div>
                <div><dt>Build</dt><dd class="about-mono">{{ $build['id'] }}</dd></div>
                <div><dt>Release channel</dt><dd>{{ $build['channel'] }}</dd></div>
                <div><dt>Environment</dt><dd>{{ ucfirst(config('app.env')) }}</dd></div>
                <div><dt>Status</dt><dd><span class="about-status"><i></i> Operational</span></dd></div>
            </dl>
        </section>

        <section class="about-section">
            <div class="about-section-heading"><h2>Development</h2><span>02</span></div>
            <div class="about-developer"><div><strong>OPS Team</strong><span>Software Engineering &amp; Operations</span></div><div><small>Lead Developer</small><span>Aris Setiawan</span></div></div>
        </section>

        <section class="about-section">
            <div class="about-section-heading"><h2>Technology</h2><span>03</span></div>
            <div class="about-stack">
                @foreach(['Laravel '.app()->version(), 'PHP '.PHP_VERSION, 'PostgreSQL', 'Redis', 'Docker Compose', 'Nginx', 'Vite', 'JavaScript', 'Linux'] as $technology)
                    <span>{{ $technology }}</span>
                @endforeach
            </div>
        </section>

        <section class="about-section">
            <div class="about-section-heading"><h2>Description</h2><span>04</span></div>
            <p class="about-description">{{ config('app.name') }} is an internally developed network operations system designed to simplify day-to-day monitoring, infrastructure workflows, and system management. The platform is actively maintained; capabilities, reliability improvements, and internal features evolve between releases.</p>
        </section>

        <section class="about-section">
            <div class="about-section-heading"><h2>Build Information</h2><span>05</span></div>
            <dl class="about-build">
                <div><dt>Build ID</dt><dd>{{ $build['id'] }}</dd></div>
                <div><dt>Release</dt><dd>{{ $build['release'] }}</dd></div>
                <div><dt>Architecture</dt><dd>Web / Responsive</dd></div>
                <div><dt>Distribution</dt><dd>Internal</dd></div>
                <div><dt>License</dt><dd>Proprietary</dd></div>
                <div><dt>Built At</dt><dd>{{ $build['built_at'] }}</dd></div>
            </dl>
        </section>
    </div>

    <footer class="about-footer"><span>{{ config('app.name') }}</span><span>Developed by OPS Team · Aris Setiawan</span></footer>
</main>
</body>
</html>
