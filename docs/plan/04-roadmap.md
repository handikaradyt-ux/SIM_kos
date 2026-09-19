# Roadmap implementasi

## Urutan dan kriteria selesai

Urutan mengikuti dependensi data, bukan urutan menu. Setiap task dimulai dengan membaca aturan terkait, diakhiri dengan verifikasi dan catatan progres. Estimasi total 10–15 hari kerja mahasiswa dengan bantuan AI, termasuk 2 hari cadangan; ini perkiraan yang perlu disesuaikan kemampuan dan waktu tersedia, bukan deadline kampus atau jaminan durasi AI.

Definition of done setiap task: acceptance criteria terpenuhi, akses server benar, validasi/alur gagal ditangani, mutasi penting memiliki audit, tes relevan lulus, UI diperiksa jika berubah, dan dokumen/progres diperbarui dengan bukti. Jangan menyatakan tes lulus bila tidak dijalankan. Commit lokal setelah task stabil; push GitHub dilakukan saat penyiapan penyerahan sesuai otorisasi pengguna.

## Fase 1 Fondasi dan akses — perkiraan 2 hari

| Task | Dependensi | Pekerjaan dan perkiraan file | Acceptance criteria dan verifikasi |
|---|---|---|---|
| T01 | Tidak ada | Periksa environment; bootstrap Laravel dalam root workspace tanpa menimpa docs; .env.example, dependency/lockfile, README | PHP/Composer/MySQL/Node sesuai; aplikasi tampil; koneksi MySQL berhasil; test skeleton dan build aset lulus; catat versi aktual |
| T02 | T01 | Migration seluruh skema/constraint, Models/relasi, role seeder | Migrasi database kosong berhasil; PK/FK, generated unique, CHECK dan index diuji di MySQL; TC-31 |
| T03 | T02 | AuditService dan activity_logs, tes rollback | Mutasi dummy uji membuat log; gagal audit membatalkan mutasi; password disaring; TC-24/32 |
| T04 | T02,T03 | AuthController/Requests, middleware aktif/role/password sementara, Policies, auth views | Login tiga role, logout, session aktif/nonaktif, ganti password, forbidden ownership; TC-01–05 |
| T05 | T04 | Layout Blade, Bootstrap lokal, navigation, flash/errors, print.css awal | Menu role tepat, keyboard/form terbaca, build tanpa CDN; TC-29 |

Gate: fondasi tidak lanjut sebelum akses server diuji, termasuk permintaan langsung tanpa tombol UI.

## Fase 2 Data dan penempatan — perkiraan 2–3 hari

| Task | Dependensi | Pekerjaan dan perkiraan file | Acceptance criteria dan verifikasi |
|---|---|---|---|
| T06 | T03–T05 | RoomController/Request/Policy, views rooms | Semua operasi master dan arsip, status turunan, tarif unik/valid; TC-06/09 |
| T07 | T03–T05 | ResidentService, Resident/User controllers, views residents/accounts | Pembuatan akun+profil atomik, reset/nonaktif, tidak ada promosi role; CRUD/cari/detail; TC-07/05/30 |
| T08 | T06 | FacilityController/Request/Policy, views facilities | CRUD lengkap, lokasi kamar/shared, kondisi, aturan referensi; TC-08/09 |
| T09 | T06,T07 | BillingService: pembentukan periode, snapshot, preview/sync; tests | Periode pertama/terakhir/jatuh tempo tepat; unique/idempoten; kelengkapan terdeteksi; TC-12/13/33 |
| T10 | T09 | PlacementService, Controller/Policy/Request, views placements | Mulai atomik membuat invoice; kamar terisi; konflik tanpa record parsial; TC-10/11 |
| T11 | T10 | End placement action, histori penghuni/kamar | Akhiri hari ini, invoice kurang dilengkapi, kamar kosong, utang tetap; TC-14 |

T09 diuji menggunakan factory placement sebelum UI penempatan tersedia. T10 memanggil service yang sudah diuji.

Gate: dapat mendemonstrasikan CRUD tiga master dan penempatan yang mengubah status; tanpa pembayaran pun kewajiban belum bayar terlihat.

## Fase 3 Tagihan dan pembayaran — perkiraan 2 hari

| Task | Dependensi | Pekerjaan dan perkiraan file | Acceptance criteria dan verifikasi |
|---|---|---|---|
| T12 | T09–T11 | InvoiceController/Policy/views; preview dan sync UI, coverage query | Pencarian/filter/tagihan milik sendiri; sinkronisasi berulang aman; TC-12/13/04 |
| T13 | T12 | PaymentService/Controller/Request, form/daftar/detail | Jumlah sesuai tersimpan; tidak sesuai tidak memutasi; nomor unik; konkurensi aman; TC-15–17 |
| T14 | T13 | Payment void action dan receipt print view | Void beralasan, histori/bukti bertanda, bisa bayar ulang, akses cetak aman; TC-18/19 |
| T15 | T12–T14 | Resident portal ringkasan dan pembayaran | Penghuni hanya membaca data sendiri termasuk sesudah keluar; TC-04/20 |

