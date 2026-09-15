<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Cari layanan puskesmas, lihat jadwal, dan siapkan antrean.">
    <title>@yield('title', 'PuskesmasKu') · PuskesmasKu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/app.css').'?v=puskesmas-mint-1' }}">
    <script src="{{ asset('assets/app.js').'?v=puskesmas-mint-1' }}" defer></script>
</head>
<body>
    <a class="skip-link" href="#main-content">Lewati ke konten</a>
    <div class="utility-bar"><span>Lebih mudah mencari. Lebih siap berkunjung.</span><span>Proyek demonstrasi · Data contoh</span></div>
    <header class="site-header">
        <a class="brand" href="{{ route('home') }}"><span aria-hidden="true">✦</span><strong>PuskesmasKu</strong><small>Lebih dekat. Lebih peduli.</small></a>
        <button class="menu-toggle" type="button" aria-controls="main-navigation" aria-expanded="false" data-menu-toggle hidden>Menu <span aria-hidden="true">☰</span></button>
        <nav id="main-navigation" aria-label="Navigasi utama">
            <a href="{{ route('home') }}">Beranda</a>
            <a href="{{ route('home') }}#hasil-puskesmas">Puskesmas</a>
            <a href="{{ route('home') }}#cara-kerja">Cara kerja</a>
            @auth
                @if (auth()->user()->role === 'MASYARAKAT')<a href="{{ route('my-queues.index') }}">Antrean saya</a>@endif
                @if (in_array(auth()->user()->role, ['ADMIN', 'PETUGAS'], true))
                    @if (auth()->user()->role === 'ADMIN')<a href="{{ route('admin.master.index') }}">Data master</a>@endif
                    <a href="{{ route('officer.schedules.index') }}">Jadwal</a>
                    <a href="{{ route('officer.queues.index') }}">Antrean</a>
                @endif
                <a href="{{ route('profile.edit') }}">Profil</a>
                <form method="post" action="{{ route('logout') }}">@csrf<button class="link-button" type="submit">Keluar</button></form>
            @else
                <a href="{{ route('activation.create') }}">Aktivasi NIK</a>
                <a class="nav-primary" href="{{ route('login') }}">Masuk</a>
            @endauth
        </nav>
    </header>
    <main class="page-shell" id="main-content" tabindex="-1">
        @if (session('success'))<div class="alert success" role="status">{{ session('success') }}</div>@endif
        @if (session('error'))<div class="alert error" role="alert">{{ session('error') }}</div>@endif
        @if ($errors->any())<div class="alert error" role="alert"><strong>Periksa kembali isian:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @yield('content')
    </main>
    <footer class="site-footer"><div class="footer-main"><div><a class="brand" href="{{ route('home') }}"><span aria-hidden="true">✦</span><strong>PuskesmasKu</strong></a><p>Temukan layanan puskesmas dan siapkan kunjungan dengan lebih mudah.</p></div><div><strong>Jelajahi</strong><a href="{{ route('home') }}#cari-puskesmas">Cari puskesmas</a><a href="{{ route('home') }}#cara-kerja">Cara kerja</a></div><div><strong>Informasi</strong><p>Proyek UTS basis data dengan data dummy. Pastikan layanan melalui puskesmas sebelum berkunjung.</p></div></div><div class="footer-bottom">PuskesmasKu · {{ now()->year }} · Proyek demonstrasi</div></footer>
</body>
</html>
