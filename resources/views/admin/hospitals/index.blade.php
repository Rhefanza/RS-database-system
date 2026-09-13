@extends('layouts.app')

@section('title', 'Kelola rumah sakit')

@section('content')
<section class="admin-heading">
    <div class="shell heading-row">
        <div>
            <p class="eyebrow">Panel pengelola</p>
            <h1>Data rumah sakit</h1>
            <p>Perbarui informasi yang akan dibaca oleh masyarakat.</p>
        </div>
        <a class="button button-primary" href="{{ route('admin.hospitals.create') }}">+ Tambah rumah sakit</a>
    </div>
</section>

<section class="shell admin-content">
    <div class="stat-strip">
        <div><span>Total data</span><strong>{{ $stats['total'] }}</strong></div>
        <div><span>IGD 24 jam</span><strong>{{ $stats['emergency'] }}</strong></div>
        <div><span>Wilayah</span><strong>{{ $stats['cities'] }}</strong></div>
        <div><span>Kelas tersedia</span><strong>{{ $stats['classes'] }}</strong></div>
    </div>

    <div class="table-toolbar">
        <div><h2>Daftar aktif</h2><p>Terakhir diperbarui langsung dari database.</p></div>
        <form method="get" action="{{ route('admin.index') }}">
            <input name="q" value="{{ $query }}" placeholder="Cari nama atau kota">
            <button type="submit">Cari</button>
        </form>
    </div>

    <div class="data-table-wrap">
        <table class="data-table">
            <thead><tr><th>Rumah sakit</th><th>Kelas</th><th>Pengelola</th><th>Wilayah</th><th>Status IGD</th><th><span class="sr-only">Aksi</span></th></tr></thead>
            <tbody>
                @forelse ($items as $hospital)
                    <tr>
                        <td><strong>{{ $hospital->name }}</strong><span>{{ $hospital->code }}</span></td>
                        <td>Kelas {{ $hospital->class }}</td>
                        <td>{{ $hospital->ownership }}</td>
                        <td>{{ $hospital->city }}</td>
                        <td><span class="table-status {{ $hospital->is_emergency ? 'is-on' : '' }}">{{ $hospital->is_emergency ? '24 jam' : 'Terbatas' }}</span></td>
                        <td class="table-actions">
                            <a href="{{ route('hospitals.show', $hospital) }}">Lihat</a>
                            <a href="{{ route('admin.hospitals.edit', $hospital) }}">Edit</a>
                            <button type="button" data-delete-trigger data-id="{{ $hospital->id }}" data-name="{{ $hospital->name }}">Hapus</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="table-empty">Tidak ada data yang cocok dengan pencarian.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<dialog class="delete-dialog" data-delete-dialog>
    <form method="dialog" class="dialog-close-row"><button aria-label="Tutup">×</button></form>
    <p class="eyebrow">Konfirmasi hapus</p>
    <h2>Hapus data rumah sakit?</h2>
    <p>Data <strong data-delete-name></strong> akan dihapus permanen dari direktori.</p>
    <div class="dialog-actions">
        <button class="button button-quiet" type="button" data-delete-cancel>Batal</button>
        <form method="post" data-delete-form data-action-template="{{ route('admin.hospitals.destroy', ['hospital' => '__ID__']) }}">
            @csrf
            @method('DELETE')
            <button class="button button-danger" type="submit">Ya, hapus data</button>
        </form>
    </div>
</dialog>
@endsection
