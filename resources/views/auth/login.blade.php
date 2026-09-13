@extends('layouts.app')

@section('title', 'Masuk pengelola')

@section('content')
<section class="auth-section">
    <div class="auth-card">
        <p class="eyebrow">Akses internal</p>
        <h1>Masuk sebagai pengelola</h1>
        <p>Kelola data rumah sakit yang tampil pada direktori publik.</p>
        <form method="post" action="{{ route('login.store') }}" class="stack-form">
            @csrf
            <label>
                <span>Email</span>
                <input type="email" name="email" autocomplete="username" value="{{ old('email', 'admin@rujuk.test') }}" required autofocus>
                @error('email')<small class="field-error">{{ $message }}</small>@enderror
            </label>
            <label>
                <span>Kata sandi</span>
                <input type="password" name="password" autocomplete="current-password" required>
                @error('password')<small class="field-error">{{ $message }}</small>@enderror
            </label>
            <label class="remember-field"><input type="checkbox" name="remember" value="1"><span>Ingat saya</span></label>
            <button class="button button-primary button-block" type="submit">Masuk ke panel</button>
        </form>
        <div class="demo-credential"><span>Akun demo</span><code>admin@rujuk.test / password</code></div>
    </div>
</section>
@endsection
