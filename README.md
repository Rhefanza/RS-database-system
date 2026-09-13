# Rujuk. — Laravel 12

Rujuk. adalah aplikasi direktori rumah sakit untuk proyek UTS Basis Data. Masyarakat dapat mencari dan membandingkan rumah sakit, sedangkan pengelola dapat mengelola datanya melalui CRUD.

## Fitur yang tersedia

- Pencarian publik berdasarkan nama, alamat, atau kota.
- Filter kelas dan kepemilikan rumah sakit.
- Pengurutan jarak terdekat memakai lokasi peramban dan rumus Haversine.
- Detail rumah sakit, fasilitas, kontak, dan tautan Google Maps.
- Login dan logout pengelola.
- Tambah, lihat, ubah, dan hapus data rumah sakit.
- Relasi many-to-many rumah sakit dengan fasilitas.
- Validasi Form Request, route model binding, CSRF, session regeneration, dan middleware autentikasi.
- Seeder enam rumah sakit demonstrasi dan sembilan fasilitas.
- Feature test untuk fungsi publik, autentikasi, validasi, dan CRUD.

## Teknologi

- Laravel 12
- PHP 8.2 atau lebih baru
- MySQL/MariaDB
- Blade, CSS, dan JavaScript native
- Tidak membutuhkan proses build frontend

## Struktur database

```mermaid
erDiagram
    USERS {
        bigint id PK
        varchar name
        varchar email UK
        varchar password
    }
    HOSPITALS {
        bigint id PK
        varchar code UK
        varchar name
        enum class
        enum ownership
        decimal latitude
        decimal longitude
        boolean is_emergency
    }
    FACILITIES {
        bigint id PK
        varchar name UK
    }
    HOSPITAL_FACILITIES {
        bigint hospital_id PK,FK
        bigint facility_id PK,FK
    }
    HOSPITALS ||--o{ HOSPITAL_FACILITIES : memiliki
    FACILITIES ||--o{ HOSPITAL_FACILITIES : tersedia_di
```

## Instalasi dengan Laragon

Ekstrak proyek ke:

```text
D:\semester 5\basdat 2\proyek uts-uas
```

Nyalakan Apache dan MySQL di Laragon. Buka Terminal Laragon, lalu jalankan:

```bash
cd /d "D:\semester 5\basdat 2\proyek uts-uas"
copy .env.example .env
composer install
php artisan key:generate
```

Buat database kosong bernama `rujuk_uts` melalui phpMyAdmin. Setelah itu jalankan:

```bash
php artisan migrate:fresh --seed
php artisan serve
```

Buka `http://127.0.0.1:8000`.

## Akun demo

```text
Email: admin@rujuk.test
Password: password
```

## Menjalankan pengujian

```bash
php artisan test
```

Pengujian mencakup halaman publik, detail dan 404, filter, proteksi halaman admin, login, logout, password salah, create, update, sinkronisasi fasilitas, delete, cascade pivot, serta validasi.

## Batas tahap UTS

Data rumah sakit masih berupa data demonstrasi. Jarak yang ditampilkan adalah jarak garis lurus, bukan waktu tempuh jalan. Dashboard analitik, peta interaktif penuh, dan sistem rekomendasi berbobot disiapkan sebagai pengembangan UAS.
