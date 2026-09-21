@extends('layouts.app')
@section('title', 'Profil')
@section('content')
<section class="panel narrow"><p class="eyebrow">Akun {{ strtolower($user->role) }}</p><h1>Profil saya</h1>
    <form method="post" action="{{ route('profile.update') }}" class="form-grid">@csrf @method('put')
        @if ($user->nik)<label>NIK<input value="{{ $user->nik }}" disabled></label>@endif
        <label>Nama lengkap<input name="nama_lengkap" value="{{ old('nama_lengkap', $user->nama_lengkap) }}" required></label>
        @if ($user->role === 'PETUGAS')
            <label>Email petugas<input type="email" value="{{ $user->email }}" disabled><small>Email ditetapkan otomatis sesuai puskesmas oleh administrator.</small></label>
        @else
            <label>Email<input type="email" name="email" value="{{ old('email', $user->email) }}" required></label>
        @endif
        @if ($user->citizen)
            <label>Nomor telepon<input name="nomor_telepon" value="{{ old('nomor_telepon', $user->citizen->nomor_telepon) }}"></label>
            <label class="span-2">Alamat<textarea name="alamat">{{ old('alamat', $user->citizen->alamat) }}</textarea></label>
        @endif
        <label>Password baru (opsional)<input type="password" name="password" minlength="8"></label>
        <label>Ulangi password<input type="password" name="password_confirmation" minlength="8"></label>
        <button class="button primary" type="submit">Simpan profil</button>
    </form>
</section>
@endsection
