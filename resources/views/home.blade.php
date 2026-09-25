@extends('layouts.app')

@section('title', 'Peta antrean puskesmas Surabaya')

@section('content')
<section class="care-hero" aria-labelledby="hero-title">
    <img src="{{ asset('images/antre.png') }}" class="care-hero-photo" alt="Petugas kesehatan melayani masyarakat yang sedang mengantre" width="1448" height="1086" fetchpriority="high">
    <div class="care-hero-shade" aria-hidden="true"></div>
    <div class="care-hero-copy">
        <p class="eyebrow">✦ &nbsp; Layanan kesehatan Surabaya</p>
        <h1 id="hero-title">Temukan faskes<br>dan lihat <em>antreannya.</em></h1>
        <p>Cari puskesmas, lihat titiknya di peta Surabaya, lalu ambil antrean layanan secara daring.</p>
        <div class="hero-actions"><a href="#cari-puskesmas" class="button primary">Cari puskesmas <svg class="icon-chevron" aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg></a><a class="text-link" href="{{ route('recommendations.index') }}">Lihat rekomendasi <svg class="icon-chevron" aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg></a></div>
    </div>
</section>

@php
    $searchSource = $mapItems->map(fn ($item) => [
        'id' => $item['id'], 'name' => $item['name'], 'district' => $item['district'],
        'address' => $item['address'], 'services' => $item['services'], 'url' => $item['url'],
        'latitude' => $item['latitude'], 'longitude' => $item['longitude'], 'active' => $item['active'],
    ])->values();
@endphp

<section class="finder-section" id="cari-puskesmas" aria-labelledby="finder-title">
    <form class="care-search" method="get" action="{{ route('home') }}#peta-surabaya" data-clinic-search data-search-source="{{ base64_encode($searchSource->toJson()) }}">
        <div class="finder-heading"><div><p class="eyebrow">Pencarian yang lebih mudah</p><h2 id="finder-title">Cari dari nama, wilayah, atau poli.</h2></div><span>Hasil muncul saat Anda mengetik</span></div>
<div class="finder-fields search-combobox"><div class="search-input-wrap"><label for="finder-query" class="sr-only">Nama puskesmas, kecamatan, alamat, atau layanan</label><input id="finder-query" type="search" name="q" maxlength="100" value="{{ $search ?? '' }}" placeholder="Contoh: Mulyorejo, Poli Gigi, atau Kecamatan Gubeng" autocomplete="off" aria-autocomplete="list" aria-controls="clinic-search-results" aria-expanded="false"><div class="search-suggestions" id="clinic-search-results" role="listbox" hidden></div></div><button class="button primary" type="submit">Tampilkan di peta <svg class="icon-chevron" aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg></button></div>
        <div class="finder-bottom"><p>Ketik nama faskes, lalu pilih hasil untuk menyorot lokasinya pada peta.</p></div>
    </form>
</section>

<section class="care-promises" aria-label="Manfaat menggunakan layanan"><div><span aria-hidden="true">⌕</span><p><strong>Cari lebih mudah</strong><small>Nama berawalan sama langsung ditampilkan.</small></p></div><div><span aria-hidden="true">⌖</span><p><strong>Lihat di peta</strong><small>31 titik faskes pada peta Surabaya.</small></p></div><div><span aria-hidden="true">≋</span><p><strong>Ambil antrean</strong><small>Pilih layanan dan nomor antrean yang tersedia.</small></p></div></section>

