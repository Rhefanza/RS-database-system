@extends('layouts.app')

@section('title', 'Temukan layanan puskesmas')

@section('content')
<section class="care-hero" aria-labelledby="hero-title">
    <img src="{{ asset('assets/rujuk-care-hero.png') }}" class="care-hero-photo" alt="Ilustrasi tenaga kesehatan berbincang dengan ibu dan anak" width="1536" height="1024" fetchpriority="high">
    <div class="care-hero-shade" aria-hidden="true"></div>
    <div class="care-hero-copy">
        <p class="eyebrow">✦ &nbsp; Layanan kesehatan, lebih dekat</p>
        <h1 id="hero-title">Langkah kecil<br>menuju <em>layanan<br>yang tepat.</em></h1>
        <p>Temukan puskesmas, lihat layanan dan jadwalnya, lalu siapkan kunjungan dengan lebih tenang.</p>
        <div class="hero-actions"><a href="#cari-puskesmas" class="button primary">Cari puskesmas <span aria-hidden="true">↗</span></a><a class="text-link" href="#cara-kerja">Lihat cara kerja ↓</a></div>
    </div>
</section>

<section class="finder-section" id="cari-puskesmas" aria-labelledby="finder-title">
    <form class="care-search" method="get" action="{{ route('home') }}#hasil-puskesmas">
        <div class="finder-heading"><div><p class="eyebrow">Pencarian yang lebih mudah</p><h2 id="finder-title">Mulai dari kebutuhan Anda.</h2></div><span>Nama puskesmas, alamat, atau layanan</span></div>
        <div class="finder-fields"><label for="finder-query" class="sr-only">Nama puskesmas, alamat, atau layanan</label><input id="finder-query" type="search" name="q" maxlength="100" value="{{ $search ?? '' }}" placeholder="Contoh: Puskesmas Sukamaju atau Poli Umum"><button class="button primary" type="submit">Cari sekarang <span aria-hidden="true">→</span></button></div>
        <div class="finder-bottom"><button type="button" class="location-button" data-use-location><span aria-hidden="true">◎</span> <strong>Urutkan dari lokasi saya</strong></button><p data-location-status role="status" aria-live="polite">Lokasi hanya digunakan untuk menghitung jarak garis lurus di perangkat Anda.</p></div>
    </form>
</section>

<section class="care-promises" aria-label="Manfaat menggunakan direktori"><div><span aria-hidden="true">⌕</span><p><strong>Cari lebih mudah</strong><small>Telusuri puskesmas atau layanan.</small></p></div><div><span aria-hidden="true">✦</span><p><strong>Kenali jadwal</strong><small>Lihat hari, jam, dan sisa kapasitas.</small></p></div><div><span aria-hidden="true">≋</span><p><strong>Siapkan antrean</strong><small>Masuk dan ambil nomor pada jadwal tersedia.</small></p></div></section>

<section class="results-section" id="hasil-puskesmas" aria-labelledby="results-title">
    <div class="section-heading"><div><p class="eyebrow">Puskesmas aktif</p><h2 id="results-title">Pilihan untuk <em>Anda.</em></h2></div><div class="results-summary"><strong>{{ $items->count() }}</strong> puskesmas ditemukan @if (($search ?? '') !== '')<a href="{{ route('home') }}#cari-puskesmas">Hapus pencarian ↗</a>@endif</div></div>
    @forelse ($items as $item)
        @if ($loop->first)<div class="hospital-list" data-hospital-list>@endif
        <article class="hospital-row" data-hospital data-puskesmas-id="{{ $item->puskesmas_id }}" data-lat="{{ $item->latitude }}" data-lng="{{ $item->longitude }}">
            <div class="hospital-card-top"><span class="result-number">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><span class="status-dot"><i></i> Puskesmas aktif</span></div>
            <div class="hospital-main"><h3><a href="{{ route('puskesmas.show', $item) }}">{{ $item->nama_puskesmas }}</a></h3><p>{{ $item->alamat }}</p><div class="facility-line">@forelse ($item->services->take(4) as $service)<span>{{ $service->nama_layanan }}</span>@empty<span>Informasi layanan belum tersedia</span>@endforelse @if ($item->services->count() > 4)<span>+{{ $item->services->count() - 4 }} layanan</span>@endif</div></div>
            <div class="hospital-meta"><div><small>Layanan tercatat</small><strong>{{ $item->services->count() }}</strong></div><div><small>Antrean aktif</small><strong data-live-active>—</strong></div><div class="distance" data-distance hidden><small>Jarak garis lurus</small><strong>—</strong></div></div>
            <a class="hospital-card-link" href="{{ route('puskesmas.show', $item) }}">Lihat jadwal layanan <span aria-hidden="true">↗</span></a>
        </article>
        @if ($loop->last)</div>@endif
    @empty
        <div class="empty-state"><span aria-hidden="true">⌕</span><div><h3>Belum ada hasil yang cocok.</h3><p>Coba nama puskesmas, alamat, atau layanan lain.</p><a class="button primary" href="{{ route('home') }}#cari-puskesmas">Lihat semua puskesmas</a></div></div>
    @endforelse
</section>

<p class="live-update-status" data-live-status role="status" aria-live="polite">Menghubungkan data antrean live…</p>

<section class="how-section" id="cara-kerja"><div><p class="eyebrow">Langkah Anda</p><h2>Cari, lihat, lalu <em>kunjungi.</em></h2><p>Informasi layanan dan antrean kini bisa dilihat dalam satu alur.</p></div><ol><li><b>01</b><div><h3>Cari puskesmas</h3><p>Masukkan nama, alamat, atau layanan yang Anda butuhkan.</p></div></li><li><b>02</b><div><h3>Pilih jadwal layanan</h3><p>Periksa hari, jam buka, dan sisa kapasitas pada halaman puskesmas.</p></div></li><li><b>03</b><div><h3>Ambil antrean</h3><p>Masuk dengan akun masyarakat yang sudah diaktivasi, lalu pilih tanggal yang tersedia.</p></div></li></ol></section>

<section class="care-cta"><div><p class="eyebrow">Siap untuk memulai?</p><h2>Temukan layanan yang <em>sesuai.</em></h2></div><a class="button primary" href="#cari-puskesmas">Cari puskesmas ↗</a></section>
@endsection
