# Struktur Database PuskesmasKu

## Enam tabel bisnis

| Tabel | Isi utama | Kunci utama |
|---|---|---|
| `kecamatan` | Nama wilayah | `kecamatan_id` |
| `akun` | NIK, nama, alamat, telepon, email, password_hash, role, status_data, status_akun, puskesmas_id | `akun_id` |
| `puskesmas` | Kecamatan, nama, alamat, koordinat, telepon, status | `puskesmas_id` |
| `layanan` | Nama poli, deskripsi, status | `layanan_id` |
| `jadwal` | Puskesmas, layanan, hari, jam buka/tutup, kapasitas, keterangan dokter dan spesialisasi, status | `jadwal_id` |
| `antrean` | Akun masyarakat, jadwal, tanggal, nomor, status antrean | `antrean_id` |

Tabel migrations, sessions, cache, dan cache_locks adalah penyimpanan teknis Laravel.

## Relasi

- kecamatan 1 → banyak puskesmas, melalui puskesmas.kecamatan_id.
- puskesmas 1 → banyak akun PETUGAS; puskesmas_id masyarakat/admin kosong.
- puskesmas 1 → banyak jadwal, melalui jadwal.puskesmas_id.
- layanan 1 → banyak jadwal, melalui jadwal.layanan_id.
- akun MASYARAKAT 1 → banyak antrean, melalui antrean.akun_id.
- jadwal 1 → banyak antrean, melalui antrean.jadwal_id.

## Aturan

- Data masyarakat disiapkan admin sebagai baris akun role MASYARAKAT, password/email kosong dan status_akun NONAKTIF. NIK unik.
- Aktivasi hanya untuk NIK masyarakat berstatus_data AKTIF yang belum memiliki password. Aktivasi memperbarui baris akun yang sama dalam transaksi; password disimpan sebagai hash.
- Login mensyaratkan status_akun dan status_data AKTIF. Petugas harus terhubung ke puskesmas aktif.
- Petugas hanya mengubah jadwal dan antrean puskesmasnya. Puskesmas jadwal ditetapkan dari akun petugas.
- Kombinasi puskesmas, layanan, dan hari pada jadwal unik. Nama dokter hanya keterangan jadwal, bukan master terpisah; perubahan pada satu jadwal tidak otomatis mengubah jadwal lain.
- Layanan puskesmas diturunkan dari jadwal. Layanan tanpa jadwal belum tampil sebagai layanan puskesmas.
- Nomor antrean unik per jadwal dan tanggal. Kapasitas dan benturan waktu diperiksa dalam transaksi dengan penguncian akun lalu jadwal.
- WAITING, CALLED, SERVING memakai kapasitas. COMPLETED dan CANCELLED adalah riwayat.
- Antrean WAITING, CALLED, dan SERVING dihapus otomatis setelah 24 jam dari tanggal layanan + jam tutup jadwal (Asia/Jakarta). Antrean masa depan, COMPLETED, dan CANCELLED tetap disimpan. Pembersihan berjalan setiap menit melalui scheduler, ketika halaman/API diakses, dan setiap putaran simulator. Jalankan `php artisan schedule:work` untuk pembersihan saat website tidak diakses.
- Masyarakat dapat menghapus antrean miliknya berstatus WAITING setelah konfirmasi. Penghapusan membebaskan kapasitas; CALLED, SERVING, dan COMPLETED tidak dapat dihapus oleh masyarakat. Antrean CANCELLED tidak ditampilkan pada halaman masyarakat; endpoint hapus tetap menerima CANCELLED untuk kompatibilitas data lama.
- Alur status: WAITING → CALLED → SERVING → COMPLETED; petugas dapat membatalkan status aktif. Masyarakat hanya mengambil atau menghapus antrean, tanpa aksi pembatalan.
- Jadwal dengan antrean aktif tidak dapat dipindah layanan/hari/jam. Jadwal, akun, puskesmas, dan layanan yang masih dirujuk tidak dihapus; nonaktifkan bila perlu.

## Migrasi dari sembilan tabel

Cadangkan database dan hentikan simulator, lalu jalankan `php artisan migrate`.
Migrasi 2026_10_02_000007 menggabungkan masyarakat ke akun, mengganti NIK antrean dengan akun_id, dan menambahkan puskesmas_id/layanan_id langsung pada jadwal.
Password lama dan ID akun/jadwal/antrean dipertahankan. Semua nama dokter lama disalin sebagai keterangan; dokter nonaktif diberi label nonaktif. Relasi layanan tanpa jadwal disimpan sebagai draf jadwal NONAKTIF untuk ditinjau petugas sebelum diaktifkan.
Setelah pemindahan selesai, tabel masyarakat, puskesmas_layanan, dan dokter dihapus. Model dan menu terpisahnya juga dihapus. Migration lama tetap dipertahankan sebagai riwayat dan agar instalasi baru dapat berjalan berurutan hingga skema terbaru.
Pemulihan ke struktur sebelumnya harus menggunakan cadangan SQL dan versi kode sebelumnya, bukan migrate:rollback.

Lokasi perangkat tidak disimpan. Peta dan rekomendasi memakai koordinat puskesmas dan lokasi sementara di browser.
