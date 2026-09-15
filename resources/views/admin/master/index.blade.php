@extends('layouts.app')

@section('title', 'Data master UTS')

@section('content')
<section class="admin-heading">
    <div class="shell heading-row">
        <div><p class="eyebrow">Cakupan UTS</p><h1>Data master & relasi</h1><p>Kelola seluruh entitas inti database dari satu panel.</p></div>
        <span class="today-chip">{{ $districts->count() + $services->count() + $hospitalServices->count() }} data inti</span>
    </div>
</section>

<nav class="shell master-jump" aria-label="Bagian data master">
    <a href="#kecamatan">Kecamatan</a><a href="#layanan">Layanan</a><a href="#rumah-sakit-layanan">RS–Layanan</a>
    <a href="#jadwal">Jadwal</a><a href="#jadwal-khusus">Jadwal khusus</a><a href="#loket">Loket</a>
    <a href="#akun">Akun</a><a href="#penugasan">Penugasan</a>
</nav>

<div class="shell master-stack">
    <section id="kecamatan" class="master-section">
        <header><div><p class="eyebrow">01 · Wilayah</p><h2>Kecamatan</h2></div><span>{{ $districts->count() }} data</span></header>
        <form class="master-create-form compact" method="post" action="{{ route('admin.master.districts.store') }}">@csrf
            <label><span>Nama kecamatan</span><input name="name" required maxlength="100" placeholder="Contoh: Rungkut"></label>
            <button class="button button-primary" type="submit">Tambah kecamatan</button>
        </form>
        <div class="master-list">
            @foreach ($districts as $district)
                <article><div><strong>{{ $district->name }}</strong><small>{{ $district->hospitals_count }} rumah sakit</small></div>
                    <div class="master-actions"><details><summary>Edit</summary><form method="post" action="{{ route('admin.master.districts.update', $district) }}">@csrf @method('PUT')<input name="name" value="{{ $district->name }}" required><button>Simpan</button></form></details>
                    <form method="post" action="{{ route('admin.master.districts.destroy', $district) }}" onsubmit="return confirm('Hapus kecamatan ini?')">@csrf @method('DELETE')<button class="danger-link">Hapus</button></form></div>
                </article>
            @endforeach
        </div>
    </section>

    <section id="layanan" class="master-section">
        <header><div><p class="eyebrow">02 · Referensi</p><h2>Master layanan</h2></div><span>{{ $services->count() }} data</span></header>
        <form class="master-create-form" method="post" action="{{ route('admin.master.services.store') }}">@csrf
            <label><span>Nama layanan</span><input name="name" required maxlength="100" placeholder="Contoh: Poli Gigi"></label>
            <label><span>Deskripsi</span><input name="description" maxlength="255" placeholder="Deskripsi singkat"></label>
            <button class="button button-primary" type="submit">Tambah layanan</button>
        </form>
        <div class="master-list">
            @foreach ($services as $service)
                <article><div><strong>{{ $service->name }}</strong><small>{{ $service->description ?: 'Tanpa deskripsi' }} · {{ $service->hospital_services_count }} rumah sakit</small></div>
                    <div class="master-actions"><details><summary>Edit</summary><form class="wide-edit" method="post" action="{{ route('admin.master.services.update', $service) }}">@csrf @method('PUT')<input name="name" value="{{ $service->name }}" required><input name="description" value="{{ $service->description }}" placeholder="Deskripsi"><button>Simpan</button></form></details>
                    <form method="post" action="{{ route('admin.master.services.destroy', $service) }}" onsubmit="return confirm('Hapus layanan ini?')">@csrf @method('DELETE')<button class="danger-link">Hapus</button></form></div>
                </article>
            @endforeach
        </div>
    </section>

    <section id="rumah-sakit-layanan" class="master-section">
        <header><div><p class="eyebrow">03 · Relasi M:N</p><h2>Rumah sakit–layanan</h2></div><span>{{ $hospitalServices->count() }} relasi</span></header>
        <form class="master-create-form four" method="post" action="{{ route('admin.master.hospital-services.store') }}">@csrf
            <label><span>Rumah sakit</span><select name="hospital_id" required><option value="">Pilih</option>@foreach($hospitals as $hospital)<option value="{{ $hospital->id }}">{{ $hospital->name }}</option>@endforeach</select></label>
            <label><span>Layanan</span><select name="service_id" required><option value="">Pilih</option>@foreach($services as $service)<option value="{{ $service->id }}">{{ $service->name }}</option>@endforeach</select></label>
            <label><span>Durasi (menit)</span><input type="number" name="initial_service_duration" min="1" max="480" value="20"></label>
            <label><span>Status</span><select name="availability_status"><option value="ACTIVE">Aktif</option><option value="INACTIVE">Tidak aktif</option></select></label>
            <button class="button button-primary" type="submit">Hubungkan layanan</button>
        </form>
        <div class="data-table-wrap"><table class="data-table master-table"><thead><tr><th>Rumah sakit</th><th>Layanan</th><th>Durasi</th><th>Turunan</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
            @foreach($hospitalServices as $item)<tr><td><strong>{{ $item->hospital->name }}</strong></td><td>{{ $item->service->name }}</td><td>{{ $item->initial_service_duration ?: '—' }} menit</td><td>{{ $item->schedules_count }} jadwal · {{ $item->desks_count }} loket</td><td><span class="table-status {{ $item->availability_status === 'ACTIVE' ? 'is-on' : '' }}">{{ $item->availability_status }}</span></td><td class="master-actions">
                <details><summary>Edit</summary><form method="post" action="{{ route('admin.master.hospital-services.update', $item) }}">@csrf @method('PUT')<input type="hidden" name="hospital_id" value="{{ $item->hospital_id }}"><select name="service_id">@foreach($services as $service)<option value="{{ $service->id }}" @selected($item->service_id === $service->id)>{{ $service->name }}</option>@endforeach</select><input type="number" name="initial_service_duration" value="{{ $item->initial_service_duration }}" min="1" max="480"><select name="availability_status"><option value="ACTIVE" @selected($item->availability_status === 'ACTIVE')>Aktif</option><option value="INACTIVE" @selected($item->availability_status === 'INACTIVE')>Tidak aktif</option></select><button>Simpan</button></form></details>
                <form method="post" action="{{ route('admin.master.hospital-services.destroy', $item) }}" onsubmit="return confirm('Hapus relasi layanan ini?')">@csrf @method('DELETE')<button class="danger-link">Hapus</button></form>
            </td></tr>@endforeach
        </tbody></table></div>
    </section>

    <section id="jadwal" class="master-section">
        <header><div><p class="eyebrow">04 · Operasional</p><h2>Jadwal layanan rutin</h2></div><span>{{ $schedules->count() }} jadwal</span></header>
        <form class="master-create-form schedule" method="post" action="{{ route('admin.master.schedules.store') }}">@csrf
            <label><span>Rumah sakit & layanan</span><select name="hospital_service_id" required><option value="">Pilih</option>@foreach($hospitalServices as $item)<option value="{{ $item->id }}">{{ $item->hospital->name }} — {{ $item->service->name }}</option>@endforeach</select></label>
            <label><span>Hari</span><select name="day" required>@foreach($days as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
            <label><span>Buka</span><input type="time" name="opens_at" required value="08:00"></label><label><span>Tutup</span><input type="time" name="closes_at" required value="12:00"></label>
            <label><span>Kuota</span><input type="number" name="quota" min="1"></label><label><span>Status</span><select name="schedule_status"><option value="ACTIVE">Aktif</option><option value="INACTIVE">Tidak aktif</option></select></label>
            <button class="button button-primary" type="submit">Tambah jadwal</button>
        </form>
        <div class="master-list">
            @forelse($schedules as $schedule)<article><div><strong>{{ $schedule->hospitalService->hospital->name }} — {{ $schedule->hospitalService->service->name }}</strong><small>{{ $days[$schedule->day] }} · {{ substr($schedule->opens_at, 0, 5) }}–{{ substr($schedule->closes_at, 0, 5) }} · Kuota {{ $schedule->quota ?: 'fleksibel' }} · {{ $schedule->schedule_status }}</small></div><div class="master-actions">
                <details><summary>Edit</summary><form method="post" action="{{ route('admin.master.schedules.update', $schedule) }}">@csrf @method('PUT')<input type="hidden" name="hospital_service_id" value="{{ $schedule->hospital_service_id }}"><select name="day">@foreach($days as $value => $label)<option value="{{ $value }}" @selected($schedule->day === $value)>{{ $label }}</option>@endforeach</select><input type="time" name="opens_at" value="{{ substr($schedule->opens_at, 0, 5) }}"><input type="time" name="closes_at" value="{{ substr($schedule->closes_at, 0, 5) }}"><input type="number" name="quota" value="{{ $schedule->quota }}" min="1"><select name="schedule_status"><option value="ACTIVE" @selected($schedule->schedule_status === 'ACTIVE')>Aktif</option><option value="INACTIVE" @selected($schedule->schedule_status === 'INACTIVE')>Tidak aktif</option></select><button>Simpan</button></form></details>
                <form method="post" action="{{ route('admin.master.schedules.destroy', $schedule) }}" onsubmit="return confirm('Hapus jadwal ini?')">@csrf @method('DELETE')<button class="danger-link">Hapus</button></form></div></article>
            @empty<p class="master-empty">Belum ada jadwal rutin.</p>@endforelse
        </div>
    </section>

    <section id="jadwal-khusus" class="master-section">
        <header><div><p class="eyebrow">05 · Pengecualian</p><h2>Jadwal khusus</h2></div><span>{{ $specialSchedules->count() }} jadwal</span></header>
        <form class="master-create-form schedule" method="post" action="{{ route('admin.master.special-schedules.store') }}">@csrf
            <label><span>Rumah sakit & layanan</span><select name="hospital_service_id" required><option value="">Pilih</option>@foreach($hospitalServices as $item)<option value="{{ $item->id }}">{{ $item->hospital->name }} — {{ $item->service->name }}</option>@endforeach</select></label>
            <label><span>Tanggal</span><input type="date" name="date" required></label><label><span>Buka khusus</span><input type="time" name="special_opens_at"></label><label><span>Tutup khusus</span><input type="time" name="special_closes_at"></label>
            <label><span>Status</span><select name="status"><option value="CHANGED">Berubah</option><option value="OPEN">Buka</option><option value="CLOSED">Tutup</option></select></label><label><span>Alasan</span><input name="reason" maxlength="255"></label>
            <button class="button button-primary" type="submit">Tambah jadwal khusus</button>
        </form>
        <div class="master-list">
            @forelse($specialSchedules as $schedule)<article><div><strong>{{ $schedule->date->format('d/m/Y') }} · {{ $schedule->hospitalService->hospital->name }}</strong><small>{{ $schedule->hospitalService->service->name }} · {{ $schedule->status }} · {{ $schedule->reason ?: 'Tanpa alasan' }}</small></div><div class="master-actions">
                <details><summary>Edit</summary><form method="post" action="{{ route('admin.master.special-schedules.update', $schedule) }}">@csrf @method('PUT')<input type="hidden" name="hospital_service_id" value="{{ $schedule->hospital_service_id }}"><input type="date" name="date" value="{{ $schedule->date->format('Y-m-d') }}"><input type="time" name="special_opens_at" value="{{ $schedule->special_opens_at ? substr($schedule->special_opens_at, 0, 5) : '' }}"><input type="time" name="special_closes_at" value="{{ $schedule->special_closes_at ? substr($schedule->special_closes_at, 0, 5) : '' }}"><select name="status"><option value="CHANGED" @selected($schedule->status === 'CHANGED')>Berubah</option><option value="OPEN" @selected($schedule->status === 'OPEN')>Buka</option><option value="CLOSED" @selected($schedule->status === 'CLOSED')>Tutup</option></select><input name="reason" value="{{ $schedule->reason }}" placeholder="Alasan"><button>Simpan</button></form></details>
                <form method="post" action="{{ route('admin.master.special-schedules.destroy', $schedule) }}" onsubmit="return confirm('Hapus jadwal khusus ini?')">@csrf @method('DELETE')<button class="danger-link">Hapus</button></form></div></article>
            @empty<p class="master-empty">Belum ada jadwal khusus.</p>@endforelse
        </div>
    </section>

    <section id="loket" class="master-section">
        <header><div><p class="eyebrow">06 · Pelayanan</p><h2>Loket layanan</h2></div><span>{{ $desks->count() }} loket</span></header>
        <form class="master-create-form" method="post" action="{{ route('admin.master.desks.store') }}">@csrf
            <label><span>Rumah sakit & layanan</span><select name="hospital_service_id" required><option value="">Pilih</option>@foreach($hospitalServices as $item)<option value="{{ $item->id }}">{{ $item->hospital->name }} — {{ $item->service->name }}</option>@endforeach</select></label>
            <label><span>Nama loket</span><input name="name" required placeholder="Loket 1"></label><label><span>Status</span><select name="desk_status"><option value="ACTIVE">Aktif</option><option value="INACTIVE">Tidak aktif</option></select></label>
            <button class="button button-primary" type="submit">Tambah loket</button>
        </form>
        <div class="master-list">@foreach($desks as $desk)<article><div><strong>{{ $desk->name }} · {{ $desk->hospitalService->hospital->name }}</strong><small>{{ $desk->hospitalService->service->name }} · {{ $desk->desk_status }}</small></div><div class="master-actions">
            <details><summary>Edit</summary><form method="post" action="{{ route('admin.master.desks.update', $desk) }}">@csrf @method('PUT')<input type="hidden" name="hospital_service_id" value="{{ $desk->hospital_service_id }}"><input name="name" value="{{ $desk->name }}"><select name="desk_status"><option value="ACTIVE" @selected($desk->desk_status === 'ACTIVE')>Aktif</option><option value="INACTIVE" @selected($desk->desk_status === 'INACTIVE')>Tidak aktif</option></select><button>Simpan</button></form></details>
            <form method="post" action="{{ route('admin.master.desks.destroy', $desk) }}" onsubmit="return confirm('Hapus loket ini?')">@csrf @method('DELETE')<button class="danger-link">Hapus</button></form></div></article>@endforeach</div>
    </section>

    <section id="akun" class="master-section">
        <header><div><p class="eyebrow">07 · Akses</p><h2>Akun pengguna</h2></div><span>{{ $users->count() }} akun</span></header>
        <form class="master-create-form four" method="post" action="{{ route('admin.master.users.store') }}">@csrf
            <label><span>Nama lengkap</span><input name="name" required></label><label><span>Email</span><input type="email" name="email" required></label><label><span>Telepon</span><input name="phone"></label><label><span>Kata sandi</span><input type="password" name="password" required minlength="8"></label>
            <label><span>Peran</span><select name="role"><option value="OFFICER">Petugas</option><option value="ADMIN">Admin</option><option value="PUBLIC">Masyarakat</option></select></label><label><span>Status</span><select name="account_status"><option value="ACTIVE">Aktif</option><option value="INACTIVE">Tidak aktif</option></select></label>
            <button class="button button-primary" type="submit">Tambah akun</button>
        </form>
        <div class="master-list">@foreach($users as $user)<article><div><strong>{{ $user->name }}</strong><small>{{ $user->email }} · {{ $user->role }} · {{ $user->account_status }} · {{ $user->staff_assignments_count }} penugasan</small></div><div class="master-actions">
            <details><summary>Edit</summary><form method="post" action="{{ route('admin.master.users.update', $user) }}">@csrf @method('PUT')<input name="name" value="{{ $user->name }}" required><input type="email" name="email" value="{{ $user->email }}" required><input name="phone" value="{{ $user->phone }}" placeholder="Telepon"><input type="password" name="password" placeholder="Kosongkan jika tetap"><select name="role"><option value="ADMIN" @selected($user->role === 'ADMIN')>Admin</option><option value="OFFICER" @selected($user->role === 'OFFICER')>Petugas</option><option value="PUBLIC" @selected($user->role === 'PUBLIC')>Masyarakat</option></select><select name="account_status"><option value="ACTIVE" @selected($user->account_status === 'ACTIVE')>Aktif</option><option value="INACTIVE" @selected($user->account_status === 'INACTIVE')>Tidak aktif</option></select><button>Simpan</button></form></details>
            <form method="post" action="{{ route('admin.master.users.destroy', $user) }}" onsubmit="return confirm('Hapus akun ini?')">@csrf @method('DELETE')<button class="danger-link">Hapus</button></form></div></article>@endforeach</div>
    </section>

    <section id="penugasan" class="master-section">
        <header><div><p class="eyebrow">08 · Organisasi</p><h2>Penugasan petugas</h2></div><span>{{ $assignments->count() }} penugasan</span></header>
        <form class="master-create-form four" method="post" action="{{ route('admin.master.assignments.store') }}">@csrf
            <label><span>Petugas</span><select name="user_id" required><option value="">Pilih</option>@foreach($users->where('role', 'OFFICER') as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select></label><label><span>Rumah sakit</span><select name="hospital_id" required><option value="">Pilih</option>@foreach($hospitals as $hospital)<option value="{{ $hospital->id }}">{{ $hospital->name }}</option>@endforeach</select></label>
            <label><span>Kode pegawai</span><input name="employee_code"></label><label><span>Mulai</span><input type="date" name="starts_on" required value="{{ today()->format('Y-m-d') }}"></label><label><span>Selesai</span><input type="date" name="ends_on"></label><label><span>Status</span><select name="assignment_status"><option value="ACTIVE">Aktif</option><option value="INACTIVE">Tidak aktif</option></select></label>
            <button class="button button-primary" type="submit">Tambah penugasan</button>
        </form>
        <div class="master-list">
            @forelse($assignments as $assignment)<article><div><strong>{{ $assignment->user->name }} → {{ $assignment->hospital->name }}</strong><small>{{ $assignment->employee_code ?: 'Tanpa kode' }} · {{ $assignment->starts_on->format('d/m/Y') }}–{{ $assignment->ends_on?->format('d/m/Y') ?: 'sekarang' }} · {{ $assignment->assignment_status }}</small></div><div class="master-actions">
                <details><summary>Edit</summary><form method="post" action="{{ route('admin.master.assignments.update', $assignment) }}">@csrf @method('PUT')<select name="user_id">@foreach($users->where('role', 'OFFICER') as $user)<option value="{{ $user->id }}" @selected($assignment->user_id === $user->id)>{{ $user->name }}</option>@endforeach</select><select name="hospital_id">@foreach($hospitals as $hospital)<option value="{{ $hospital->id }}" @selected($assignment->hospital_id === $hospital->id)>{{ $hospital->name }}</option>@endforeach</select><input name="employee_code" value="{{ $assignment->employee_code }}"><input type="date" name="starts_on" value="{{ $assignment->starts_on->format('Y-m-d') }}"><input type="date" name="ends_on" value="{{ $assignment->ends_on?->format('Y-m-d') }}"><select name="assignment_status"><option value="ACTIVE" @selected($assignment->assignment_status === 'ACTIVE')>Aktif</option><option value="INACTIVE" @selected($assignment->assignment_status === 'INACTIVE')>Tidak aktif</option></select><button>Simpan</button></form></details>
                <form method="post" action="{{ route('admin.master.assignments.destroy', $assignment) }}" onsubmit="return confirm('Hapus penugasan ini?')">@csrf @method('DELETE')<button class="danger-link">Hapus</button></form></div></article>
            @empty<p class="master-empty">Belum ada penugasan petugas.</p>@endforelse
        </div>
    </section>
</div>
@endsection
