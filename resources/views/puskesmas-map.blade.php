@extends('layouts.app')
@section('title', 'Peta Puskesmas')
@section('content')
<section class="map-hero">
    <div><p class="eyebrow">Peta layanan hari ini</p><h1>Lihat puskesmas dan <em>antreannya.</em></h1><p>Pilih marker untuk melihat alamat, layanan, dan total antrean hari ini. Posisi marker menggunakan koordinat data puskesmas.</p></div>
    <div class="map-summary" aria-label="Ringkasan peta"><div><strong>{{ $mapItems->count() }}</strong><span>Puskesmas</span></div><div><strong data-live-summary-total>{{ $totalQueues }}</strong><span>Total antrean</span></div><div><strong data-live-summary-active>{{ $activeQueues }}</strong><span>Masih aktif</span></div></div>
</section>

@if ($mapItems->isEmpty())
    <div class="empty">Belum ada puskesmas aktif yang memiliki koordinat peta.</div>
@else
    <section class="map-layout" data-clinic-map>
        <div class="map-board" role="region" aria-label="Denah interaktif puskesmas Surabaya">
            <div class="map-grid" aria-hidden="true"></div>
            <span class="map-road road-one" aria-hidden="true"></span><span class="map-road road-two" aria-hidden="true"></span><span class="map-river" aria-hidden="true"></span>
            <div class="map-compass" aria-hidden="true"><b>U</b><span>↑</span></div>
            @foreach ($mapItems as $index => $item)
                <button class="map-marker @if($index === 0) is-active @endif" type="button" style="--map-x: {{ $item['x'] }}%; --map-y: {{ $item['y'] }}%" data-map-select="{{ $item['id'] }}" data-puskesmas-id="{{ $item['id'] }}" aria-controls="map-detail-{{ $item['id'] }}" aria-pressed="{{ $index === 0 ? 'true' : 'false' }}">
                    <span data-live-total>{{ $item['total'] }}</span><small>{{ $item['name'] }}</small>
                </button>
            @endforeach
            <div class="map-legend"><span><i></i> Angka marker = total antrean hari ini</span><small>Denah posisi relatif · bukan navigasi jalan</small></div>
        </div>

        <aside class="map-sidebar" aria-live="polite">
            <div class="map-location-list" aria-label="Daftar puskesmas">
                @foreach ($mapItems as $index => $item)
                    <button type="button" class="map-location-button @if($index === 0) is-active @endif" data-map-select="{{ $item['id'] }}" data-puskesmas-id="{{ $item['id'] }}"><span>{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span><b>{{ $item['name'] }}</b><small><span data-live-active>{{ $item['active'] }}</span> aktif</small></button>
                @endforeach
            </div>

            @foreach ($mapItems as $index => $item)
                <article class="map-detail" id="map-detail-{{ $item['id'] }}" data-map-detail="{{ $item['id'] }}" data-puskesmas-id="{{ $item['id'] }}" @if($index !== 0) hidden @endif>
                    <p class="eyebrow">Antrean hari ini</p><h2>{{ $item['name'] }}</h2><p class="map-address">{{ $item['address'] }}</p>
                    <div class="queue-metrics"><div><strong data-live-total>{{ $item['total'] }}</strong><span>Total</span></div><div><strong data-live-active>{{ $item['active'] }}</strong><span>Aktif</span></div><div><strong data-live-completed>{{ $item['completed'] }}</strong><span>Selesai</span></div></div>
                    <div class="chips">@forelse($item['services'] as $service)<span>{{ $service }}</span>@empty<span>Layanan belum tersedia</span>@endforelse</div>
                    <div class="map-detail-actions"><a class="button primary" href="{{ $item['url'] }}">Lihat jadwal</a>@if($item['phone'])<a class="button ghost" href="tel:{{ $item['phone'] }}">{{ $item['phone'] }}</a>@endif</div>
                    <small class="map-coordinate">{{ number_format($item['latitude'], 5) }}, {{ number_format($item['longitude'], 5) }}</small>
                </article>
            @endforeach
        </aside>
    </section>
    <p class="live-update-status" data-live-status role="status" aria-live="polite">Menghubungkan data antrean live…</p>
@endif
@endsection
