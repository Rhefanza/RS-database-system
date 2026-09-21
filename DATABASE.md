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
puskesmas 1 ── N akun PETUGAS (setiap petugas tepat pada satu puskesmas)
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
- Satu NIK hanya boleh memiliki satu antrean aktif pada jam yang sama. Jadwal poli yang waktunya bertabrakan akan ditolak, termasuk jika berada di puskesmas berbeda.
- Hanya `WAITING`, `CALLED`, dan `SERVING` yang dihitung sebagai antrean aktif dan memakai kapasitas. `COMPLETED` dan `CANCELLED` tetap tersimpan sebagai riwayat, tetapi tidak tampil pada daftar operasional.
- Kapasitas dan nomor antrean dibuat di dalam transaksi database agar dua permintaan yang datang hampir bersamaan tidak melewati kapasitas.
- Status antrean: `WAITING`, `CALLED`, `SERVING`, `COMPLETED`, atau `CANCELLED`.
- Perubahan status mengikuti urutan `WAITING` → `CALLED` → `SERVING` → `COMPLETED`; status aktif juga dapat diubah menjadi `CANCELLED`.
- Email petugas dibuat otomatis dari puskesmas, misalnya `petugas_asemrowo@test`. Akun petugas tidak dapat mengakses sistem pengelola tanpa puskesmas aktif.
- Akun admin tidak ditampilkan pada pengelolaan akun petugas dan tidak dapat dihapus melalui menu tersebut.

Lokasi pengguna tidak disimpan. Jarak dihitung pada browser dari koordinat puskesmas dan lokasi sementara yang diberikan pengguna.
