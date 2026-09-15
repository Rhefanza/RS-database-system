<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Direktori rumah sakit untuk membandingkan kelas, fasilitas, dan jarak.">
    <title>@yield('title', 'Rujuk.') · Rujuk.</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
    <script src="{{ asset('assets/app.js') }}" defer></script>
</head>
<body class="{{ request()->routeIs('admin.*') ? 'admin-surface' : '' }}">
    <header class="site-header">
        <div class="utility-bar">
            <div class="shell utility-row">
                <span>Direktori rumah sakit Indonesia</span>
                <span><b>Informasi layanan</b> · Data demonstrasi basis data</span>
            </div>
        </div>
        <div class="shell nav-row">
            <a class="brand" href="{{ route('home') }}" aria-label="Beranda Rujuk">
                <span class="brand-mark" aria-hidden="true">R</span>
                <span><strong>Rujuk.</strong><small>Hospital Directory</small></span>
            </a>
            <nav class="main-nav" aria-label="Navigasi utama">
                <a class="{{ request()->routeIs('home') ? 'is-active' : '' }}" href="{{ route('home') }}">Beranda</a>
                @auth
                    @if (auth()->user()->role === 'ADMIN')
                        <a class="{{ request()->routeIs('admin.index', 'admin.hospitals.*') ? 'is-active' : '' }}" href="{{ route('admin.index') }}">Rumah sakit</a>
                        <a class="{{ request()->routeIs('admin.master.*') ? 'is-active' : '' }}" href="{{ route('admin.master.index') }}">Data master</a>
                    @endif
                    @if (in_array(auth()->user()->role, ['ADMIN', 'OFFICER'], true))
                        <a class="{{ request()->routeIs('admin.queues.*') ? 'is-active' : '' }}" href="{{ route('admin.queues.index') }}">Antrean</a>
                    @endif
                    <form action="{{ route('logout') }}" method="post">
                        @csrf
                        <button class="nav-button" type="submit">Keluar</button>
                    </form>
                @else
                    <a class="nav-button" href="{{ route('login') }}">Masuk pengelola</a>
                @endauth
            </nav>
        </div>
    </header>

    @if (session('success'))
        <div class="shell flash-wrap">
            <div class="flash flash-success" role="status">
                <span>{{ session('success') }}</span>
                <button type="button" data-dismiss aria-label="Tutup pemberitahuan">×</button>
            </div>
        </div>
    @endif

    @if ($errors->any())
        <div class="shell flash-wrap flash-errors">
            <div class="flash flash-error" role="alert">
                <span>{{ $errors->first() }}</span>
                <button type="button" data-dismiss aria-label="Tutup pemberitahuan">×</button>
            </div>
        </div>
    @endif

    <main>@yield('content')</main>

    <footer class="site-footer">
        <div class="shell footer-row">
            <p>Rujuk. <span>Direktori rumah sakit untuk tugas basis data.</span></p>
            <p>Data demonstrasi · {{ now()->year }}</p>
        </div>
    </footer>
</body>
</html>
