# Struktur Database PuskesmasKu

## Entitas

| Tabel | Fungsi | Kunci utama |
|---|---|---|
| `kecamatan` | Wilayah administratif Surabaya | `kecamatan_id` |
| `masyarakat` | Identitas dummy pemilik antrean | `nik` |
| `akun` | Autentikasi masyarakat, petugas, dan admin | `akun_id` |
| `puskesmas` | Identitas, alamat, dan koordinat faskes | `puskesmas_id` |
| `layanan` | Master jenis poli atau layanan | `layanan_id` |
| `puskesmas_layanan` | Penghubung N:M puskesmas dan layanan | `puskesmas_layanan_id` |
| `jadwal` | Hari, jam, dan kapasitas layanan | `jadwal_id` |
| `dokter` | Dokter dummy yang praktik pada layanan puskesmas | `dokter_id` |
| `antrean` | Transaksi pengambilan nomor antrean | `antrean_id` |

## Relasi

```text
kecamatan 1 ── N puskesmas
masyarakat 1 ── 0..1 akun
puskesmas 1 ── N akun PETUGAS
puskesmas N ── M layanan melalui puskesmas_layanan
puskesmas_layanan 1 ── N jadwal
puskesmas_layanan 1 ── N dokter
jadwal 1 ── N antrean
masyarakat 1 ── N antrean
```

## Aturan integritas

- Nama kecamatan, nama puskesmas, nama layanan, dan email akun harus unik.
- Kombinasi puskesmas dan layanan hanya boleh muncul sekali.
- Kombinasi layanan puskesmas dan hari hanya boleh memiliki satu jadwal pada versi saat ini.
- Nama dokter harus unik di dalam satu layanan puskesmas.
- Nomor antrean unik untuk kombinasi jadwal dan tanggal.
- Satu NIK hanya boleh mengambil satu antrean pada jadwal dan tanggal yang sama.
- Status antrean: `WAITING`, `CALLED`, `SERVING`, `COMPLETED`, atau `CANCELLED`.

Lokasi pengguna tidak disimpan. Jarak dihitung pada browser dari koordinat puskesmas dan lokasi sementara yang diberikan pengguna.
