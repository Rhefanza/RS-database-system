# Sistem Informasi Antrean Puskesmas

Proyek Laravel 12 untuk UTS Basis Data. Fokus aplikasi adalah CRUD, autentikasi, pembagian tiga role, relasi MySQL, jadwal, kapasitas, dan transaksi antrean.

## Role dan fitur

### Masyarakat

- Aktivasi akun memakai NIK dummy yang sudah didaftarkan Dinkes.
- Login dan mengubah profil sendiri.
- Melihat puskesmas, layanan, jadwal, dan sisa kapasitas.
- Mengambil, melihat, membatalkan, serta menghapus antrean sendiri yang sudah dibatalkan.

### Petugas

- Terhubung langsung ke satu puskesmas melalui `akun.puskesmas_id`.
- CRUD jadwal dan kapasitas layanan puskesmasnya.
- CRUD antrean dan mengubah status antrean secara manual.
- Tidak dapat membuka data master Dinkes atau data puskesmas lain.

### Admin / Dinkes

- CRUD masyarakat, petugas, puskesmas, layanan, dan relasi puskesmas–layanan.
- Mengaktifkan atau menonaktifkan akun.
- Melihat dan mengelola jadwal serta antrean.

## Struktur database UTS

Tepat tujuh tabel bisnis digunakan:

1. `masyarakat`
2. `akun`
3. `puskesmas`
4. `layanan`
5. `puskesmas_layanan`
6. `jadwal`
7. `antrean`

Relasi utama:

```text
masyarakat 1 ── 0..1 akun
puskesmas  1 ── N akun (role PETUGAS)
puskesmas  N ── M layanan (melalui puskesmas_layanan)
puskesmas_layanan 1 ── N jadwal
jadwal 1 ── N antrean
masyarakat 1 ── N antrean
```

Schema UTS tidak memakai kecamatan, fasilitas, penugasan terpisah, jadwal khusus, loket, sesi antrean, snapshot, lokasi tersimpan, map, GPS, rekomendasi, analitik, data warehouse, atau simulasi real-time.

## Menjalankan aplikasi

1. Buat database MySQL kosong bernama `rujuk_uts`.
2. Salin `.env.example` menjadi `.env` dan sesuaikan koneksi MySQL bila perlu.
3. Jalankan:

```bash
composer install
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

Buka `http://127.0.0.1:8000`.

## Akun dan data demo

Semua data bersifat synthetic dan tidak merepresentasikan orang atau fasilitas nyata.

| Role | Email | Password |
|---|---|---|
| Admin | `admin@puskesmas.test` | `password` |
| Petugas | `petugas@puskesmas.test` | `password` |
| Masyarakat | `masyarakat1@puskesmas.test` | `password` |

NIK dummy yang belum diaktivasi: `3578010101900004`.

Seeder menghasilkan 15 masyarakat, 3 puskesmas, 5 layanan, 9 relasi layanan, 9 jadwal untuk hari saat seeder dijalankan, dan 8 antrean.

## Pengujian

```bash
php artisan test
```

Test mencakup tujuh tabel bisnis, aktivasi NIK, login, pembatasan role, CRUD data master, pembatasan puskesmas petugas, kapasitas antrean, pembatalan, penghapusan, dan konsistensi data dummy.
