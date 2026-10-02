# PuskesmasKu

Prototipe Laravel 12 untuk simulasi informasi dan pengambilan antrean puskesmas di Kota Surabaya. Seluruh fasilitas, akun, dokter, jadwal, dan transaksi antrean merupakan data dummy.

## Fitur publik dan masyarakat

- Beranda memuat pencarian langsung dan peta Surabaya berbasis Leaflet serta OpenStreetMap.
- Peta menampilkan 31 titik puskesmas dummy, satu pada setiap kecamatan, beserta antrean aktif dan jarak dari lokasi pengguna.
- Halaman rekomendasi mengambil 10 faskes terdekat lalu menampilkan lima dengan antrean aktif lebih sedikit.
- Detail rekomendasi memuat kecamatan, alamat, layanan, dokter dummy, jadwal, jarak, dan antrean aktif.
- Masyarakat tetap dapat mengaktifkan akun, mengambil nomor antrean, melihat antrean sendiri, dan membatalkannya.
- Sistem menolak pengambilan dua antrean aktif dengan jam yang bertabrakan serta mencegah kapasitas terlewati saat beberapa permintaan masuk bersamaan.

## Role

- `MASYARAKAT`: mengambil dan mengelola antrean milik sendiri.
- `PETUGAS`: ditetapkan admin pada tepat satu puskesmas; memilih layanan, mengisi keterangan dokter pada jadwal, dan mengelola antreannya.
- `ADMIN`: mengelola kecamatan, masyarakat (akun yang belum/sudah aktivasi), petugas, puskesmas, dan layanan.

## Struktur database

Enam tabel bisnis digunakan: `kecamatan`, `akun`, `puskesmas`, `layanan`, `jadwal`, dan `antrean`. Tabel teknis Laravel (`migrations`, `sessions`, `cache`, `cache_locks`) terpisah dari enam tabel bisnis. Detail relasi tersedia di `DATABASE.md`.

## Menjalankan aplikasi

```bash
composer install
php artisan key:generate
php artisan migrate
# Hanya untuk database baru/kosong:
php artisan db:seed
php artisan serve
```

Gunakan PHP 8.2+ (di Laragon pilih PHP 8.3), aktifkan MySQL, dan sesuaikan `.env`. Jalankan `key:generate` hanya pada instalasi baru; jangan mengganti APP_KEY instalasi yang sudah digunakan.

Untuk database lama, cadangkan database, hentikan simulator, lalu jalankan `php artisan migrate` tanpa seeding ulang. Migrasi `2026_10_02_000007` memindahkan data lama ke enam tabel dan mempertahankan ID akun aktif, jadwal, serta antrean. Pemulihan menggunakan cadangan SQL karena migrasi penggabungan ini tidak mendukung rollback otomatis.

Buka `http://127.0.0.1:8000`.

## Akun akses

| Role | Email | Password |
|---|---|---|
| Admin | `admin@puskesmas.test` | `password` |
| Petugas Asemrowo | `petugas_asemrowo@test` | `password` |
| Masyarakat | `masyarakat1@puskesmas.test` | `password` |

NIK dummy yang belum diaktivasi: `3578010101900004`.

Seeder membuat 31 kecamatan, 31 puskesmas, 80 akun masyarakat, 6 layanan, 93 jadwal hari ini beserta keterangan dokter, dan antrean dummy yang tersebar pada seluruh puskesmas.

## Peta

Antarmuka memakai Leaflet 1.9.4 dan tile standar OpenStreetMap. Atribusi OpenStreetMap selalu ditampilkan pada peta. Jarak dihitung dengan rumus Haversine di perangkat pengguna dan lokasi pengguna tidak disimpan ke database.

## Simulator antrean

```bash
php artisan queue:simulate --min=2 --max=9
```

Endpoint `GET /api/antrean-live` diperiksa halaman setiap lima detik. Hanya status `WAITING`, `CALLED`, dan `SERVING` yang ditampilkan dan dihitung sebagai antrean aktif. Data `COMPLETED` dan `CANCELLED` tetap tersimpan sebagai riwayat, tetapi tidak memakai kapasitas. Simulator memproses data dummy dan membatasi antrean dummy hari berjalan hingga 300 record agar data simulasi tidak tumbuh tanpa batas.

## Batas pengembangan saat ini

Versi ini belum mencakup estimasi waktu tunggu, loket, sesi antrean, riwayat snapshot, data medis, atau integrasi Mobile JKN. Nomor antrean hanya berlaku dalam lingkungan simulasi.
