<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Antrean Puskesmas')</title>
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
    <script src="{{ asset('assets/app.js') }}" defer></script>
</head>
<body>
    <header class="site-header">
        <a class="brand" href="{{ route('home') }}"><span>+</span> PuskesmasKu</a>
        <nav aria-label="Navigasi utama">
            <a href="{{ route('home') }}">Puskesmas</a>
            @auth
                @if (auth()->user()->role === 'MASYARAKAT')
                    <a href="{{ route('my-queues.index') }}">Antrean saya</a>
                @endif
                @if (in_array(auth()->user()->role, ['ADMIN', 'PETUGAS']))
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

    <main class="page-shell">
        @if (session('success'))<div class="alert success">{{ session('success') }}</div>@endif
        @if (session('error'))<div class="alert error">{{ session('error') }}</div>@endif
        @if ($errors->any())<div class="alert error"><strong>Periksa kembali isian:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @yield('content')
    </main>

    <footer>Proyek UTS Basis Data · Data seluruhnya dummy/synthetic</footer>
</body>
</html>
