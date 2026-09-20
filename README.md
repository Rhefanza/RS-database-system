# PuskesmasKu

Prototipe Laravel 12 untuk simulasi informasi dan pengambilan antrean puskesmas di Kota Surabaya. Seluruh fasilitas, akun, dokter, jadwal, dan transaksi antrean merupakan data dummy.

## Fitur publik dan masyarakat

- Beranda memuat pencarian langsung dan peta Surabaya berbasis Leaflet serta OpenStreetMap.
- Peta menampilkan 31 titik puskesmas dummy, satu pada setiap kecamatan, beserta antrean aktif dan jarak dari lokasi pengguna.
- Halaman rekomendasi mengambil 10 faskes terdekat lalu menampilkan lima dengan antrean aktif lebih sedikit.
- Detail rekomendasi memuat kecamatan, alamat, layanan, dokter dummy, jadwal, jarak, dan antrean aktif.
- Masyarakat tetap dapat mengaktifkan akun, mengambil nomor antrean, melihat antrean sendiri, dan membatalkannya.

## Role

- `MASYARAKAT`: mengambil dan mengelola antrean milik sendiri.
- `PETUGAS`: mengelola jadwal dan antrean hanya pada puskesmas tempatnya bertugas.
- `ADMIN`: mengelola kecamatan, masyarakat, akun, puskesmas, layanan, relasi layanan, dan dokter.

## Struktur database

Sembilan tabel bisnis digunakan: `kecamatan`, `masyarakat`, `akun`, `puskesmas`, `layanan`, `puskesmas_layanan`, `jadwal`, `dokter`, dan `antrean`. Detail relasi tersedia di `DATABASE.md`.

## Menjalankan aplikasi

```bash
composer install
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

Buka `http://127.0.0.1:8000`.

## Akun demo

| Role | Email | Password |
|---|---|---|
| Admin | `admin@puskesmas.test` | `password` |
| Petugas | `petugas@puskesmas.test` | `password` |
| Masyarakat | `masyarakat1@puskesmas.test` | `password` |

NIK dummy yang belum diaktivasi: `3578010101900004`.

Seeder membuat 31 kecamatan, 31 puskesmas, 80 masyarakat, 6 layanan, 93 relasi layanan, 93 dokter, 93 jadwal hari ini, dan antrean dummy yang tersebar pada seluruh puskesmas.

## Peta

Antarmuka memakai Leaflet 1.9.4 dan tile standar OpenStreetMap. Atribusi OpenStreetMap selalu ditampilkan pada peta. Jarak dihitung dengan rumus Haversine di perangkat pengguna dan lokasi pengguna tidak disimpan ke database.

## Simulator antrean

```bash
php artisan queue:simulate --min=2 --max=9
```

Endpoint `GET /api/antrean-live` diperiksa halaman setiap lima detik. Simulator memproses data dummy dan membatasi antrean dummy hari berjalan hingga 300 record agar data demonstrasi tidak tumbuh tanpa batas.

## Batas pengembangan saat ini

Versi ini belum mencakup estimasi waktu tunggu, loket, sesi antrean, riwayat snapshot, data medis, atau integrasi Mobile JKN. Nomor antrean hanya berlaku dalam lingkungan simulasi.
