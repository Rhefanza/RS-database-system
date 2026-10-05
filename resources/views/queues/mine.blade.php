@extends('layouts.app')
@section('title', 'Antrean Saya')
@section('content')
<div class="page-title"><div><p class="eyebrow">Masyarakat</p><h1>Antrean aktif saya</h1><p>Antrean yang masih menunggu dapat dihapus. Antrean yang sudah dipanggil atau sedang dilayani tidak dapat dihapus.</p></div></div>
<div class="stack-list citizen-queue-list">
@forelse ($queues as $queue)
    <article class="queue-card citizen-queue-card"><img class="puskesmas-photo queue-puskesmas-photo" src="{{ $queue->schedule->puskesmas->photo_url }}" alt="Ilustrasi suasana layanan puskesmas" loading="lazy" decoding="async"><div class="queue-number" aria-label="Nomor antrean {{ $queue->nomor_antrean }}"><small>Nomor</small><strong>{{ str_pad($queue->nomor_antrean, 3, '0', STR_PAD_LEFT) }}</strong></div><div class="citizen-queue-details"><span class="status {{ strtolower($queue->status_antrean) }}">{{ $queue->status_antrean }}</span><h3>{{ $queue->schedule->service->nama_layanan }}</h3><p>{{ $queue->schedule->puskesmas->nama_puskesmas }} · {{ $queue->tanggal_daftar->translatedFormat('d F Y') }}</p></div>
        <div class="actions">@if ($queue->status_antrean === 'WAITING')<form method="post" action="{{ route('my-queues.destroy', $queue) }}" data-confirm-delete-queue>@csrf @method('delete')<button class="button danger">Hapus antrean</button></form>@endif</div>
    </article>
@empty <div class="empty">Anda tidak mempunyai antrean aktif.</div> @endforelse
</div>
@endsection
