# Gambar PuskesmasKu

Semua aset gambar lokal website disimpan dalam folder `public/images`.

| Folder | Penggunaan |
|---|---|
| `logo/` | Logo pada header/footer dan ikon browser. Website menggunakan `logo-puskesmasku.png`; `favicon.ico` disimpan sebagai alternatif. |
| `beranda/` | Gambar utama beranda (`antre.png`). |
| `langkah/` | Tiga kartu cara kerja: cari puskesmas, periksa detail layanan, dan ambil antrean. |
| `rekomendasi/` | Gambar pengantar fitur rekomendasi. |
| `layanan/` | Foto poli/layanan: imunisasi, KIA, laboratorium, poli gigi, poli gizi, dan poli umum. |
| `arsip/` | Dua gambar beranda lama yang saat ini tidak dirujuk kode; dipertahankan sebagai cadangan. |

Gunakan `asset('images/<folder>/<nama-file>')` pada Blade. Gambar layanan dipilih oleh komponen `resources/views/components/service-art.blade.php` berdasarkan slug nama layanan, sehingga nama file layanan harus mengikuti pola yang sudah ada, misalnya `poli-umum.png`.

CSS dan JavaScript berada di `public/assets`. Tile peta OpenStreetMap dimuat dari penyedia peta dan bukan aset gambar lokal.