<section class="home-map-section" id="peta-surabaya" aria-labelledby="map-title">
    <div class="section-heading map-section-heading"><div><p class="eyebrow">Peta layanan hari ini</p><h2 id="map-title">Semua faskes dalam <em>satu peta.</em></h2><p>Klik titik untuk melihat nama faskes, alamat, dan jumlah antrean aktif.</p></div><div class="map-summary" aria-label="Ringkasan peta"><div><strong>{{ $mapItems->count() }}</strong><span>Faskes</span></div><div><strong data-live-summary-total>{{ $totalQueues }}</strong><span>Total antrean</span></div><div><strong data-live-summary-active>{{ $activeQueues }}</strong><span>Antrean aktif</span></div></div></div>

    @if ($mapItems->isEmpty())
        <div class="empty">Belum ada puskesmas aktif yang memiliki koordinat peta.</div>
    @else
        <div class="leaflet-map-shell">
            <div id="surabaya-map" class="surabaya-map" data-leaflet-map data-map-items="{{ base64_encode($mapItems->toJson()) }}" role="region" aria-label="Peta puskesmas di Kota Surabaya"></div>
            <div class="map-overlay-note"><strong>Antrean hari ini</strong><span>Klik titik untuk melihat informasi. Perbesar peta untuk menampilkan nama faskes.</span></div>
        </div>
        <ul class="sr-only" aria-label="Daftar titik puskesmas pada peta">@foreach($mapItems as $item)<li>{{ $item['name'] }}, Kecamatan {{ $item['district'] }}, {{ $item['active'] }} antrean aktif.</li>@endforeach</ul>
        <p class="live-update-status" data-live-status role="status" aria-live="polite">Menghubungkan data antrean…</p>
    @endif
</section>

<section class="journey-showcase" id="cara-kerja" data-journey-showcase aria-labelledby="journey-title">
    <header class="journey-intro" data-scroll-blur>
        <div><p class="eyebrow">Langkah Anda</p><h2 id="journey-title">Cari, pilih, lalu <em>antre.</em></h2></div>
        <p>Informasi layanan dan antrean tersedia dalam satu alur yang mudah diikuti.</p>
    </header>
    <ol class="journey-cards" aria-label="Tiga langkah menggunakan PuskesmasKu">
        <li class="journey-card is-active" data-step-card tabindex="0" role="button" aria-expanded="true" style="--step-image:url('{{ asset('images/lihat_puskesmas.png') }}');--step-accent:#25c5e9">
            <span class="journey-card-number">01</span>
            <div class="journey-card-content"><span class="journey-card-kicker">Mulai dari kebutuhan Anda</span><h3>Cari puskesmas</h3><div class="journey-card-body"><p>Ketik nama, kecamatan, alamat, atau layanan yang Anda butuhkan.</p><span>Nama yang sesuai langsung ditampilkan dan disorot pada peta.</span></div></div>
        </li>
        <li class="journey-card" data-step-card tabindex="0" role="button" aria-expanded="false" style="--step-image:url('{{ asset('images/detail_layanan.png') }}');--step-accent:#caffde">
            <span class="journey-card-number">02</span>
            <div class="journey-card-content"><span class="journey-card-kicker">Bandingkan sebelum berkunjung</span><h3>Periksa detail layanan</h3><div class="journey-card-body"><p>Klik titik peta untuk melihat antrean, lalu buka jadwal puskesmas.</p><span>Periksa poli, dokter, hari praktik, dan kapasitas yang tersedia.</span></div></div>
        </li>
        <li class="journey-card" data-step-card tabindex="0" role="button" aria-expanded="false" style="--step-image:url('{{ asset('images/list_antre.png') }}');--step-accent:#ffd84d">
            <span class="journey-card-number">03</span>
            <div class="journey-card-content"><span class="journey-card-kicker">Siapkan kunjungan Anda</span><h3>Ambil antrean</h3><div class="journey-card-body"><p>Masuk sebagai masyarakat dan pilih jadwal yang kapasitasnya masih tersedia.</p><span>Nomor antrean aktif tersimpan dan dapat dipantau dari akun Anda.</span></div></div>
        </li>
    </ol>
</section>

<section class="care-cta"><div class="care-cta-copy"><p class="eyebrow">Butuh pilihan yang lebih terarah?</p><h2>Cari fasilitas kesehatan dengan <em>rekomendasi.</em></h2><p>Bandingkan jarak, antrean, poli, dokter, dan jadwal praktik dalam satu halaman.</p></div><img class="care-cta-image" src="{{ asset('images/rekomendasi.png') }}" alt="Tampilan fasilitas kesehatan dari udara" width="1448" height="1086" loading="lazy"><a class="button primary" href="{{ route('recommendations.index') }}">Lihat rekomendasi <svg class="icon-chevron" aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg></a></section>
@endsection
