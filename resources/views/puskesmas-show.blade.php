@extends('layouts.app')
@section('title', $puskesmas->nama_puskesmas)
@section('content')
<a class="back-link" href="{{ route('home') }}#peta-surabaya"><svg class="icon-chevron" aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 6-6 6 6 6"/></svg> Kembali ke peta</a>
<section class="detail-head has-puskesmas-photo"><img class="detail-puskesmas-photo" src="{{ $puskesmas->photo_url }}" alt="Ilustrasi suasana layanan puskesmas" fetchpriority="high"><div><p class="eyebrow">✦ Puskesmas aktif · Kecamatan {{ $puskesmas->district?->nama_kecamatan ?? 'belum diatur' }}</p><h1>{{ $puskesmas->nama_puskesmas }}</h1><p>{{ $puskesmas->alamat }}</p></div><aside class="detail-callout"><span>Informasi kontak</span>@if ($puskesmas->nomor_telepon)<a href="tel:{{ $puskesmas->nomor_telepon }}">{{ $puskesmas->nomor_telepon }}</a>@else<p>Telepon belum tersedia.</p>@endif<p>Periksa jadwal layanan sebelum mengambil antrean.</p></aside></section>
<section class="detail-services" id="layanan"><div class="section-heading"><div><p class="eyebrow">Jadwal layanan</p><h2>Pilih layanan dan <em>hari.</em></h2></div></div>
    <div class="stack-list">
    @forelse ($puskesmas->schedules->groupBy('layanan_id') as $schedules)
        <article class="list-card service-detail-card">@include('components.service-art', ['name' => $schedules->first()->service->nama_layanan, 'class' => 'service-art service-art-detail'])<div class="service-detail-intro"><div class="service-detail-copy"><h3>{{ $schedules->first()->service->nama_layanan }}</h3><p>{{ $schedules->first()->service->deskripsi }}</p></div></div>
            <div class="schedule-list">@forelse ($schedules as $schedule)
                @php
                    $map = ['MINGGU' => 0, 'SENIN' => 1, 'SELASA' => 2, 'RABU' => 3, 'KAMIS' => 4, 'JUMAT' => 5, 'SABTU' => 6];
                    $daysAhead = ($map[$schedule->hari] - today()->dayOfWeek + 7) % 7;
                    $date = today()->copy()->addDays($daysAhead);
                    $used = $schedule->queues->filter(fn ($queue) => $queue->tanggal_daftar->isSameDay($date))->count();
                    $existingQueue = auth()->check() && auth()->user()->role === 'MASYARAKAT'
                        ? $schedule->queues->first(fn ($queue) => (int) $queue->akun_id === (int) auth()->user()->akun_id && $queue->tanggal_daftar->isSameDay($date))
                        : null;
                @endphp
                <div class="schedule-row"><div><strong>{{ ucfirst(strtolower($schedule->hari)) }}</strong><span>{{ $schedule->nama_dokter ?: 'Dokter belum diatur' }}@if($schedule->spesialisasi) · {{ $schedule->spesialisasi }}@endif</span><span>{{ substr($schedule->jam_buka, 0, 5) }}–{{ substr($schedule->jam_tutup, 0, 5) }} · Sisa {{ max($schedule->kapasitas - $used, 0) }}/{{ $schedule->kapasitas }}</span></div>
                    @auth
                        @if (auth()->user()->role === 'MASYARAKAT')
                            @if ($existingQueue)
                                <a class="button ghost" href="{{ route('my-queues.index') }}">Lihat antrean</a>
                            @else
                                <form method="post" action="{{ route('my-queues.store', $schedule) }}">@csrf<input type="hidden" name="tanggal_daftar" value="{{ $date->toDateString() }}"><button class="button primary" type="submit" @disabled($used >= $schedule->kapasitas)>Ambil {{ $date->translatedFormat('d M') }}</button></form>
                            @endif
                        @endif
                    @else <a class="button primary" href="{{ route('login') }}">Masuk untuk antre</a> @endauth
                </div>
            @empty <p>Jadwal belum dibuat petugas.</p> @endforelse</div>
        </article>
    @empty <div class="empty">Jadwal layanan belum tersedia.</div> @endforelse
    </div>
</section>
@endsection
