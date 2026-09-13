# Struktur dan Relasi Database Rujuk.

Rancangan ini mengadaptasi sistem antrean puskesmas menjadi sistem informasi dan antrean rumah sakit. Istilah `puskesmas` diterjemahkan menjadi `hospital`, sedangkan `layanan` menjadi `service`. Fasilitas penunjang tetap menjadi entitas terpisah.

## Relasi utama

| Entitas | Relasi | Entitas tujuan | Implementasi |
|---|---|---|---|
| District | 1:N | Hospital | `hospitals.district_id` |
| Hospital | M:N | Service | `hospital_services` dengan durasi awal dan status ketersediaan |
| HospitalService | 1:N | ServiceSchedule | Jadwal rutin mingguan |
| HospitalService | 1:N | SpecialServiceSchedule | Perubahan jadwal pada tanggal tertentu |
| HospitalService | 1:N | ServiceDesk | Loket atau ruang pelayanan |
| HospitalService | 1:N | QueueSession | Sesi antrean per tanggal/waktu |
| User | 1:N | QueueSession | Petugas pembuka dan penutup sesi |
| QueueSession | 1:N | Queue | Nomor antrean unik dalam satu sesi |
| ServiceDesk | 1:N | Queue | Loket pelayanan; boleh kosong ketika masih menunggu |
| HospitalService | 1:N | QueueSnapshot | Rekaman kondisi antrean dari waktu ke waktu |
| User | M:N | Hospital | `staff_assignments` menyimpan riwayat penugasan |
| User | 1:N | SavedLocation | Lokasi favorit pengguna; GPS sementara tidak disimpan |
| Hospital | M:N | Facility | `hospital_facilities` untuk sarana penunjang |

## Aturan integritas penting

- Kombinasi `hospital_id` dan `service_id` pada `hospital_services` harus unik.
- Kombinasi `queue_session_id` dan `queue_number` pada `queues` harus unik.
- Jadwal khusus hanya boleh satu untuk setiap layanan rumah sakit pada satu tanggal.
- Nama loket unik di dalam satu layanan rumah sakit.
- Antrean terhapus ketika sesi induknya dihapus.
- Loket pada antrean menjadi `NULL` jika loket dihapus.
- Akun penutup sesi boleh `NULL` selama sesi masih berlangsung atau jika akun penutup dihapus.
- Penghapusan rumah sakit membersihkan relasi layanan dan data operasional turunannya, tetapi riwayat penugasan petugas menggunakan pembatasan penghapusan.

## Perbedaan layanan dan fasilitas

- `services`: kegiatan medis yang memiliki jadwal, loket, dan antrean, misalnya IGD, rawat jalan, atau hemodialisis.
- `facilities`: sarana pendukung yang dimiliki rumah sakit, misalnya ICU, ambulans, CT Scan, dan ruang operasi.

Implementasi fisik terdapat dalam migration `2026_09_13_000003` sampai `2026_09_13_000005`, sedangkan relasi aplikasinya berada pada model Eloquent di `app/Models`.
