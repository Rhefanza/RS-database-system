@extends('layouts.app')

@section('title', 'Peta antrean puskesmas Surabaya')

@section('content')
<section class="care-hero" aria-labelledby="hero-title">
    <img src="{{ asset('assets/rujuk-care-hero.png') }}" class="care-hero-photo" alt="Ilustrasi tenaga kesehatan berbincang dengan ibu dan anak" width="1536" height="1024" fetchpriority="high">
    <div class="care-hero-shade" aria-hidden="true"></div>
    <div class="care-hero-copy">
        <p class="eyebrow">✦ &nbsp; Layanan kesehatan Surabaya</p>
        <h1 id="hero-title">Temukan faskes<br>dan lihat <em>antreannya.</em></h1>
        <p>Cari puskesmas, lihat titiknya di peta Surabaya, lalu ambil antrean layanan secara daring.</p>
        <div class="hero-actions"><a href="#cari-puskesmas" class="button primary">Cari puskesmas <span aria-hidden="true">↗</span></a><a class="text-link" href="{{ route('recommendations.index') }}">Lihat rekomendasi →</a></div>
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
        <div class="finder-fields search-combobox"><div class="search-input-wrap"><label for="finder-query" class="sr-only">Nama puskesmas, kecamatan, alamat, atau layanan</label><input id="finder-query" type="search" name="q" maxlength="100" value="{{ $search ?? '' }}" placeholder="Contoh: Mulyorejo, Poli Gigi, atau Kecamatan Gubeng" autocomplete="off" aria-autocomplete="list" aria-controls="clinic-search-results" aria-expanded="false"><div class="search-suggestions" id="clinic-search-results" role="listbox" hidden></div></div><button class="button primary" type="submit">Tampilkan di peta <span aria-hidden="true">→</span></button></div>
        <div class="finder-bottom"><p>Ketik nama faskes, lalu pilih hasil untuk menyorot lokasinya pada peta.</p></div>
    </form>
</section>

<section class="care-promises" aria-label="Manfaat menggunakan layanan"><div><span aria-hidden="true">⌕</span><p><strong>Cari lebih mudah</strong><small>Nama berawalan sama langsung ditampilkan.</small></p></div><div><span aria-hidden="true">⌖</span><p><strong>Lihat di peta</strong><small>31 titik faskes pada peta Surabaya.</small></p></div><div><span aria-hidden="true">≋</span><p><strong>Ambil antrean</strong><small>Pilih layanan dan nomor antrean yang tersedia.</small></p></div></section>

<section class="home-map-section" id="peta-surabaya" aria-labelledby="map-title">
    <div class="section-heading map-section-heading"><div><p class="eyebrow">Peta layanan hari ini</p><h2 id="map-title">Semua faskes dalam <em>satu peta.</em></h2><p>Klik titik untuk melihat nama faskes, alamat, dan jumlah antrean aktif.</p></div><div class="map-summary" aria-label="Ringkasan peta"><div><strong>{{ $mapItems->count() }}</strong><span>Faskes</span></div><div><strong data-live-summary-total>{{ $totalQueues }}</strong><span>Total antrean</span></div><div><strong data-live-summary-active>{{ $activeQueues }}</strong><span>Masih aktif</span></div></div></div>

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

<section class="how-section" id="cara-kerja"><div><p class="eyebrow">Langkah Anda</p><h2>Cari, pilih, lalu <em>antre.</em></h2><p>Informasi layanan dan antrean tersedia dalam satu alur yang mudah diikuti.</p></div><ol><li><b>01</b><div><h3>Cari puskesmas</h3><p>Ketik nama, kecamatan, alamat, atau layanan yang Anda butuhkan.</p></div></li><li><b>02</b><div><h3>Periksa detail layanan</h3><p>Klik titik peta untuk melihat antrean, lalu buka jadwal puskesmas.</p></div></li><li><b>03</b><div><h3>Ambil antrean</h3><p>Masuk sebagai masyarakat dan pilih jadwal yang kapasitasnya masih tersedia.</p></div></li></ol></section>

<section class="care-cta"><div><p class="eyebrow">Butuh pilihan yang lebih terarah?</p><h2>Cari fasilitas kesehatan dengan <em>rekomendasi.</em></h2><p>Bandingkan jarak, antrean, poli, dokter, dan jadwal praktik dalam satu halaman.</p></div><a class="button primary" href="{{ route('recommendations.index') }}">Lihat rekomendasi ↗</a></section>
@endsection
