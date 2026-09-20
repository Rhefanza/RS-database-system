@extends('layouts.app')
@section('title', $puskesmas->nama_puskesmas)
@section('content')
<a class="back-link" href="{{ route('home') }}#peta-surabaya">← Kembali ke peta</a>
<section class="detail-head"><div><p class="eyebrow">✦ Puskesmas aktif · Kecamatan {{ $puskesmas->district?->nama_kecamatan ?? 'belum diatur' }}</p><h1>{{ $puskesmas->nama_puskesmas }}</h1><p>{{ $puskesmas->alamat }}</p></div><aside class="detail-callout"><span>Informasi kontak</span>@if ($puskesmas->nomor_telepon)<a href="tel:{{ $puskesmas->nomor_telepon }}">{{ $puskesmas->nomor_telepon }}</a>@else<p>Telepon belum tersedia.</p>@endif<p>Periksa jadwal layanan sebelum mengambil antrean.</p></aside></section>
<section class="detail-services" id="layanan"><div class="section-heading"><div><p class="eyebrow">Jadwal layanan</p><h2>Pilih layanan dan <em>hari.</em></h2></div></div>
    <div class="stack-list">
    @forelse ($puskesmas->puskesmasServices as $relation)
        <article class="list-card"><div><h3>{{ $relation->service->nama_layanan }}</h3><p>{{ $relation->service->deskripsi }}</p><div class="service-doctors">@forelse($relation->doctors as $doctor)<span><b>{{ $doctor->nama_dokter }}</b><small>{{ $doctor->spesialisasi }}</small></span>@empty<span>Dokter belum dijadwalkan.</span>@endforelse</div></div>
            <div class="schedule-list">@forelse ($relation->schedules as $schedule)
                @php
                    $map = ['MINGGU' => 0, 'SENIN' => 1, 'SELASA' => 2, 'RABU' => 3, 'KAMIS' => 4, 'JUMAT' => 5, 'SABTU' => 6];
                    $daysAhead = ($map[$schedule->hari] - today()->dayOfWeek + 7) % 7;
                    $date = today()->copy()->addDays($daysAhead);
                    $used = $schedule->queues->filter(fn ($queue) => $queue->tanggal_daftar->isSameDay($date))->count();
                    $existingQueue = auth()->check() && auth()->user()->role === 'MASYARAKAT'
                        ? $schedule->queues->first(fn ($queue) => $queue->nik === auth()->user()->nik && $queue->tanggal_daftar->isSameDay($date))
                        : null;
                @endphp
                <div class="schedule-row"><div><strong>{{ ucfirst(strtolower($schedule->hari)) }}</strong><span>{{ substr($schedule->jam_buka, 0, 5) }}–{{ substr($schedule->jam_tutup, 0, 5) }} · Sisa {{ max($schedule->kapasitas - $used, 0) }}/{{ $schedule->kapasitas }}</span></div>
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
    @empty <div class="empty">Layanan belum dihubungkan.</div> @endforelse
    </div>
</section>
@endsection
