@extends('layouts.app')
@section('title', 'Daftar Puskesmas')
@section('content')
<section class="hero">
    <div><p class="eyebrow">Sistem antrean UTS</p><h1>Cari layanan puskesmas tanpa alur yang rumit.</h1><p>Lihat layanan, jadwal, dan kapasitas. Masyarakat yang sudah aktivasi dapat mengambil antrean.</p></div>
    <form method="get" action="{{ route('home') }}" class="search-form"><input name="q" value="{{ $search }}" placeholder="Nama puskesmas atau layanan"><button class="button primary">Cari</button></form>
</section>
<section>
    <div class="section-heading"><div><p class="eyebrow">Data aktif</p><h2>{{ $items->count() }} puskesmas tersedia</h2></div></div>
    <div class="card-grid">
        @forelse ($items as $item)
            <article class="card"><span class="status active">AKTIF</span><h3>{{ $item->nama_puskesmas }}</h3><p>{{ $item->alamat }}</p><div class="chips">@foreach ($item->services as $service)<span>{{ $service->nama_layanan }}</span>@endforeach</div><a class="button secondary" href="{{ route('puskesmas.show', $item) }}">Lihat jadwal</a></article>
        @empty <div class="empty">Tidak ada puskesmas yang sesuai.</div> @endforelse
    </div>
</section>
@endsection
