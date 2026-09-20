@extends('layouts.app')
@section('title', 'Masuk')
@section('content')
<section class="auth-wrap">
    <div class="auth-card">
        <p class="eyebrow">3 role · 1 halaman masuk</p>
        <h1>Masuk ke sistem</h1>
        <p>Gunakan akun masyarakat, petugas, atau Admin/Dinkes.</p>
        <form method="post" action="{{ route('login.store') }}" class="form-grid one">@csrf
            <label>Email<input type="email" name="email" value="{{ old('email') }}" required autofocus></label>
            <label>Kata sandi<input type="password" name="password" required></label>
            <button class="button primary" type="submit">Masuk</button>
        </form>
        <div class="demo-box"><strong>Akun akses</strong><code>admin@puskesmas.test / password</code><code>petugas@puskesmas.test / password</code><code>masyarakat1@puskesmas.test / password</code></div>
        <p>Belum punya akun masyarakat? <a href="{{ route('activation.create') }}">Aktivasi dengan NIK</a>.</p>
    </div>
</section>
@endsection
