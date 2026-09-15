@extends('layouts.app')
@section('title', $puskesmas->nama_puskesmas)
@section('content')
<a class="back-link" href="{{ route('home') }}">← Kembali ke daftar</a>
<section class="detail-head"><div><p class="eyebrow">Puskesmas aktif</p><h1>{{ $puskesmas->nama_puskesmas }}</h1><p>{{ $puskesmas->alamat }}</p><p>{{ $puskesmas->nomor_telepon ?: 'Telepon belum tersedia' }}</p></div></section>
<section><div class="section-heading"><div><p class="eyebrow">Jadwal layanan</p><h2>Pilih layanan dan hari</h2></div></div>
    <div class="stack-list">
    @forelse ($puskesmas->puskesmasServices as $relation)
        <article class="list-card"><div><h3>{{ $relation->service->nama_layanan }}</h3><p>{{ $relation->service->deskripsi }}</p></div>
            <div class="schedule-list">@forelse ($relation->schedules as $schedule)
                @php
                    $map = ['MINGGU' => 0, 'SENIN' => 1, 'SELASA' => 2, 'RABU' => 3, 'KAMIS' => 4, 'JUMAT' => 5, 'SABTU' => 6];
                    $daysAhead = ($map[$schedule->hari] - today()->dayOfWeek + 7) % 7;
                    $date = today()->copy()->addDays($daysAhead);
                    $used = $schedule->queues->filter(fn ($queue) => $queue->tanggal_daftar->isSameDay($date))->count();
                @endphp
                <div class="schedule-row"><div><strong>{{ ucfirst(strtolower($schedule->hari)) }}</strong><span>{{ substr($schedule->jam_buka, 0, 5) }}–{{ substr($schedule->jam_tutup, 0, 5) }} · Sisa {{ max($schedule->kapasitas - $used, 0) }}/{{ $schedule->kapasitas }}</span></div>
                    @auth
                        @if (auth()->user()->role === 'MASYARAKAT')<form method="post" action="{{ route('my-queues.store', $schedule) }}">@csrf<input type="hidden" name="tanggal_daftar" value="{{ $date->toDateString() }}"><button class="button primary" @disabled($used >= $schedule->kapasitas)>Ambil {{ $date->translatedFormat('d M') }}</button></form>@endif
                    @else <a class="button primary" href="{{ route('login') }}">Masuk untuk antre</a> @endauth
                </div>
            @empty <p>Jadwal belum dibuat petugas.</p> @endforelse</div>
        </article>
    @empty <div class="empty">Layanan belum dihubungkan.</div> @endforelse
    </div>
</section>
@endsection
