@extends('layouts.app')
@section('title', 'Rekomendasi faskes')
@section('content')
<section class="recommendation-hero">
    <div><p class="eyebrow">Rekomendasi berbasis lokasi</p><h1>Lima pilihan yang lebih <em>dekat dan lengang.</em></h1><p>Kami mengambil 10 faskes terdekat, lalu memilih lima dengan antrean aktif lebih sedikit.</p></div>
    <div class="recommendation-location-panel"><span>Lokasi pembanding</span><strong data-recommendation-origin>Pusat Kota Surabaya</strong><button class="button primary" type="button" data-use-recommendation-location>Gunakan lokasi saya</button><small data-recommendation-status role="status" aria-live="polite">Izinkan lokasi untuk mendapatkan urutan yang lebih sesuai.</small></div>
</section>

<section class="recommendation-results" aria-labelledby="recommendation-title">
    <div class="section-heading"><div><p class="eyebrow">Hasil rekomendasi</p><h2 id="recommendation-title">Pilihan untuk kunjungan Anda.</h2></div><span class="count">5 dari {{ $recommendations->count() }} faskes</span></div>
    <div class="recommendation-list" data-recommendation-list>
        @foreach($recommendations->sortBy('active')->values() as $item)
            <article class="recommendation-card" data-recommendation-card data-puskesmas-id="{{ $item['id'] }}" data-lat="{{ $item['latitude'] }}" data-lng="{{ $item['longitude'] }}" data-active="{{ $item['active'] }}" @if($loop->iteration > 5) hidden @endif>
                <div class="recommendation-rank" data-recommendation-rank>{{ str_pad((string) min($loop->iteration, 5), 2, '0', STR_PAD_LEFT) }}</div>
                <div class="recommendation-main">
                    <div class="recommendation-title-row"><div><span class="district-pill">Kecamatan {{ $item['district'] }}</span><h3>{{ $item['name'] }}</h3><p>{{ $item['address'] }}</p></div><div class="recommendation-metrics"><div><strong data-recommendation-distance>—</strong><span>Jarak</span></div><div><strong data-live-active>{{ $item['active'] }}</strong><span>Antrean aktif</span></div></div></div>
                    <div class="recommendation-services">
                        @foreach($item['service_details'] as $service)
                            <section class="recommendation-service"><div><h4>{{ $service['name'] }}</h4><p>{{ $service['description'] }}</p></div><div class="doctor-list">@forelse($service['doctors'] as $doctor)<span><b>{{ $doctor['name'] }}</b><small>{{ $doctor['specialization'] }}</small></span>@empty<span><b>Dokter belum dijadwalkan</b></span>@endforelse</div><div class="practice-list">@forelse($service['schedules'] as $schedule)<span>{{ $schedule['day'] }} · {{ $schedule['open'] }}–{{ $schedule['close'] }}</span>@empty<span>Jadwal belum tersedia</span>@endforelse</div></section>
                        @endforeach
                    </div>
                    <div class="recommendation-actions"><a class="button primary" href="{{ $item['url'] }}">Lihat jadwal & ambil antrean</a>@if($item['phone'])<a class="button ghost" href="tel:{{ $item['phone'] }}">{{ $item['phone'] }}</a>@endif</div>
                </div>
            </article>
        @endforeach
    </div>
</section>
<p class="live-update-status" data-live-status role="status" aria-live="polite">Menghubungkan data antrean…</p>
@endsection
