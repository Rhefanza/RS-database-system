@extends('layouts.app')

@php($editing = $hospital->exists)
@section('title', $editing ? 'Edit rumah sakit' : 'Tambah rumah sakit')

@section('content')
<section class="form-page shell">
    <div class="form-topbar">
        <div>
            <a class="back-link" href="{{ route('admin.index') }}">← Kembali ke daftar</a>
            <p class="eyebrow">{{ $editing ? 'Perbarui data' : 'Entri baru' }}</p>
            <h1>{{ $editing ? 'Edit rumah sakit' : 'Tambah rumah sakit' }}</h1>
        </div>
        <p>Kolom bertanda <strong>*</strong> wajib diisi.</p>
    </div>

    @if ($errors->any())
        <div class="validation-summary" role="alert"><strong>Beberapa data perlu diperiksa.</strong><p>Perbaiki kolom yang ditandai sebelum menyimpan.</p></div>
    @endif

    <form class="hospital-form" method="post" action="{{ $editing ? route('admin.hospitals.update', $hospital) : route('admin.hospitals.store') }}">
        @csrf
        @if ($editing) @method('PUT') @endif

        <section class="form-section">
            <div class="form-section-title"><span>01</span><div><h2>Identitas</h2><p>Informasi utama untuk mengenali rumah sakit.</p></div></div>
            <div class="form-fields">
                <label class="field field-wide @error('name') has-error @enderror">
                    <span>Nama rumah sakit *</span><input name="name" maxlength="150" value="{{ old('name', $hospital->name) }}" required>
                    @error('name')<small>{{ $message }}</small>@enderror
                </label>
                <label class="field @error('code') has-error @enderror">
                    <span>Kode rumah sakit *</span><input name="code" maxlength="20" value="{{ old('code', $hospital->code) }}" placeholder="RS-001" required>
                    @error('code')<small>{{ $message }}</small>@enderror
                </label>
                <label class="field @error('class') has-error @enderror">
                    <span>Kelas *</span><select name="class" required><option value="">Pilih kelas</option>
                    @foreach (['A', 'B', 'C', 'D'] as $class)<option value="{{ $class }}" @selected(old('class', $hospital->class) === $class)>Kelas {{ $class }}</option>@endforeach</select>
                    @error('class')<small>{{ $message }}</small>@enderror
                </label>
                <label class="field @error('ownership') has-error @enderror">
                    <span>Kepemilikan *</span><select name="ownership" required><option value="">Pilih pengelola</option>
                    @foreach (['Pemerintah', 'BUMN', 'Swasta'] as $ownership)<option value="{{ $ownership }}" @selected(old('ownership', $hospital->ownership) === $ownership)>{{ $ownership }}</option>@endforeach</select>
                    @error('ownership')<small>{{ $message }}</small>@enderror
                </label>
                <label class="check-field field-wide">
                    <input type="checkbox" name="is_emergency" value="1" @checked(old('is_emergency', $hospital->is_emergency))>
                    <span><strong>Memiliki IGD 24 jam</strong><small>Tampilkan status layanan darurat pada halaman publik.</small></span>
                </label>
            </div>
        </section>

        <section class="form-section">
            <div class="form-section-title"><span>02</span><div><h2>Kontak & lokasi</h2><p>Data untuk petunjuk arah dan komunikasi.</p></div></div>
            <div class="form-fields">
                <label class="field @error('phone') has-error @enderror"><span>Telepon utama *</span><input name="phone" maxlength="30" value="{{ old('phone', $hospital->phone) }}" required>@error('phone')<small>{{ $message }}</small>@enderror</label>
                <label class="field"><span>Telepon IGD</span><input name="emergency_phone" maxlength="30" value="{{ old('emergency_phone', $hospital->emergency_phone) }}"></label>
                <label class="field field-wide @error('address') has-error @enderror"><span>Alamat *</span><textarea name="address" rows="3" required>{{ old('address', $hospital->address) }}</textarea>@error('address')<small>{{ $message }}</small>@enderror</label>
                <label class="field @error('city') has-error @enderror"><span>Kota/kabupaten *</span><input name="city" maxlength="100" value="{{ old('city', $hospital->city) }}" required>@error('city')<small>{{ $message }}</small>@enderror</label>
                <div class="coordinate-pair field-wide">
                    <label class="field @error('latitude') has-error @enderror"><span>Latitude *</span><input type="number" step="any" name="latitude" value="{{ old('latitude', $hospital->latitude) }}" required>@error('latitude')<small>{{ $message }}</small>@enderror</label>
                    <label class="field @error('longitude') has-error @enderror"><span>Longitude *</span><input type="number" step="any" name="longitude" value="{{ old('longitude', $hospital->longitude) }}" required>@error('longitude')<small>{{ $message }}</small>@enderror</label>
                </div>
            </div>
        </section>

        <section class="form-section">
            <div class="form-section-title"><span>03</span><div><h2>Fasilitas</h2><p>Pilih layanan penting yang tersedia.</p></div></div>
            <div class="form-fields">
                <div class="facility-checks field-wide">
                    @foreach ($facilities as $facility)
                        <label><input type="checkbox" name="facilities[]" value="{{ $facility->id }}" @checked(in_array($facility->id, old('facilities', $selectedFacilities)))><span>{{ $facility->name }}</span></label>
                    @endforeach
                </div>
                @error('facilities.*')<small class="field-error field-wide">{{ $message }}</small>@enderror
                <label class="field field-wide @error('description') has-error @enderror"><span>Deskripsi singkat</span><textarea name="description" rows="5" maxlength="1000" placeholder="Tuliskan profil dan layanan unggulan secara ringkas.">{{ old('description', $hospital->description) }}</textarea>@error('description')<small>{{ $message }}</small>@enderror</label>
            </div>
        </section>

        <div class="form-actions">
            <a class="button button-quiet" href="{{ route('admin.index') }}">Batal</a>
            <button class="button button-primary" type="submit">{{ $editing ? 'Simpan perubahan' : 'Simpan rumah sakit' }}</button>
        </div>
    </form>
</section>
@endsection
