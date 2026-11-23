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
- `PETUGAS`: ditetapkan admin pada tepat satu puskesmas dan hanya dapat mengelola jadwal serta antrean puskesmas tersebut. Petugas juga mendaftarkan dan mengelola data masyarakat (NIK) pada menu Data masyarakat.
- `ADMIN`: mengelola kecamatan, akun petugas, puskesmas, dan layanan. Petugas mengelola jadwal beserta keterangan dokter dan antrean puskesmasnya.

## Struktur database

Enam tabel bisnis digunakan: `kecamatan`, `akun`, `puskesmas`, `layanan`, `jadwal`, dan `antrean`. Data masyarakat berada di `akun`; layanan puskesmas dan keterangan dokter berada di `jadwal`. Detail relasi tersedia di `DATABASE.md`.

Tabel `migrations`, `sessions`, `cache`, dan `cache_locks` adalah tabel teknis Laravel dan tidak dihitung sebagai tabel bisnis.

## Menjalankan aplikasi

Aktifkan MySQL pada Laragon, buka terminal di folder proyek, lalu untuk database yang sudah berisi data jalankan:

```bash
php artisan optimize:clear
php artisan migrate
php artisan serve
```

`migrate` hanya menjalankan migrasi yang belum diterapkan. Bila migrasi penyederhanaan sudah berstatus `Ran`, hasilnya `Nothing to migrate` dan database tidak dibuat ulang. Hentikan simulator dengan Ctrl+C sebelum menjalankan migrasi yang mengubah struktur tabel. Cadangkan database melalui Export SQL HeidiSQL sebelum memigrasikan database lama.

Untuk instalasi baru saja: jalankan `composer install`, salin `.env.example` menjadi `.env`, isi koneksi database, lalu jalankan `php artisan key:generate`, `php artisan migrate`, dan `php artisan db:seed`. Seeder mengisi data simulasi dan dapat memperbarui akun/jadwal contoh; tidak perlu dijalankan lagi pada database yang sudah digunakan.

Pada terminal kedua, simulator opsional dapat dijalankan dengan:

```bash
php artisan queue:simulate --min=2 --max=9
```

Jangan gunakan `php artisan migrate:fresh --seed` untuk pembaruan database yang sudah berisi data: perintah tersebut menghapus seluruh tabel sebelum membuatnya ulang. Migrasi penggabungan tidak mendukung `migrate:rollback`; pemulihan struktur lama memakai cadangan SQL dan kode versi sebelumnya.

Buka `http://127.0.0.1:8000`.

## Akun demo

| Role | Email | Password |
|---|---|---|
| Admin | `admin@puskesmas.test` | `password` |
| Petugas Asemrowo | `petugas_asemrowo@test` | `password` |
| Masyarakat | `masyarakat1@puskesmas.test` | `password` |

NIK dummy yang belum diaktivasi: `3578010101900004`.

Pada instalasi baru, seeder membuat 31 kecamatan, 31 puskesmas, 112 akun (80 masyarakat, 31 petugas, 1 admin), 6 layanan, 93 jadwal hari ini dengan keterangan dokter, dan antrean dummy yang tersebar pada seluruh puskesmas.

## Peta

Semua gambar lokal berada di `public/images`, dibagi ke folder `logo`, `beranda`, `langkah`, `rekomendasi`, `layanan`, dan `arsip`. Panduan penggunaan tersedia di `public/images/README.md`. CSS dan JavaScript tetap berada di `public/assets`.

Antarmuka memakai Leaflet 1.9.4 dan tile standar OpenStreetMap. Atribusi OpenStreetMap selalu ditampilkan pada peta. Jarak dihitung dengan rumus Haversine di perangkat pengguna dan lokasi pengguna tidak disimpan ke database.

## Simulator antrean

```bash
php artisan queue:simulate --min=2 --max=9
```

Endpoint `GET /api/antrean-live` diperiksa halaman setiap lima detik. Hanya status `WAITING`, `CALLED`, dan `SERVING` yang ditampilkan dan dihitung sebagai antrean aktif. Data `COMPLETED` dan `CANCELLED` tetap tersimpan sebagai riwayat, tetapi tidak memakai kapasitas. Simulator memproses data dummy dan membatasi antrean dummy hari berjalan hingga 300 record agar data demonstrasi tidak tumbuh tanpa batas.

## Data warehouse dan dashboard analitik

Dashboard analitik membaca data warehouse `puskesmasku_dw`, bukan database aplikasi. Data warehouse memakai star schema: `fact_antrean` dengan dimensi `dim_waktu`, `dim_puskesmas` (termasuk kecamatan), `dim_layanan`, dan `dim_jadwal`.

- **Admin** (`/admin/analitik`): seluruh kecamatan, dengan drill-down kecamatan → puskesmas.
- **Petugas** (`/pengelola/analitik`): otomatis dikunci ke puskesmas tempat petugas bertugas.

Isi dashboard: KPI (total antrean, pasien unik, persen selesai dalam liquid chart, persen batal, utilisasi kapasitas), tren per status (roll-up harian/mingguan/bulanan), antrean per kecamatan, antrean per layanan bertumpuk menurut status, kapasitas vs utilisasi (dual-axes), hari × sesi, komposisi status (donut), serta tabel utilisasi tertinggi dan terendah. Filter tahun, bulan, kecamatan, dan layanan dikirim ke endpoint JSON (`/admin/analitik/data`, `/pengelola/analitik/data`) sehingga grafik diperbarui tanpa memuat ulang halaman. Query OLAP berada di `app/Support/WarehouseAnalytics.php`.

Utilisasi dihitung dari kapasitas per slot jadwal × tanggal (bukan dijumlah per baris antrean) dan hanya mencakup slot yang memiliki antrean. Data warehouse menyimpan status akhir antrean saja, sehingga dashboard tidak menampilkan waktu tunggu.

Data warehouse harus berada di server MySQL yang sama dengan database aplikasi. Nama database diatur lewat `DW_DATABASE` di `.env` (bawaan `puskesmasku_dw`). Isi atau perbarui data warehouse dengan:

```bash
php artisan dw:etl --full
```

Perintah tersebut membuat database dan tabel bila belum ada, lalu memuat seluruh antrean. Tanpa `--full`, ETL hanya memuat antrean baru dan antrean yang berubah dalam dua hari terakhir. Scheduler menjalankan `dw:etl` setiap jam (aktif bila `php artisan schedule:work` berjalan) sehingga antrean aktif sudah tercatat sebelum dibersihkan setelah 24 jam. Riwayat di data warehouse tetap tersimpan walaupun antrean sumbernya sudah terhapus.

Grafik memakai GPT-Vis 1.3.2 (`@antv/gpt-vis`, lisensi MIT) yang disimpan lokal di `public/assets/vendor` sehingga dashboard tidak bergantung pada CDN.

## Batas pengembangan saat ini

Versi ini belum mencakup estimasi waktu tunggu, loket, sesi antrean, riwayat snapshot, data medis, atau integrasi Mobile JKN. Nomor antrean hanya berlaku dalam lingkungan simulasi.
