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
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIINfQ3ynceqWgVqC41Wifii1/Lnt2MZt4=" crossorigin="">
    <link rel="stylesheet" href="{{ asset('assets/app.css').'?v=puskesmas-journey-5' }}">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin="" defer></script>
    <script src="{{ asset('assets/app.js').'?v=puskesmas-journey-5' }}" defer></script>
</head>
<body data-live-queue-endpoint="{{ route('api.live-queues') }}">
    <a class="skip-link" href="#main-content">Lewati ke konten</a>
    <div class="utility-bar"><span>Lebih mudah mencari. Lebih siap berkunjung.</span><span>Informasi layanan kesehatan Surabaya</span></div>
    <header class="site-header">
        <a class="brand" href="{{ auth()->check() && auth()->user()->role === 'ADMIN' ? route('admin.master.index') : route('home') }}"><span aria-hidden="true">✦</span><strong>PuskesmasKu</strong><small>Lebih dekat. Lebih peduli.</small></a>
        <button class="menu-toggle" type="button" aria-controls="main-navigation" aria-expanded="false" data-menu-toggle hidden>Menu <span aria-hidden="true">☰</span></button>
        <nav id="main-navigation" aria-label="Navigasi utama">
            @auth
                @if (auth()->user()->role === 'ADMIN')
                    <a href="{{ route('admin.master.index') }}">Data master</a>
                @else
                    <a href="{{ route('home') }}">Beranda</a>
                    <a href="{{ route('home') }}#peta-surabaya">Puskesmas</a>
                    <a href="{{ route('home') }}#cara-kerja">Cara kerja</a>
                    <a class="nav-recommendation" href="{{ route('recommendations.index') }}">Lihat rekomendasi</a>
                    @if (auth()->user()->role === 'MASYARAKAT')<a href="{{ route('my-queues.index') }}">Antrean saya</a>@endif
                    @if (auth()->user()->role === 'PETUGAS')
                    <a href="{{ route('officer.schedules.index') }}">Jadwal</a>
                    <a href="{{ route('officer.queues.index') }}">Antrean</a>
                    @endif
                @endif
                <a href="{{ route('profile.edit') }}">Profil</a>
                <form method="post" action="{{ route('logout') }}">@csrf<button class="link-button" type="submit">Keluar</button></form>
            @else
                <a href="{{ route('home') }}">Beranda</a>
                <a href="{{ route('home') }}#peta-surabaya">Puskesmas</a>
                <a href="{{ route('home') }}#cara-kerja">Cara kerja</a>
                <a class="nav-recommendation" href="{{ route('recommendations.index') }}">Lihat rekomendasi</a>
                <a href="{{ route('activation.create') }}">Aktivasi NIK</a>
                <a class="nav-primary" href="{{ route('login') }}">Masuk</a>
            @endauth
        </nav>
    </header>
    <div class="flash-toast-stack" aria-live="polite" aria-atomic="false">
        @if (session('success'))<div class="alert success" role="status">{{ session('success') }}</div>@endif
        @if (session('error'))<div class="alert error" role="alert">{{ session('error') }}</div>@endif
        @if ($errors->any())<div class="alert error" role="alert"><strong>Periksa kembali isian:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    </div>
    <main class="page-shell" id="main-content" tabindex="-1">
        @yield('content')
    </main>
    <div class="live-toast-stack" data-live-toast-stack aria-live="polite" aria-atomic="false"></div>
    <footer class="site-footer"><div class="footer-main"><div><a class="brand" href="{{ auth()->check() && auth()->user()->role === 'ADMIN' ? route('admin.master.index') : route('home') }}"><span aria-hidden="true">✦</span><strong>PuskesmasKu</strong></a><p>{{ auth()->check() && auth()->user()->role === 'ADMIN' ? 'Area pengelolaan data master Dinas Kesehatan.' : 'Temukan layanan puskesmas dan siapkan kunjungan dengan lebih mudah.' }}</p></div>@if(!auth()->check() || auth()->user()->role !== 'ADMIN')<div><strong>Jelajahi</strong><a href="{{ route('home') }}#peta-surabaya">Peta puskesmas</a><a href="{{ route('recommendations.index') }}">Lihat rekomendasi</a><a href="{{ route('home') }}#cara-kerja">Cara kerja</a></div><div><strong>Informasi</strong><p>Informasi layanan dan antrean diperbarui secara berkala.</p></div>@endif</div><div class="footer-bottom">PuskesmasKu · {{ now()->year }}</div></footer>
</body>
</html>
