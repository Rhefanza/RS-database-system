@extends('layouts.app')
@section('title', 'Antrean Saya')
@section('content')
<div class="page-title"><div><p class="eyebrow">Masyarakat</p><h1>Antrean aktif saya</h1><p>Menampilkan antrean berstatus menunggu, dipanggil, atau sedang dilayani. Antrean selesai dan dibatalkan otomatis keluar dari daftar aktif.</p></div></div>
<div class="stack-list">
@forelse ($queues as $queue)
    <article class="queue-card"><div class="queue-number">{{ str_pad($queue->nomor_antrean, 3, '0', STR_PAD_LEFT) }}</div><div><span class="status {{ strtolower($queue->status_antrean) }}">{{ $queue->status_antrean }}</span><h3>{{ $queue->schedule->puskesmasService->service->nama_layanan }}</h3><p>{{ $queue->schedule->puskesmasService->puskesmas->nama_puskesmas }} · {{ $queue->tanggal_daftar->translatedFormat('d F Y') }}</p></div>
        <div class="actions">@if ($queue->status_antrean === 'WAITING')<form method="post" action="{{ route('my-queues.cancel', $queue) }}">@csrf @method('patch')<button class="button danger">Batalkan</button></form>@endif</div>
    </article>
@empty <div class="empty">Anda tidak mempunyai antrean aktif.</div> @endforelse
</div>
@endsection
