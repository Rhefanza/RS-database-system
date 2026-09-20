@extends('layouts.app')
@section('title', 'Aktivasi akun')
@section('content')
<section class="auth-wrap">
    <div class="auth-card wide">
        <p class="eyebrow">Khusus masyarakat</p>
        <h1>Aktivasi akun dengan NIK</h1>
        <p>NIK harus sudah tersedia pada data Dinas Kesehatan dan belum pernah diaktivasi.</p>
        <form method="post" action="{{ route('activation.store') }}" class="form-grid">@csrf
            <label>NIK<input name="nik" inputmode="numeric" maxlength="16" value="{{ old('nik') }}" required></label>
            <label>Email<input type="email" name="email" value="{{ old('email') }}" required></label>
            <label>Kata sandi<input type="password" name="password" minlength="8" required></label>
            <label>Ulangi kata sandi<input type="password" name="password_confirmation" minlength="8" required></label>
            <button class="button primary" type="submit">Aktifkan akun</button>
        </form>
        <div class="demo-box"><strong>NIK yang belum aktif</strong><code>3578010101900004</code></div>
    </div>
</section>
@endsection