Gate: transaksi sampai bukti cetak berhasil; salah nominal, submit ulang, dan pembayaran bersamaan tidak menyebabkan pendapatan ganda.

## Fase 4 Keluhan — perkiraan 1 hari

| Task | Dependensi | Pekerjaan dan perkiraan file | Acceptance criteria dan verifikasi |
|---|---|---|---|
| T16 | T08,T11,T15 | ComplaintService/Controller/Policy/Request, form/daftar/detail | Keluhan terhubung penempatan, pembatasan fasilitas/kepemilikan, pengajuan admin/penghuni; TC-21 |
| T17 | T16 | Timeline dan state transition dalam service/views | Cabang tindak lanjut dan tutup beralasan, log atomik, fasilitas tidak otomatis baik; TC-22/23 |

Gate: dua cabang proses keluhan dapat didemonstrasikan dan penghuni melihat timeline miliknya.

## Fase 5 Informasi — perkiraan 1–2 hari

| Task | Dependensi | Pekerjaan dan perkiraan file | Acceptance criteria dan verifikasi |
|---|---|---|---|
| T18 | T13,T14,T17 | DashboardQuery/Controller/views, Chart.js lokal | Empat KPI, dua grafik, satu tabel; hasil fixture sesuai dan coverage warning benar; TC-25/26 |
| T19 | T18 | RevenueReportQuery, BillingReportQuery, controllers/views/print | Dua laporan dengan semua kontrol, total bukan hanya halaman, cetak setara filter, log akses; TC-27/28 |
| T20 | T03,T19 | AuditController/Policy/views | Cari/filter log, identitas/label terbaca, tidak ada mutasi log; TC-24 |

Gate: angka demo cocok dengan penjumlahan fixture; layar dan cetak konsisten, termasuk sesudah void.

## Fase 6 Penyerahan — perkiraan 2–3 hari termasuk cadangan

| Task | Dependensi | Pekerjaan dan perkiraan file | Acceptance criteria dan verifikasi |
|---|---|---|---|
| T21 | T01–T20 | DemoSeeder lengkap, test suite MySQL, uji UI | Semua TC wajib terisi hasil nyata; uji dua koneksi konkurensi; kosong/restart/nonaktif tetap benar |
| T22 | T21 | README Windows, .env.example, instruksi dump/restore | Clone/setup bersih, build lokal, impor SQL ke DB baru dan hitungan sama; TC-34/35 |
| T23 | T22 | docs/system, screenshots nyata, test-results, checklist GitHub | Seluruh berkas TA tersedia, tidak ada secret; screenshot sesuai implementasi; TC-36 |
| T24 | T23 | Naskah demo dan latihan dua anggota | Demo urut sesuai dosen berhasil setelah restore; keduanya menjelaskan transaksi/ERD/dashboard/audit |

## Pembagian dua anggota

Usulan, belum merupakan kesepakatan kelompok. Anggota A memimpin fondasi/auth, kamar/fasilitas, keluhan, audit. Anggota B memimpin penghuni/penempatan, tagihan/pembayaran, dashboard/laporan. T01–T03 dan desain transaksi ditinjau bersama. Pengujian, dokumentasi, restore, dan demo dibagi seimbang; masing-masing menguji modul anggota lain dan menjelaskan kembali alurnya. AI mengerjakan sesuai task, mahasiswa memeriksa bukti dan belajar dari walkthrough.

## Risiko dan respons

| Risiko | Respons yang ditetapkan |
|---|---|
| Versi PHP/MySQL tidak cocok | T01 memeriksa sebelum scaffold; sesuaikan instalasi, catat versi; jangan diam-diam beralih SQLite |
| AI mengubah scope atau mengulang scaffold | Prompt wajib membaca plan/progres/codebase; satu task atau fase per sesi |
| Data ganda karena request bersamaan | Lock, unique generated column, tes MySQL dengan dua koneksi |
| Tagihan bulan baru terlupa | Peringatan coverage dan sinkronisasi manual idempoten; cetak status ditahan sampai lengkap |
| Waktu tersisa sedikit | Bekukan fitur opsional; semua syarat TA tetap dikerjakan, kurangi dekorasi UI |
| Internet terputus saat demo | Build/aset lokal, MySQL lokal, data sintetis dan dump cadangan |
| Salah mengartikan BPMN atau aturan kos | Catat asumsi/ambiguitas; perubahan business rule memperbarui dokumen terkait sebelum coding |

Perubahan scope bukan sekadar menambah task: nilai dampaknya ke tabel, data lama, izin, pengujian, dan presentasi. Fitur opsional setelah TA: ekspor Excel, unggah bukti, perubahan tarif kontrak, prorata/pindah kamar, notifikasi, dan hosting; tidak termasuk komitmen versi pertama.
