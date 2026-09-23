# Progres SIM Kos

## Status terkini

- **Progres Implementasi**: Task T01, T02, T03, T04, T05, T06, T07, T08, T09, T10, dan T11 telah SELESAI. Task T12–T24 belum dimulai.
- **Environment**: PHP 8.3.32 (cli), Composer 2.10.2, Node v24.18.0, npm 11.16.0, Git 2.55.0, MySQL 8.0.30 (Laragon). Ekstensi `intl` telah aktif.
- **Pemisahan Status Database Nyata**:
  - **Database Uji (`sim_kos_test`)**: Dimigrasikan penuh (13 migrasi) dan di-seed dengan `RoleSeeder` dan `UserSeeder`. Dilindungi oleh database guard pada `tests/TestCase.php`. Terbukti lulus seluruh pengujian otomatis: TC-31 (18 tests, 86 assertions), TC-24/TC-32 fondasi (10 tests, 46 assertions), TC-01–TC-05 (24 tests, 113 assertions), TC-29 (6 tests, 49 assertions), TC-06 & TC-09 (30 tests, 185 assertions), TC-07/TC-05/TC-30 (37 tests, 185 assertions), TC-08 & TC-09 fasilitas (40 tests, 277 assertions), TC-12, TC-13, & TC-33 billing (19 tests, 150 assertions), TC-10 & TC-11 penempatan (18 tests, 153 assertions), TC-14 pengakhiran penempatan (25 tests, 174 assertions), total suite 229 tests (1420 assertions).
  - **Database Aplikasi (`sim_kos`)**: Diverifikasi koneksi aktual dan tabel awal, dimigrasikan normal tanpa reset database. Data operasional kamar, fasilitas, penempatan, dan akun demo 7 pengguna diverifikasi utuh.
- **Automated Tests**: 229 passed, 1420 assertions (0 failure, 0 error) pada suite pengujian `sim_kos_test`.
- **Frontend & Master Data**: Layout bersama Bootstrap 5 lokal, navigasi menu "Kamar", "Penghuni", "Fasilitas", & "Penempatan" (Admin) serta "Data Kamar", "Data Penghuni", "Data Fasilitas", & "Data Penempatan" (Pemilik), validasi form server-side dengan pesan Indonesia dan aksesibilitas `aria-invalid`, tab filter (Aktif, Selesai/Diarsipkan, Semua), modal konfirmasi interaktif preview terverifikasi server (`preview_token`), pembatalan request preview lama via AbortController dan pelacakan sequence, status dinamis terbebas dari query N+1 (eager loading `validPayment`), otorisasi server-side `Gate::authorize()`, pembuatan penempatan dan penerbitan invoice pertama atomik dengan `BillingService::syncPlacementInvoices`, serta proteksi drift tarif/tanggal operasional pasca-lock.

## Langkah berikut

Task T11: Akhiri Penempatan (Check-out) telah selesai secara penuh dengan seluruh pengujian TC-14 (25 tests, 174 assertions) serta 4 screenshot bukti visual. Langkah berikutnya adalah melanjutkan ke implementasi Task **T12: Halaman Tagihan & Sinkronisasi Manual (Billing Management)** yang mencakup daftar invoice seluruh penempatan, filter status pembayaran (lunas/belum lunas), trigger sinkronisasi tagihan manual oleh Administrator, dan audit trail penagihan.

## Tabel progres

| Fase | Task | Status | Bukti |
|---|---|---|---|
| 1 Fondasi | T01 | Selesai | docs/evidence/t01-welcome-page.png, log pengujian skeleton |
| 1 Fondasi | T02 | Selesai | docs/evidence/test-results.md (TC-31: 18 tests, 86 assertions) |
| 1 Fondasi | T03 | Selesai | docs/audit-service.md, docs/evidence/test-results.md (TC-24 & TC-32 Fondasi: 10 tests, 46 assertions) |
| 1 Fondasi | T04 | Selesai | docs/evidence/test-results.md (TC-01–TC-05: 24 tests, 113 assertions) |
| 1 Fondasi | T05 | Selesai | docs/evidence/t05-walkthrough.md, docs/evidence/t05/ (13 screenshot nyata), test-results.md (TC-29: 6 tests, 49 assertions) |
| 2 Data/penempatan | T06 | Selesai | docs/evidence/t06-walkthrough.md, docs/evidence/t06/ (11 screenshot nyata), test-results.md (TC-06 & TC-09: 30 tests, 185 assertions) |
| 2 Data/penempatan | T07 | Selesai | docs/evidence/t07-walkthrough.md, docs/evidence/t07/ (9 screenshot nyata), test-results.md (TC-07, TC-05, TC-30: 37 tests, 185 assertions) |
| 2 Data/penempatan | T08 | Selesai | docs/evidence/t08-walkthrough.md, docs/evidence/t08/ (10 screenshot nyata), test-results.md (TC-08 & TC-09: 40 tests, 277 assertions) |
| 2 Data/penempatan | T09 | Selesai | docs/evidence/t09-walkthrough.md, test-results.md (TC-12, TC-13, TC-33: 19 tests, 150 assertions) |
| 2 Data/penempatan | T10 | Selesai | docs/evidence/t10-walkthrough.md, docs/evidence/t10/ (7 screenshot nyata), test-results.md (TC-10 & TC-11: 18 tests, 153 assertions) |
| 2 Data/penempatan | T11 | Selesai | docs/evidence/t11-walkthrough.md, test-results.md (TC-14: 25 tests, 174 assertions), docs/evidence/t11/ (4 screenshot nyata) |
| 3 Pembayaran | T12–T15 | Belum dimulai | — |
| 4 Keluhan | T16–T17 | Belum dimulai | — |
| 5 Informasi | T18–T20 | Belum dimulai | — |
| 6 Penyerahan | T21–T24 | Belum dimulai | — |
| 3 Pembayaran | T12–T15 | Belum dimulai | — |
| 4 Keluhan | T16–T17 | Belum dimulai | — |
| 5 Informasi | T18–T20 | Belum dimulai | — |
| 6 Penyerahan | T21–T24 | Belum dimulai | — |

## Format catatan setiap task

```text
Tanggal:
Task dan status: BELUM DIMULAI / BERJALAN / TERHAMBAT / SELESAI
Ringkasan perubahan:
File utama:
Keputusan/asumsi yang berubah dan dampaknya:
Perintah verifikasi yang benar-benar dijalankan:
Hasil dan lokasi bukti:
Hal yang belum diuji:
Kendala tersisa:
Cara menjalankan keadaan saat ini:
Task berikutnya:
```

## Catatan Eksekusi Task

### T01 - Fondasi & Setup Proyek Laravel SIM Kos
- **Tanggal**: 19 September 2026
- **Task dan status**: T01 SELESAI
- **Ringkasan perubahan**:
  - Inventarisasi environment Windows: PHP 8.3.32, Composer 2.10.2, Node v24.18.0, npm 11.16.0, Git 2.55.0.windows.2, MySQL 8.0.30 (Laragon).
  - Mengaktifkan ekstensi `intl` pada `C:\php\php.ini` yang dibutuhkan oleh komponen Number formatting Laravel.
  - Melakukan scaffold Laravel 13.32.0 secara non-destruktif melalui folder sementara `tmp/laravel_skeleton` dengan opsi `--no-scripts` untuk mencegah migrasi otomatis dan pembuatan `database.sqlite` bawaan.
  - Memindahkan seluruh skeleton (termasuk hidden files) ke direktori root `TA` tanpa menimpa berkas yang sudah ada (`docs/`, `tmp/`, dan berkas prompt lama tetap utuh).
  - Menghapus skrip migrasi otomatis dari `composer.json` (`post-create-project-cmd`).
  - Menyiapkan database lokal `sim_kos` dan `sim_kos_test` di MySQL lokal port 3306 (user `root`, tanpa password).
  - Mengonfigurasi `.env` dan `.env.example`: `SESSION_DRIVER=file`, `CACHE_STORE=file`, `QUEUE_CONNECTION=sync`, `DB_CONNECTION=mysql`, `DB_DATABASE=sim_kos`.
  - Mengonfigurasi `phpunit.xml`: `DB_CONNECTION=mysql`, `DB_DATABASE=sim_kos_test`.
  - Memastikan `.gitignore` mengabaikan `.env`, `.env.backup`, `vendor/`, `node_modules/`, dan `/tmp`.
  - Memasang dependensi npm (`npm install`) dan mengompilasi aset frontend (`npm run build`).
  - Menginisialisasi Git lokal (`git init`).
  - Membuat `README.md` pada root workspace dengan panduan lokal Windows.
- **File utama**:
  - `.env`, `.env.example`, `composer.json`, `phpunit.xml`, `.gitignore`, `README.md`
- **Keputusan/asumsi yang berubah dan dampaknya**:
  - *Penyesuaian Baseline MySQL*: MySQL aktual yang terpasang di sistem adalah versi `8.0.30` (bukan 8.4). MySQL 8.0.30 mendukung InnoDB, utf8mb4, CHECK constraint, dan generated column. Kompatibilitas skema penuh dan constraint database dibuktikan secara nyata pada T02.
  - Ekstensi PHP `intl` diaktifkan di `C:\php\php.ini` agar `php artisan db:show` dan fitur format Laravel berjalan normal.
- **Perintah verifikasi yang benar-benar dijalankan**:
  - `php -v`, `composer -V`, `node -v`, `npm -v`, `git --version`, `mysql -u root -e "SELECT VERSION();"`
  - `php artisan --version` (Hasil: `Laravel Framework 13.32.0`)
  - `php artisan config:clear`
  - `php artisan db:monitor` (Hasil: `mysql .. [2] OK`)
  - `php artisan db:show` (Hasil: Terhubung ke MySQL 8.0.30 database `sim_kos`)
  - `php artisan test` & `php vendor/bin/phpunit` (Hasil: 2 tests, 2 passed, 2 assertions)
  - `npm run build` (Hasil: Sukses mengompilasi aset Vite dalam 1.08s)
  - `php artisan serve` & verifikasi browser via headless agent ke `http://127.0.0.1:8000` (Hasil: HTTP 200 OK, title "SIM Kos", halaman awal Laravel berhasil dirender sempurna)
- **Hasil dan lokasi bukti**:
  - Screenshot browser: `docs/evidence/t01-welcome-page.png`
- **Hal yang belum diuji**:
  - Tabel bisnis, foreign key constraint, CHECK constraint, generated column, dan unique index (diuji pada T02).
  - Skrip autentikasi, layout custom, dan alur bisnis kos (dijadwalkan pada task berikutnya).
- **Kendala tersisa**:
  - Tidak ada kendala teknis yang menghalangi. MySQL berjalan lokal via Laragon / mysqld.
- **Cara menjalankan keadaan saat ini**:
  - Pastikan MySQL aktif (Laragon Start All).
  - Jalankan `php artisan serve`.
  - Buka browser di `http://127.0.0.1:8000`.
- **Task berikutnya**:
  - T02: SELESAI (lanjut ke T03).

### T02 - Skema Database, Constraint, Model/Relasi, dan Role Seeder
- **Tanggal**: 19 September 2026
- **Task dan status**: T02 SELESAI
- **Ringkasan perubahan**:
  - Memasang pengaman koneksi database (*database safety guard*) pada `tests/TestCase.php` sebelum `parent::setUp()` yang memverifikasi koneksi aktual melalui query `SELECT DATABASE()` dan memastikan driver adalah MySQL dan database adalah `sim_kos_test`. Eksekusi langsung dihentikan dengan `RuntimeException` jika tidak sesuai.
  - Mengonfigurasi berkas `.env.testing` khusus lingkungan tes dan memastikannya diabaikan oleh `.gitignore`.
  - Memperbarui migration awal `0001_01_01_000000_create_users_table.php` dengan tabel `roles` dan `users` (termasuk CHECK constraint `chk_roles_code` untuk memastikan code hanya 'admin', 'owner', 'resident', serta FK `role_id` RESTRICT).
  - Membuat 9 migration baru terurut: `rooms` (CHECK rate > 0), `residents` (FK user_id UNIQUE), `facilities` (CHECK location_type, condition, location_rule), `placements` (CHECK rate, dates, end_metadata, serta generated UNIQUE columns `active_room_id` dan `active_resident_id`), `invoices` (CHECK amount, period_month day 1, unique composite placement_id + period_month), `payments` (CHECK amount, status, method, void_metadata, serta generated UNIQUE column `valid_invoice_id`), `complaints` (CHECK status, closed_metadata), `complaint_updates` (CHECK from/to status), `activity_logs` (JSON changes, indexes).
  - Membuat 11 Model Eloquent lengkap: `Role`, `User`, `Room`, `Resident`, `Facility`, `Placement`, `Invoice`, `Payment`, `Complaint`, `ComplaintUpdate`, `ActivityLog` dengan relasi dua arah, casts, dan proteksi kolom generated dari mass assignment.
  - Membuat `RoleSeeder` idempoten untuk 3 role (`admin`, `owner`, `resident`) dan menghubungkannya pada `DatabaseSeeder`.
  - Menjalankan migrasi penuh pada database `sim_kos_test`.
  - Menyusun dan menjalankan test suite komprehensif TC-31 pada `tests/Feature/DatabaseConstraintTest.php` mencakup:
    1. Penolakan duplikasi `roles.code` (MySQL 1062)
    2. Penolakan duplikasi `residents.user_id` (MySQL 1062)
    3. Penolakan duplikasi `payments.receipt_number` pada invoice berbeda (MySQL 1062)
    4. Penolakan `payments.invoice_id` non-existent dengan field lain valid (MySQL 1452)
    5. Penolakan penghapusan parent yang masih direferensikan untuk membuktikan RESTRICT menjaga histori (MySQL 1451)
    6. Pengetatan helper CHECK: memverifikasi kode error MySQL 3819 dan nama constraint spesifik tanpa menerima keyword generik 'CONSTRAINT'
    7. Penolakan generated column collision untuk multiple active placements & valid payments, serta update bentrok
- **File utama**:
  - `database/migrations/` (12 migration files)
  - `app/Models/` (11 Eloquent models)
  - `database/seeders/RoleSeeder.php`, `database/seeders/DatabaseSeeder.php`
  - `tests/TestCase.php`, `tests/Feature/DatabaseConstraintTest.php`
  - `.env.testing`, `.gitignore`, `docs/evidence/test-results.md`
- **Keputusan/asumsi yang berubah dan dampaknya**:
  - *MySQL Keyword Quoting*: Kolom `condition` pada tabel `facilities` merupakan reserved keyword di MySQL 8.0 sehingga wajib dibungkus backtick (`` `condition` ``) pada seluruh raw DDL / ALTER TABLE CHECK constraint.
  - *Pemisahan Status Database Nyata*:
    - Database `sim_kos_test`: Telah dimigrasikan penuh (12 migrasi) dan diverifikasi lulus 18 pengujian constraint (86 assertions).
    - Database aplikasi `sim_kos`: **Belum dimigrasikan (0 tabel, `Migration table not found`)** untuk menjaga database aplikasi tetap bersih dan terisolasi sampai modul aplikasi siap.
  - *Pengetatan Helper CHECK*: Helper `assertCheckConstraintFails` menolak string generik `'CONSTRAINT'` dan secara ketat memverifikasi MySQL error code `3819` beserta nama constraint spesifik yang dilanggar.
- **Perintah verifikasi yang benar-benar dijalankan**:
  - `php artisan migrate:status --env=testing` (Hasil: Seluruh 12 migrasi berstatus Ran pada `sim_kos_test`)
  - `php artisan migrate:status` (Hasil: `Migration table not found` - membuktikan `sim_kos` tetap bersih dan tidak disentuh)
  - `php artisan db:seed --class=RoleSeeder --env=testing` dijalankan 2x (Hasil: Idempoten, tepat 3 role tanpa duplikasi)
  - `php vendor/bin/phpunit --testdox tests/Feature/DatabaseConstraintTest.php` (Hasil: 18 passed, 86 assertions, 0 failure)
  - `php artisan test` (Hasil: 20 passed, 88 assertions, 0 failure)
- **Hasil dan lokasi bukti**:
  - Log rinci pengujian TC-31: [docs/evidence/test-results.md](docs/evidence/test-results.md)
- **Hal yang belum diuji**:
  - Autentikasi web, login session/throttling, AuditService logging (dijadwalkan pada T03–T04).
  - Controller, views, dan form mutasi bisnis (dijadwalkan pada Fase 2).
- **Kendala tersisa**:
  - Tidak ada kendala teknis. MySQL 8.0.30 terbukti kompatibel dengan seluruh skema, foreign key RESTRICT, generated unique column, dan check constraint.
- **Cara menjalankan keadaan saat ini**:
  - Jalankan test suite TC-31: `php artisan test --filter=DatabaseConstraintTest`
  - Jalankan seluruh tes: `php artisan test`
  - Periksa status database aplikasi: `php artisan migrate:status`
- **Task berikutnya**:
  - T03: SELESAI (lanjut ke T04).

### T03 - Fondasi AuditService & Pengujian Rollback Audit
- **Tanggal**: 19 September 2026
- **Task dan status**: T03 SELESAI
- **Ringkasan perubahan**:
  - Membangun [app/Services/AuditService.php](app/Services/AuditService.php) sebagai pintu tunggal audit logging bisnis yang mencatat: `actor_id`, `actor_name` (snapshot), `action`, `module`, `entity_type`, `entity_id`, `entity_label`, `summary`, `changes`, dan `occurred_at` (server UTC).
  - Menetapkan kontrak `changes`: `{"before": {...}, "after": {...}}` dengan allowlist per modul (`AuditService::ALLOWED_FIELDS[$module]`) yang ditentukan di sisi kode/konfigurasi, bukan dari request input.
  - Memasang denylist pertahanan berlapis (`AuditService::FORBIDDEN_KEYS`) untuk menyaring otomatis field sensitif (`password`, `token`, `secret`, `cookie`, `credential`) serta menolak objek/array bersarang pada kolom skalar.
  - Memastikan identitas pelaku pada `log()` diterima dari objek `User` server-side context (`auth()->user()`), bukan dari input browser.
  - Menyediakan metode eksplisit `logSystem()` untuk aksi latar belakang sistem (`actor_id = null`, `actor_name = 'Sistem'`).
  - Menetapkan pola integrasi sinkron dalam koneksi dan transaksi database pemanggil (`DB::transaction()`); error audit sengaja tidak ditelan agar memicu rollback mutasi secara utuh.
  - Menambahkan method pembantu [occurredAtJakarta()](app/Models/ActivityLog.php) pada model `ActivityLog` untuk mengonversi waktu UTC ke `Asia/Jakarta` hanya saat ditampilkan.
  - Menyusun test suite komprehensif [tests/Feature/AuditServiceTest.php](tests/Feature/AuditServiceTest.php) (10 tests, 45 assertions) mencakup determinisme waktu UTC dengan waktu yang dibekukan (`Carbon::setTestNow`), penyaringan allowlist/sensitif/nested, snapshot immutability, aktor sistem, dan pengujian rollback nyata di level database MySQL (error 1406 data too long).
  - Menyusun panduan arsitektur dan contoh integrasi pada [docs/audit-service.md](docs/audit-service.md).
- **File utama**:
  - `app/Services/AuditService.php`
  - `app/Models/ActivityLog.php`
  - `tests/Feature/AuditServiceTest.php`
  - `docs/audit-service.md`, `docs/evidence/test-results.md`
- **Keputusan/asumsi yang berubah dan dampaknya**:
  - *Tanggung Jawab Transaksi*: `AuditService` tidak membuka transaksi mandiri. Caller bertanggung jawab membuka `DB::transaction()` agar mutasi dan audit atomik. Jika audit gagal, mutasi bisnis ikut rollback.
  - *Status TC-24 & TC-32*: Dinyatakan **LULUS pada Tingkat Fondasi**. Integrasi penuh per fitur bisnis akan diuji saat masing-masing modul dibangun pada Fase 2–4.
  - *Pemisahan Database Tetap Utuh*: Database `sim_kos_test` lulus 30 tests (134 assertions), sementara database aplikasi `sim_kos` tetap bersih (0 tabel).
- **Perintah verifikasi yang benar-benar dijalankan**:
  - `php vendor/bin/phpunit --testdox tests/Feature/AuditServiceTest.php` (Hasil: 10 passed, 46 assertions, 0 failure)
  - `php artisan test` (Hasil: 30 passed, 134 assertions, 0 failure)
  - `php artisan migrate:status` (Hasil: `Migration table not found` - database aplikasi `sim_kos` tetap bersih)
- **Hasil dan lokasi bukti**:
  - Panduan pemakaian caller: [docs/audit-service.md](docs/audit-service.md)
  - Log rinci pengujian TC-24 & TC-32 Fondasi: [docs/evidence/test-results.md](docs/evidence/test-results.md)
- **Hal yang belum diuji**:
  - Autentikasi dan login multi-role (dijadwalkan pada T04).
  - Tampilan halaman audit log dan filter laporan audit (dijadwalkan pada Fase 5).
- **Kendala tersisa**:
  - Tidak ada kendala teknis. Seluruh pengujian lulus 100%.
- **Cara menjalankan keadaan saat ini**:
  - Jalankan test suite AuditService: `php artisan test --filter=AuditServiceTest`
  - Jalankan seluruh suite tes: `php artisan test`
- **Task berikutnya**:
  - T04: SELESAI (lanjut ke T05).

### T04 - Autentikasi dan Otorisasi Multi-Role
- **Tanggal**: 19 September 2026
- **Task dan status**: T04 SELESAI
- **Ringkasan perubahan**:
  - Memverifikasi koneksi aktual dan tabel database aplikasi `sim_kos` (awal 0 tabel), lalu menjalankan migrasi normal `php artisan migrate` (12 migrasi `Ran` tanpa reset) dan seeder lokal idempoten.
  - Membangun `UserSeeder` idempoten yang memuat password demo dari konfigurasi lokal (`DEMO_ADMIN_PASSWORD`, `DEMO_OWNER_PASSWORD`, `DEMO_RESIDENT_PASSWORD`) tanpa hardcoded fallback password di `config/auth.php` maupun di `UserSeeder`. Jika konfigurasi kosong/tidak valid untuk akun yang belum ada, proses seeding dihentikan dengan `RuntimeException` yang jelas tanpa membuat akun parsial. Pembuatan akun dan profil resident dibungkus dalam satu transaksi database (`DB::transaction`). Akun existing terlindungi dan tidak ditimpa password, status, maupun rolenya.
  - Identitas akun demo diselaraskan dengan seeder aktual dan dokumen rencana: admin (`admin@example.test`), owner (`owner@example.test`), dan resident (`resident@example.test`) lengkap dengan profil `Resident` dan flag `must_change_password = true`.
  - Mengonfigurasi 3 middleware keamanan:
    - `EnsureAccountIsActive` (`active`): Menolak akun nonaktif secara instan pada request berikutnya, mengakhiri sesi, membersihkan session data, dan meregenerasi token CSRF.
    - `EnsureRole` (`role`): Mengembalikan HTTP 403 Forbidden jika role pengguna tidak cocok.
    - `EnsurePasswordNotTemporary` (`password.not_temp`): Memaksa redirect ke `/password/change` jika `must_change_password = true`, dengan pengecualian rute ganti password dan logout untuk mencegah redirection loop.
  - Mengembangkan `AuthController`:
    - Rate limiting konsisten: maksimal 5 percobaan gagal per menit yang mengembalikan redirect HTTP 302 dengan pesan error validasi throttle di session (`"Terlalu banyak percobaan login..."`).
    - Pesan kegagalan generik: `'Email atau password salah.'` baik untuk password salah, email tidak terdaftar, maupun akun yang dinonaktifkan.
    - Session regeneration saat login sukses untuk mencegah *session fixation*, dibuktikan dengan perubahan session ID.
    - Penanganan asimetris kegagalan audit: jika audit login gagal, pengguna dipaksa logout (tetap guest), session dibersihkan, dan CSRF token diregenerasi; jika audit logout gagal, sesi pengguna tetap dipastikan berakhir melalui blok `finally` tanpa mengganggu alur redirect keluar.
    - Logging teknis kegagalan audit aman: hanya mencatat konteks aman (`operation`, `user_id`, `exception_class`, `error_code`) tanpa membawa pesan database mentah yang dapat mengekspos SQL atau query bindings.
  - Mengembangkan `PasswordController`:
    - Validasi password: password saat ini wajib benar, password baru minimal 12 karakter, konfirmasi password wajib cocok.
    - Menjamin atomisitas transaksi: perubahan password, reset `must_change_password = false`, dan pencatatan audit log dibungkus dalam `DB::transaction()` yang sama. Jika audit gagal, seluruh perubahan password dan flag rollback penuh.
    - Session diregenerasi setelah password berhasil diperbarui, dibuktikan dengan perubahan session ID.
  - Mengembangkan `ResidentPolicy`:
    - Membedakan izin view dan mutasi: admin memiliki akses penuh, owner memiliki akses baca-saja (*read-only*), resident hanya dapat melihat profil miliknya sendiri dan dilarang melihat profil penghuni lain (HTTP 403).
    - Penanganan *null-safe* untuk pengguna tanpa profil resident tanpa memicu HTTP 500 error.
  - Menyusun views autentikasi fungsional: `resources/views/auth/login.blade.php`, `resources/views/auth/change-password.blade.php`, serta dashboard placeholder untuk admin, owner, dan portal resident.
  - Mendaftarkan endpoint pengujian dummy `/_test/admin-mutation` dan `/_test/resident-profile/{resident}` secara ketat hanya pada environment testing (`app()->environment('testing')`).
  - Menyesuaikan `AuditService` denylist agar kolom `must_change_password` (boolean status) tidak tersaring oleh aturan kata kunci 'password'.
  - Menyusun test suite komprehensif `tests/Feature/AuthTest.php` (24 tests, 113 assertions) yang mencakup seluruh skenario TC-01 hingga TC-05, pengujian kegagalan config seeder, idempotensi seeder, serta assertion perubahan session ID, penghapusan session data, dan perubahan token CSRF.
- **File utama**:
  - `app/Http/Controllers/AuthController.php`
  - `app/Http/Controllers/PasswordController.php`
  - `app/Http/Middleware/EnsureAccountIsActive.php`
  - `app/Http/Middleware/EnsureRole.php`
  - `app/Http/Middleware/EnsurePasswordNotTemporary.php`
  - `app/Http/Requests/Auth/LoginRequest.php`
  - `app/Http/Requests/Auth/ChangePasswordRequest.php`
  - `app/Policies/ResidentPolicy.php`
  - `database/seeders/UserSeeder.php`
  - `routes/web.php`
  - `resources/views/auth/login.blade.php`, `resources/views/auth/change-password.blade.php`
  - `tests/Feature/AuthTest.php`
  - `config/auth.php`, `.env`, `.env.example`
- **Keputusan/asumsi yang berubah dan dampaknya**:
  - *Penanganan Asimetris Audit*: Pada login, kegagalan audit membatalkan sesi login agar pengguna tidak masuk tanpa audit trail (transaksional aman). Pada logout, kegagalan audit dicatat di log teknis sistem yang aman, tetapi sesi pengguna tetap wajib diakhiri pada blok `finally` agar pengguna tidak tertahan dalam sesi aktif karena gangguan log audit.
  - *Klaim Throttle*: Selaras dengan kode, throttle mengembalikan HTTP 302 Redirect dengan error session validasi (bukan respons HTTP 429 langsung).
  - *Pencegahan Bocoran Kredensial di Log*: Technical log audit login/logout hanya mencatat kelas exception dan kode error, bukan pesan SQL mentah.
- **Perintah verifikasi yang benar-benar dijalankan**:
  - `php artisan db:monitor` (Hasil: Koneksi MySQL 8.0.30 OK)
  - Hash check `admin@example.test` dengan `DEMO_ADMIN_PASSWORD` (Hasil: `DB: sim_kos | STATUS: COCOK`)
  - Verifikasi E2E sesi HTTP pada server aktif `http://127.0.0.1:8000`: Login sukses, Session cookie diregenerasi, Dashboard terakses, Logout berhasil, Session cookie diinvaliasi, Dashboard post-logout diblokir.
  - `php vendor/bin/phpunit --testdox tests/Feature/AuthTest.php` (Hasil: 24 passed, 113 assertions, 0 failure)
  - `php artisan test` (Hasil: 54 passed, 247 assertions, 0 failure)
- **Hasil dan lokasi bukti**:
  - Log rinci pengujian TC-01–05: [docs/evidence/test-results.md](docs/evidence/test-results.md)
- **Hal yang belum diuji**:
  - Tampilan visual lengkap Bootstrap 5 dan Navbar terintegrasi multi-role (dijadwalkan pada T05).
  - Kepemilikan entitas invoice, pembayaran, penempatan, dan keluhan (dijadwalkan pada Fase 2–4).
- **Kendala tersisa**:
  - Tidak ada kendala teknis. Seluruh pengujian lulus 100%.
- **Cara menjalankan keadaan saat ini**:
  - Jalankan test suite Auth: `php artisan test --filter=AuthTest`
  - Jalankan seluruh suite tes: `php artisan test`
  - Buka halaman login di browser: `http://127.0.0.1:8000/login`
- **Task berikutnya**:
  - T05: SELESAI (lanjut ke T06).

### T05 - Layout Blade, Bootstrap 5 Lokal, Navigasi Role, dan Komponen Bersama
- **Tanggal**: 20 September 2026
- **Task dan status**: T05 SELESAI
- **Ringkasan perubahan**:
  - Menghapus dependensi Tailwind CSS bawaan (`@tailwindcss/vite`, `tailwindcss`) dan memasang `bootstrap@^5.3.8` serta `@popperjs/core@^2.11.8` via npm secara lokal tanpa CDN.
  - Memperbarui `vite.config.js` untuk build aset lokal murni tanpa font atau plugin eksternal.
  - Menyusun `resources/css/print.css` dan `resources/css/app.css` dengan urutan `@import` di baris teratas sebelum aturan CSS biasa, mendefinisikan token tema SIM Kos (navy `#0f172a`, latar `#f8fafc`, aksen teal `#0d9488`, dan ring aksesibilitas keyboard).
  - Mengompilasi aset frontend via `npm run build` (CSS 236 kB, JS 79 kB). Memastikan `public/hot` tidak ada agar aplikasi 100% menggunakan aset lokal offline.
  - Membuat komponen antarmuka bersama berstandar aksesibilitas (`resources/views/components/`):
    - `x-input`: menghubungkan label, helper, dan error feedback via `id` dan `aria-describedby`; mengaktifkan `aria-invalid="true"` saat validasi gagal; secara ketat **tidak mengisi atribut value atau old()** pada kolom kata sandi.
    - `x-alert`: visualisasi alert flash session Bootstrap (`status`, `success`, `error`, `warning`).
    - `x-button`: tombol teal dan aksi antarmuka terstandar.
    - `x-badge`: badge role (`admin`, `owner`, `resident`) dengan contrast yang jelas.
    - `x-empty-state`: placeholder informatif netral ("Informasi Belum Tersedia") tanpa klaim kosong palsu dan tanpa query database prematur sebelum T18.
    - `x-table-card`: pembungkus tabel responsif.
  - Membangun layout dan partials bersama (`resources/views/layouts/`):
    - `layouts.app`: layout terpadu dengan header sticky, sidebar desktop, drawer offcanvas mobile, dan footer.
    - `layouts.guest`: layout halaman tamu / login.
    - `layouts.partials.sidebar-nav`: navigasi role dinamis (Admin, Pemilik, Penghuni). Fitur mendatang ditampilkan sebagai teks non-interaktif berlabel **"Belum tersedia"** tanpa `href="#"`, tanpa rute dummy, dan tanpa badge fase. Menampilkan **navigasi terbatas** (hanya Ganti Password dan Logout) bagi pengguna dengan password sementara.
    - `layouts.partials.header`: topbar dengan toggle drawer mobile, role badge, dan dropdown akun (ganti password dan logout POST).
  - Merefaktor halaman autentikasi dan dashboard awal:
    - `resources/views/auth/login.blade.php`: menggunakan `layouts.guest` dan komponen bersama.
    - `resources/views/auth/change-password.blade.php`: menggunakan `layouts.app`, form logout terpisah yang valid HTML5, dan validasi feedback.
    - `resources/views/admin/dashboard.blade.php`, `resources/views/owner/dashboard.blade.php`, `resources/views/resident/portal.blade.php`: menggunakan `layouts.app`, kartu profil role, dan empty state netral.
  - Menulis test suite otomatis `tests/Feature/LayoutNavigationTest.php` (6 tests, 49 assertions) yang memverifikasi aksesibilitas input, penghindaran password value/old, pesan error dan `aria-invalid`, render layout tiap role, dan navigasi terbatas password sementara.
- **File utama**:
  - `package.json`, `package-lock.json`, `vite.config.js`
  - `resources/css/app.css`, `resources/css/print.css`, `resources/js/app.js`
  - `resources/views/components/input.blade.php`, `alert.blade.php`, `badge.blade.php`, `button.blade.php`, `empty-state.blade.php`, `table-card.blade.php`
  - `resources/views/layouts/app.blade.php`, `guest.blade.php`
  - `resources/views/layouts/partials/sidebar.blade.php`, `sidebar-nav.blade.php`, `header.blade.php`
  - `resources/views/auth/login.blade.php`, `change-password.blade.php`
  - `resources/views/admin/dashboard.blade.php`, `owner/dashboard.blade.php`, `resident/portal.blade.php`
  - `tests/Feature/LayoutNavigationTest.php`
  - `docs/evidence/t05-walkthrough.md`, `docs/evidence/t05/` (13 screenshot)
- **Keputusan/asumsi yang berubah dan dampaknya**:
  - *Menu Fitur Mendatang*: Tidak menggunakan badge fase (misalnya "Fase 2") dan tidak menggunakan `href="#"` atau route dummy, melainkan teks non-interaktif berlabel "Belum tersedia".
  - *Dashboard Awal Netral*: Tidak mengklaim "belum ada data/tagihan/keluhan" tanpa query pembuktian; menggunakan pesan informatif netral bahwa fitur/data terkait akan tersedia pada implementasi modul bersangkutan demi mencegah pembuatan query bisnis sebelum T18.
  - *Aksesibilitas Form*: Kolom password sama sekali tidak mengisi atribut `value` atau `old()` saat terjadi kegagalan validasi, demi mencegah kebocoran kredensial di DOM atau inspector.
  - *Pemisahan Form Logout pada Change Password*: Memisahkan form logout keluar dari form update password menggunakan atribut HTML5 `form="logout-form"` untuk mencegah nested form yang melanggar standar HTML.
- **Perintah verifikasi yang benar-benar dijalankan**:
  - `npm run build` (Hasil: Sukses mengompilasi CSS 236 kB dan JS 79 kB dalam 216ms)
  - `Test-Path "public/hot"` (Hasil: `False`, membuktikan penggunaan bundel offline lokal)
  - `php vendor/bin/phpunit --testdox tests/Feature/LayoutNavigationTest.php` (Hasil: 6 tests, 49 assertions, seluruhnya lulus)
  - `php artisan test` (Hasil: 60 passed, 296 assertions, 0 failure)
  - Uji visual browser otomatis desktop 1366×768 dan mobile 390px pada server aktif `http://127.0.0.1:8000`:
    - Login desktop & mobile
    - Validasi error login (`aria-invalid="true"`, input password kosong)
    - Admin dashboard desktop & mobile
    - Mobile offcanvas menu (terbuka, ditutup via tombol Escape, dan fokus kembali ke tombol toggle)
    - Ganti password desktop & alur logout
    - Owner dashboard desktop & mobile
    - User password sementara dengan navigasi terbatas
    - Resident portal desktop & mobile
- **Hasil dan lokasi bukti**:
  - Walkthrough lengkap: [docs/evidence/t05-walkthrough.md](docs/evidence/t05-walkthrough.md)
  - 13 Berkas screenshot nyata: `docs/evidence/t05/`
  - Log rinci pengujian TC-29: [docs/evidence/test-results.md](docs/evidence/test-results.md)
- **Hal yang belum diuji**:
  - CRUD master bisnis (Kamar, Penghuni, Fasilitas) yang dijadwalkan pada Fase 2 (T06–T08).
  - Tampilan grafik statistik dan metrik finansial bisnis yang dijadwalkan pada T18.
- **Kendala tersisa**:
  - Tidak ada kendala teknis. Seluruh kriteria T05 terpenuhi dengan sempurna.
- **Cara menjalankan keadaan saat ini**:
  - Jalankan build aset (jika belum): `npm run build`
  - Jalankan server aplikasi: `php artisan serve`
  - Jalankan test suite: `php artisan test`
  - Akses aplikasi di browser: `http://127.0.0.1:8000/login`
- **Task berikutnya**:
  - T06: SELESAI (lanjut ke T07).

### T06 - Master Kamar (Room Management)
- **Tanggal**: 20 September 2026
- **Task dan status**: T06 SELESAI
- **Ringkasan perubahan**:
  - Mengembangkan Model `Room` dengan relasi `placements()`, `activePlacement()`, dan `facilities()`. Mengimplementasikan `scopeSearch()` dengan pengelompokan kondisi OR (`where(function($q) { ... })`) agar pencarian tidak menerobos tab status arsip maupun hunian. Mengoptimalkan accessor `is_occupied` berbasis `withExists(['activePlacement as has_active_placement'])` untuk mengeliminasi potensi query N+1 per baris.
  - Membangun `RoomPolicy` dan menerapkannya menggunakan `Gate::authorize()` di seluruh controller: Admin memiliki izin penuh, Pemilik hanya baca (*read-only*), dan Penghuni dilarang penuh (HTTP 403).
  - Membuat `StoreRoomRequest` dan `UpdateRoomRequest` dengan penegakan izin di method `authorize()` sebelum validasi aturan sehingga role tanpa izin tetap memperoleh HTTP 403 meskipun mengirim payload yang tidak valid. Memvalidasi nomor kamar unik lintas kamar aktif dan diarsipkan, tarif bilangan bulat Rupiah positif (Rp 1 – Rp 999.999.999), dan membersihkan payload dari injeksi `archived_at` serta status hunian.
  - Membangun `RoomController` dengan transaksi database atomik (`DB::transaction`) dan penguncian baris (`lockForUpdate`). Menolak penghapusan fisik jika kamar memiliki histori penempatan atau referensi fasilitas (termasuk fasilitas diarsipkan) sesuai proteksi referensi RESTRICT (TC-09), serta menolak pengarsipan jika kamar sedang dihuni penempatan aktif.
  - Mengintegrasikan pencatatan audit log (`AuditService`) pada modul `rooms` untuk aksi `create`, `update`, `delete`, `archive`, dan `unarchive` dengan snapshot before/after yang aman (serialisasi waktu `archived_at` string/null). Memastikan kegagalan audit menyebabkan rollback penuh terhadap seluruh mutasi data.
  - Membuktikan imutabilitas tarif (TC-06): Pembaruan tarif kamar tidak mengubah `agreed_monthly_rate` penempatan aktif maupun `amount` faktur yang telah terbit.
  - Membangun antarmuka Blade responsif berbasis Bootstrap 5 lokal: `rooms/index.blade.php` (tab Aktif, Diarsipkan, Semua, filter pencarian & hunian, modal konfirmasi, pagination Bootstrap), `create.blade.php`, `edit.blade.php` (dengan alert edukasi imutabilitas tarif), dan `show.blade.php` (detail kamar, kartu penempatan aktif, tabel fasilitas, histori sewa, dan panduan kebijakan).
- **File utama**:
  - `app/Models/Room.php`
  - `app/Policies/RoomPolicy.php`
  - `app/Http/Requests/Room/StoreRoomRequest.php`
  - `app/Http/Requests/Room/UpdateRoomRequest.php`
  - `app/Http/Controllers/RoomController.php`
  - `routes/web.php`
  - `resources/views/layouts/partials/sidebar-nav.blade.php`
  - `resources/views/rooms/index.blade.php`
  - `resources/views/rooms/create.blade.php`
  - `resources/views/rooms/edit.blade.php`
  - `resources/views/rooms/show.blade.php`
  - `tests/Feature/RoomTest.php`
- **Keputusan/asumsi yang berubah dan dampaknya**:
  - *Otorisasi Gate::authorize()*: Base Controller belum menggunakan trait AuthorizesRequests, sehingga otorisasi ditegakkan konsisten melalui `Gate::authorize()` dan Form Request `authorize()`.
  - *Pencegahan N+1*: Pemeriksaan `has_active_placement` dilakukan via `withExists` pada query controller, bukan accessor yang menjalankan subquery terpisah tiap baris.
  - *Penanganan Rollback Exception Test*: Uji rollback audit menggunakan `$this->withoutExceptionHandling()` dan blok try-catch agar exception PHPUnit terverifikasi sekaligus assertion pembatalan transaksi database dapat dieksekusi.
- **Perintah verifikasi yang benar-benar dijalankan**:
  - `npm run build` (Sukses membangun aset lokal dalam 1.18s)
  - `Test-Path "public/hot"` (Hasil: `False`, verifikasi penggunaan aset build lokal)
  - `php vendor/bin/phpunit --testdox tests/Feature/RoomTest.php` (30 tests, 30 passed, 185 assertions)
  - `php artisan test` (90 tests, 90 passed, 481 assertions, 0 failure)
  - Uji visual browser otomatis desktop 1366×768 dan mobile 390px pada `http://127.0.0.1:8000`:
    - Validasi form tambah kamar (`02-admin-rooms-create-validation.png`)
    - Daftar kamar Admin desktop (`01-admin-rooms-index-desktop.png`)
    - Detail kamar Admin desktop (`03-admin-room-show-desktop.png`)
    - Edit kamar Admin desktop (`04-admin-room-edit-desktop.png`)
    - Modal konfirmasi arsip kamar (`05-admin-room-archive-modal.png`)
    - Tab kamar diarsipkan (`06-admin-rooms-archived-tab.png`)
    - Tampilan mobile 390px (`07-admin-rooms-mobile-390px.png`)
    - Daftar kamar Pemilik mode baca saja (`08-owner-rooms-index-desktop.png`)
    - Detail kamar Pemilik mode baca saja (`09-owner-room-show-desktop.png`)
    - Detail kamar Admin berpenghuni aktif, berfasilitas, dan berhistori sewa terurut tanggal descending (`10-admin-room-detail-relations.png`)
    - Detail kamar Pemilik dengan relasi lengkap tanpa tombol aksi mutasi (`11-owner-room-detail-relations.png`)
- **Hasil dan lokasi bukti**:
  - Walkthrough lengkap: [docs/evidence/t06-walkthrough.md](docs/evidence/t06-walkthrough.md)
  - 11 Berkas screenshot nyata: `docs/evidence/t06/`
  - Log rinci pengujian TC-06 & TC-09: [docs/evidence/test-results.md](docs/evidence/test-results.md)
- **Hal yang belum diuji**:
  - Modul Penghuni & Akun (T07) serta Fasilitas (T08) yang sesungguhnya di database aplikasi (diuji via relasi model dan test fixtures).
  - Transaksi pembayaran dan tagihan sewa bulanan (dijadwalkan pada Fase 3).
- **Kendala tersisa**:
  - Tidak ada kendala teknis. Seluruh kriteria T06 terpenuhi dengan sempurna.
- **Cara menjalankan keadaan saat ini**:
  - Pastikan server aktif: `php artisan serve`
  - Jalankan test suite: `php artisan test`
  - Buka halaman Kamar di browser: `http://127.0.0.1:8000/rooms`
- **Task berikutnya**:
  - T07: SELESAI (lanjut ke T08).

### T07 - Master Penghuni & Akun (Resident & Account Management)
- **Tanggal**: 21 September 2026
- **Task dan status**: T07 SELESAI
- **Ringkasan perubahan**:
  - Mengembangkan Model `Resident` dengan relasi `user()`, `placements()`, dan `activePlacement()`. Mengimplementasikan `scopeSearch()` dengan pengelompokan kondisi OR (`where(function($q) { ... })`) untuk pencarian nama, telepon, dan email tanpa menerobos tab status arsip maupun filter akun. Mengoptimalkan accessor `getHasHistoricalReferencesAttribute()` dan helper `canBeArchived()` berbasis `withExists()` untuk mencegah potensi kueri N+1 per baris pada tabel daftar penghuni.
  - Membangun `ResidentPolicy` dengan pemisahan tegas antara otorisasi master manajemen kos (`viewMaster()`) dan profil mandiri portal (`view()`): Penghuni dilarang mengakses seluruh endpoint master penghuni (**HTTP 403 Forbidden**) termasuk saat mencoba melihat detail dirinya sendiri melalui `/residents/{resident}`. Admin berwenang penuh mengelola (create, edit, delete, archive, unarchive, activate, deactivate, resetPassword); Pemilik memiliki hak baca-saja (*read-only*).
  - Membuat `StoreResidentRequest` dan `UpdateResidentRequest` dengan penegakan izin di method `authorize()` sebelum validasi aturan sehingga role tanpa izin tetap memperoleh HTTP 403 meskipun payload form tidak valid. Memvalidasi nomor telepon wajib memuat digit (`/^(?=.*[0-9])[0-9+\-\s]{8,20}$/`), email unik di tabel `users` lintas akun aktif maupun nonaktif (dengan pengecualian ID diri sendiri saat update), dan membersihkan input dari manipulasi `role`, `role_id`, `user_id`, `is_active`, `archived_at`, `password`, atau `must_change_password`.
  - Membangun service transaksional `ResidentService` sebagai satu-satunya pintu masuk mutasi data penghuni dan akun. Mengimplementasikan penguncian baris konsisten (`lockForUpdate`: `Resident` lalu `User`) dan mengevaluasi status penempatan aktif serta referensi histori di dalam transaksi.
  - Menerapkan pembuatan akun dan profil penghuni secara atomik dalam satu transaksi (`DB::transaction`) beserta pencatatan audit log ganda (`module: 'residents'` dan `module: 'users'`). Kegagalan pada pembuatan profil atau pencatatan log audit kedua terbukti membatalkan seluruh transaksi tanpa meninggalkan akun yatim (TC-07 & TC-30).
  - Mengimplementasikan sinkronisasi nama: pembaruan nama pada profil penghuni menyinkronkan nama pada akun pengguna terkait, namun terbukti TIDAK mengubah nilai snapshot nama historis pada `invoices.resident_name_snapshot` (TC-30).
  - Menerapkan aksi eksplisit idempoten aktifkan (`activateAccount`) dan nonaktifkan (`deactivateAccount`). Akun dengan profil diarsipkan dilarang diaktifkan langsung (wajib melalui buka arsip). Membuka arsip hanya diizinkan jika profil berstatus diarsipkan dan mengaktifkan kembali akun terkait secara transparan.
  - Menerapkan mekanisme pencabutan sesi lama yang kompatibel 100% dengan `SESSION_DRIVER=file` melalui validasi signature session hash (`auth_session_hash_{user_id}`) pada middleware `EnsureAccountIsActive`. Sesi lama dari dua sesi independen langsung dicabut saat penonaktifan akun, pengaktifan kembali, atau reset password sementara, serta remember token dirotasi (TC-05).
  - Penyerahan password sementara: Password acak 12 karakter alfanumerik dihasilkan secara kriptografis aman (`Str::password(12)`), langsung di-hash, flag `must_change_password = true`, dan diserahkan ke Admin melalui session flash sekali pakai. Halaman yang menampilkan kredensial dilindungi header `Cache-Control: no-store`, password termaskir default (`••••••••••••`) di UI, dan plaintext password tidak pernah dicatat ke audit log atau URL.
  - Membangun antarmuka Blade responsif berbasis Bootstrap 5 lokal: `residents/index.blade.php` (tab Aktif, Diarsipkan, Semua, filter pencarian & status akun, modal konfirmasi Bootstrap 5), `create.blade.php`, `edit.blade.php` (dengan alert edukasi imutabilitas invoice snapshot), dan `show.blade.php` (kartu identitas, kartu akun, kartu riwayat sewa kamar, panel kelola akun, panel aksi master, dan kartu kredensial password sementara termaskir).
- **File utama**:
  - `app/Models/Resident.php`
  - `app/Models/User.php`
  - `app/Policies/ResidentPolicy.php`
  - `app/Services/ResidentService.php`
  - `app/Http/Requests/Resident/StoreResidentRequest.php`
  - `app/Http/Requests/Resident/UpdateResidentRequest.php`
  - `app/Http/Controllers/ResidentController.php`
  - `app/Http/Middleware/EnsureAccountIsActive.php`
  - `app/Http/Controllers/AuthController.php`
  - `app/Http/Controllers/PasswordController.php`
  - `routes/web.php`
  - `resources/views/layouts/partials/sidebar-nav.blade.php`
  - `resources/views/residents/index.blade.php`
  - `resources/views/residents/create.blade.php`
  - `resources/views/residents/edit.blade.php`
  - `resources/views/residents/show.blade.php`
  - `tests/Feature/ResidentTest.php`
- **Keputusan/asumsi yang berubah dan dampaknya**:
  - *Signature Hash Sesi per User ID*: Signature session disimpan dengan kunci `auth_session_hash_{user_id}` agar evaluasi independen antar user dalam lingkungan pengujian multi-sesi tidak saling menginterferensi.
  - *Masking Default Password Sementara*: Password sementara termaskir secara default di tampilan Blade (`••••••••••••`) dan hanya terlihat jika tombol "Tampilkan" ditekan secara sadar oleh Admin, memastikan tidak ada plaintext password yang bocor ke rekaman layar atau tangkapan visual.
  - *Penguncian Urutan Transaksi*: Baris `Resident` selalu dikunci terlebih dahulu baru disusul baris `User` untuk mencegah deadlock jika terjadi mutasi simultan.
- **Perintah verifikasi yang benar-benar dijalankan**:
  - `npm run build` (Sukses membangun aset lokal dalam 773ms)
  - `php vendor/bin/phpunit --testdox tests/Feature/ResidentTest.php` (37 tests, 37 passed, 185 assertions)
  - `php artisan test` (127 tests, 127 passed, 666 assertions, 0 failure)
  - Uji visual browser otomatis desktop 1366×768 dan mobile 390px pada `http://127.0.0.1:8000`:
    - Daftar penghuni Admin desktop (`01-admin-residents-index-desktop.png`)
    - Validasi form tambah penghuni (`02-admin-residents-create-validation.png`)
    - Detail penghuni Admin dengan kartu kredensial termaskir (`03-admin-resident-show-credentials.png`)
    - Edit penghuni Admin desktop (`04-admin-resident-edit-desktop.png`)
    - Modal konfirmasi reset password sementara (`05-admin-resident-reset-password-modal.png`)
    - Modal konfirmasi arsip penghuni (`06-admin-resident-archive-modal.png`)
    - Tampilan mobile 390px (`07-admin-residents-mobile-390px.png`)
    - Daftar penghuni Pemilik mode baca saja (`08-owner-residents-index-desktop.png`)
    - Detail penghuni Pemilik mode baca saja (`09-owner-resident-show-desktop.png`)
- **Hasil dan lokasi bukti**:
  - Walkthrough lengkap: [docs/evidence/t07-walkthrough.md](docs/evidence/t07-walkthrough.md)
  - 9 Berkas screenshot nyata: `docs/evidence/t07/`
  - Log rinci pengujian TC-07, TC-05, & TC-30: [docs/evidence/test-results.md](docs/evidence/test-results.md)
- **Hal yang belum diuji**:
  - Modul Master Fasilitas (T08: telah selesai), BillingService (T09), Mulai Penempatan (T10), Akhiri Penempatan (T11), dan modul tagihan bulanan (Fase 3: T12–T15).
- **Kendala tersisa**:
  - Tidak ada kendala teknis. Seluruh kriteria T07 terpenuhi dengan sempurna.
- **Cara menjalankan keadaan saat ini**:
  - Pastikan server aktif: `php artisan serve`
  - Jalankan test suite: `php artisan test`
  - Buka halaman Penghuni di browser: `http://127.0.0.1:8000/residents`
- **Task berikutnya**:
  - T08: SELESAI (lanjut ke T09).

### T08 - Master Fasilitas (Facility Management)
- **Tanggal**: 21 September 2026
- **Task dan status**: T08 SELESAI
- **Ringkasan perubahan**:
  - Mengembangkan Model `Facility` dengan relasi `room()` dan `complaints()`. Mengimplementasikan query scope `active()`, `archived()`, `search()`, `condition()`, `locationType()`, dan `room()`. Menambahkan accessor dan helper bebas N+1 `hasHistoricalReferences()`, `canBeDeleted()`, `hasActiveComplaints()`, `canBeArchived()`, `location_label`, `condition_label`, dan `condition_badge_class`.
  - Menyelaraskan Model `Room` dengan menambahkan method `isArchived(): bool` agar seragam dengan accessor `is_archived`.
  - Membangun `FacilityPolicy` dan menegakkannya di seluruh endpoint melalui `Gate::authorize()`: Admin memiliki wewenang penuh, Pemilik hanya baca (*read-only*), dan Penghuni dilarang penuh (**HTTP 403 Forbidden**).
  - Membuat `StoreFacilityRequest` dan `UpdateFacilityRequest` dengan penegakan otorisasi dini di `authorize()` sebelum validasi aturan (role tanpa izin langsung memperoleh HTTP 403 bahkan saat mengirim payload invalid). Memvalidasi keunikan kode case-insensitive (`LOWER(code)`), panjang nama 2–100 karakter, kondisi valid (`good`, `broken`, `repairing`), penegakan aturan CHECK constraint lokasi (`chk_facilities_location_rule`), penolakan kamar arsip untuk penempatan fasilitas baru/pindah, dan pembuangan field `archived_at` dari payload.
  - Menegakkan imutabilitas lokasi fasilitas berhistori keluhan: Jika fasilitas pernah direferensikan keluhan, pembaruan lokasi (room→room, room→shared, shared→room, perubahan area_name) ditolak secara tegas demi integritas data riwayat teknis. Pembaruan non-lokasi (nama, kondisi, catatan) tetap diizinkan dan ditangani aman terhadap elemen input yang di-disable oleh browser.
  - Mengizinkan fasilitas eksisting di kamar yang kemudian diarsipkan untuk memperbarui data non-lokasi tanpa dipaksa pindah kamar.
  - Membangun service transaksional `FacilityService` dengan penguncian baris konsisten (`Facility` lalu target `Room`), verifikasi histori keluhan dan status kamar arsip di dalam transaksi, serta integrasi audit log (`AuditService`, module: `facilities`). Kegagalan audit terbukti membatalkan seluruh mutasi (rollback penuh).
  - Menerapkan aksi arsip dan buka arsip yang idempoten tanpa mutasi atau audit ganda jika status sudah sesuai.
  - Membangun antarmuka Blade responsif berbasis Bootstrap 5 lokal: `facilities/index.blade.php` (tab Aktif, Diarsipkan, Semua; filter kondisi dan lokasi; tombol aksi dengan alasan tindakan tidak tersedia yang terbaca langsung pada layar desktop dan mobile; modal konfirmasi Bootstrap 5), `create.blade.php` (toggle interaktif vanilla JS kamar vs area bersama), `edit.blade.php` (alert edukasi penguncian lokasi), dan `show.blade.php` (kartu data spesifikasi, kartu riwayat keluhan terkait nyata, dan panduan kebijakan integritas data).
- **File utama**:
  - `app/Models/Facility.php`
  - `app/Models/Room.php`
  - `app/Policies/FacilityPolicy.php`
  - `app/Services/FacilityService.php`
  - `app/Http/Requests/Facility/StoreFacilityRequest.php`
  - `app/Http/Requests/Facility/UpdateFacilityRequest.php`
  - `app/Http/Controllers/FacilityController.php`
  - `routes/web.php`
  - `resources/views/layouts/partials/sidebar-nav.blade.php`
  - `resources/views/facilities/index.blade.php`
  - `resources/views/facilities/create.blade.php`
  - `resources/views/facilities/edit.blade.php`
  - `resources/views/facilities/show.blade.php`
  - `tests/Feature/FacilityTest.php`
- **Keputusan/asumsi yang berubah dan dampaknya**:
  - *Penanganan Elemen Disabled*: Input form lokasi yang di-disable oleh browser pada fasilitas berhistori keluhan tidak dikirim ke server. Server menangani secara eksplisit nilai kanonikal dari basis data sehingga field non-lokasi dapat diperbarui dengan lancar.
  - *Toleransi Kamar Arsip Eksisting*: Fasilitas yang sudah berada di kamar arsip tidak dipaksa pindah saat Admin hanya mengedit nama, kondisi, atau catatan. Dropdown edit menyertakan label `[Kamar Diarsipkan]` untuk kamar tersebut.
  - *Alasan Tindakan Terbaca Langsung*: Alasan mengapa tombol Arsip atau Hapus tidak tersedia ditampilkan sebagai teks badge yang terlihat langsung di bawah tombol pada tabel dan kartu detail, memastikan pengguna mobile dapat membacanya tanpa mengandalkan hover mouse.
- **Perintah verifikasi yang benar-benar dijalankan**:
  - `npm run build` (Sukses membangun aset lokal dalam 1.02s)
  - `Test-Path "public/hot"` (Hasil: `False`, verifikasi aset build lokal)
  - `php artisan view:cache` (Hasil: `Blade templates cached successfully`)
  - `php vendor/bin/phpunit --testdox tests/Feature/FacilityTest.php` (40 tests, 40 passed, 277 assertions)
  - `php artisan test` (167 tests, 167 passed, 943 assertions, 0 failure)
  - Uji visual browser otomatis desktop 1366×768 dan mobile 390px pada `http://127.0.0.1:8000`:
    - Daftar fasilitas Admin desktop (`01-admin-facilities-index-desktop.png`)
    - Validasi form tambah fasilitas (`02-admin-facilities-create-validation.png`)
    - Notifikasi sukses tambah fasilitas (`03-admin-facility-create-success.png`)
    - Detail fasilitas Admin desktop (`04-admin-facility-show-desktop.png`)
    - Edit fasilitas Admin desktop (`05-admin-facility-edit-desktop.png`)
    - Modal konfirmasi arsip fasilitas (`06-admin-facility-archive-modal.png`)
    - Tab fasilitas diarsipkan (`07-admin-facilities-archived-tab.png`)
    - Tampilan mobile 390px (`08-admin-facilities-mobile-390px.png`)
    - Daftar fasilitas Pemilik mode baca saja (`09-owner-facilities-index-desktop.png`)
    - Detail fasilitas Pemilik mode baca saja (`10-owner-facility-show-desktop.png`)
- **Hasil dan lokasi bukti**:
  - Walkthrough lengkap: [docs/evidence/t08-walkthrough.md](docs/evidence/t08-walkthrough.md)
  - 10 Berkas screenshot nyata: `docs/evidence/t08/`
  - Log rinci pengujian TC-08 & TC-09: [docs/evidence/test-results.md](docs/evidence/test-results.md)
- **Koreksi Penutupan T08 yang Diterapkan**:
  - Pengetatan validasi tipe input pada `StoreFacilityRequest` dan `UpdateFacilityRequest` dengan aturan `bail` dan pengecekan tipe sebelum callback `trim()`, `strtolower()`, casting, atau `Room::find()`.
  - Input bertipe array (pada `code`, `room_id`, `location_type`, dan `area_name`) menghasilkan error validasi 422 terkendali, bukan error fatal HTTP 500.
  - Input invalid tidak diubah/dikonversi menjadi nilai kanonikal lalu dilaporkan berhasil: input array pada fasilitas berhistori ditolak tegas dan tidak mengubah data maupun menambah audit.
  - Form lokasi yang disabled tetap didukung (field yang tidak dikirim oleh browser mempertahankan nilai lokasi lama dari database), sedangkan payload yang dikirim divalidasi ketat.
  - Ditambahkan 3 regression test di `FacilityTest.php` untuk create dan update dengan input array (total 40 tests, 277 assertions).
- **Hal yang belum diuji**:
  - Modul Keluhan mandiri pada portal penghuni (dijadwalkan pada Fase 4: T16–T17).
  - Modul BillingService (T09: periode bulan kalender, tarif penuh tanpa prorata, snapshot agreed_monthly_rate penempatan), Mulai Penempatan (T10), dan Akhiri Penempatan (T11).
- **Kendala tersisa**:
  - Tidak ada kendala teknis. Seluruh kriteria T08 terpenuhi dengan sempurna.
- **Cara menjalankan keadaan saat ini**:
  - Pastikan server aktif: `php artisan serve`
  - Jalankan test suite: `php artisan test`
  - Buka halaman Fasilitas di browser: `http://127.0.0.1:8000/facilities`
- **Task berikutnya**:
  - T09: SELESAI (lanjut ke T10).

### T09 - BillingService (Fondasi Tagihan & Sinkronisasi Idempoten)
- **Tanggal**: 21 September 2026
- **Task dan status**: T09 SELESAI
- **Ringkasan perubahan**:
  - Membangun service inti `BillingService` (`app/Services/BillingService.php`) dengan fungsi lengkap:
    - Normalisasi tanggal bisnis `Asia/Jakarta` (`parseModelDate`) dari kolom `DATE` Eloquent/MySQL tanpa memutasi objek model penempatan dan bebas distorsi offset jam.
    - Kontrak tanggal pasti pada `getRequiredPeriods`: mengembalikan array kosong `[]` jika penempatan mulai setelah tanggal acuan (termasuk pada bulan kalender yang sama) atau jika `maxPeriodMonth` sebelum tanggal mulai; format tanggal invalid melempar `InvalidArgumentException`.
    - Larangan penerbitan invoice masa depan pada `resolveReferenceDate`: tanggal acuan masa depan ditolak dengan `InvalidArgumentException`, dan verifikasi pengujian dilakukan dengan membekukan waktu server via `Carbon::setTestNow()`.
    - Perhitungan jatuh tempo `calculateDueDate`: jatuh tempo default tanggal 5 (`YYYY-MM-05`), kecuali periode pertama jika `started_on->day > 5` maka `due_on = started_on`. Menolak periode di luar rentang masa sewa yang sah.
    - Pembentukan payload tagihan `calculateInvoicePayload`: memastikan nominal tagihan bersumber dari snapshot `agreed_monthly_rate` penempatan (bukan tarif kamar fisik terbaru), `period_month` selalu tanggal 1 (`YYYY-MM-01`), serta validasi kelayakan periode langsung (menolak penempatan belum mulai per tanggal acuan, periode masa depan setelah bulan berjalan, dan periode di luar masa tinggal).
    - Otorisasi terverifikasi `ensureActorIsAdmin`: memverifikasi keberadaan aktor di database dan membaca ulang status aktif/peran langsung dari basis data, kebal terhadap manipulasi objek in-memory yang sudah dinonaktifkan atau didemosi.
    - Operasi murni baca-saja `previewPlacement`, `checkCoverage`, dan `checkGlobalCoverage` (memeriksa seluruh penempatan termasuk yang sudah selesai/ended jika memiliki histori invoice yang hilang) tanpa melakukan mutasi database atau pencatatan audit log.
    - Sinkronisasi transaksional atomik `syncPlacementInvoices` dengan otorisasi ketat (hanya Admin aktif, melempar `AuthorizationException` jika tidak sah), penguncian baris (`lockForUpdate`), integrasi audit log (`module: 'invoices'`, action: `'create'`), dan pelemparan kembali exception agar transaksi induk (T10/T11) dapat rollback utuh.
    - Pemrosesan batch `syncAllPlacements` dengan tanggal acuan seragam yang dibekukan di awal batch, isolasi kegagalan per penempatan (penempatan lain tetap commit mandiri bila tanpa transaksi luar), dan pesan kegagalan aman tanpa kebocoran raw SQL, kredensial, atau stack trace.
  - Memperbarui model `Placement` (`app/Models/Placement.php`) dengan query scope `scopeActive()`, `scopeEnded()`, dan helper method `isActive()`.
  - Membangun test suite komprehensif `tests/Feature/BillingServiceTest.php` mencakup 19 metode pengujian (150 assertions) yang memverifikasi seluruh skenario TC-12, TC-13, TC-33, batas konkurensi database via constraint `UNIQUE(placement_id, period_month)`, imutabilitas tarif dan snapshot, isolasi batch, otorisasi aktor tersimpan/usang, serta rollback atomik.
- **File utama**:
  - `app/Services/BillingService.php`
  - `app/Models/Placement.php`
  - `tests/Feature/BillingServiceTest.php`
  - `docs/evidence/t09-walkthrough.md`
  - `docs/evidence/test-results.md`
- **Keputusan/asumsi yang berubah dan dampaknya**:
  - *Normalisasi Offset UTC ke Asia/Jakarta*: Kolom `DATE` MySQL yang di-cast menjadi `Carbon` oleh Eloquent memiliki offset `00:00:00 UTC`. Helper `parseModelDate()` mengekstrak string `'Y-m-d'` sebelum membuat instance `Carbon` di `Asia/Jakarta`, memastikan komparasi tanggal `startOfDay()` tidak terdistorsi oleh selisih 7 jam.
  - *Integrasi Constraint End Metadata*: Pembuatan fixture penempatan yang telah selesai (`ended_on`) diwajibkan menyertakan `ended_by` dan `end_reason` sesuai CHECK constraint database `chk_placements_end_metadata`.
  - *Batas Bukti Konkurensi & Transaksi Batch*: Pengujian otomatis dijalankan secara sekuensial pada satu koneksi PHPUnit. Perlindungan konkurensi di produksi dijamin oleh `lockForUpdate()` dan constraint MySQL composite unique `invoices_placement_id_period_month_unique`. Isolasi commit mandiri per penempatan pada `syncAllPlacements` ditegaskan hanya berlaku tanpa transaksi induk pembungkus.
- **Perintah verifikasi yang benar-benar dijalankan**:
  - `php vendor/bin/phpunit --testdox tests/Feature/BillingServiceTest.php` (Hasil: 19 tests, 19 passed, 150 assertions)
  - `php artisan test` (Hasil: 186 tests, 186 passed, 1093 assertions, 0 failure)
- **Hasil dan lokasi bukti**:
  - Walkthrough lengkap: [docs/evidence/t09-walkthrough.md](docs/evidence/t09-walkthrough.md)
  - Log rinci pengujian TC-12, TC-13, & TC-33: [docs/evidence/test-results.md](docs/evidence/test-results.md)
- **Hal yang belum diuji**:
  - Modul Mulai Penempatan / Check-in kamar baru (T10).
  - Modul Akhiri Penempatan / Check-out kamar (T11).
  - Antarmuka pengguna (UI/Blade/Controller) daftar invoice dan tombol sinkronisasi tagihan (T12).
- **Kendala tersisa**:
  - Tidak ada kendala teknis. Seluruh kriteria bisnis dan arsitektur T09 terpenuhi dengan sempurna.
- **Cara menjalankan keadaan saat ini**:
  - Pastikan server aktif: `php artisan serve`
  - Jalankan test suite BillingService: `php vendor/bin/phpunit --testdox tests/Feature/BillingServiceTest.php`
  - Jalankan test suite penuh: `php artisan test`
- **Task berikutnya**:
  - T10: SELESAI (lanjut ke T11).

### T10 - Mulai Penempatan (Check-in / New Placement)
- **Tanggal**: 21 September 2026
- **Task dan status**: T10 SELESAI
- **Ringkasan perubahan**:
  - Membangun service layer penempatan `PlacementService` (`app/Services/PlacementService.php`):
    - `previewPlacement`: operasi murni *read-only* (0 insert, 0 update, 0 delete, 0 audit log), validasi kelayakan kamar tidak diarsipkan dan kosong, validasi penghuni aktif belum memiliki penempatan, deteksi riwayat penempatan selesai di bulan yang sama (`same_month_warning`), kalkulasi tanggal mulai WIB hari ini dan proyeksi tagihan pertama via `BillingService::calculateDueDate` (in-memory), pembuatan session preview token aman (`placement_preview_{token}`) yang mengikat admin, kamar, penghuni, tarif, dan masa kedaluwarsa 15 menit.
    - `startPlacement`: verifikasi token sesi preview, transaksi atomik `DB::transaction()`, penguncian pesimistik berurutan $\text{Room} \rightarrow \text{Resident} \rightarrow \text{User}$ (`lockForUpdate`), penentuan satu tanggal bisnis tunggal WIB pasca-lock, deteksi drift tarif dan pergantian tanggal operasional pasca-lock, pembuatan baris `Placement` dengan tarif otoritatif kamar fisik saat lock, penerbitan invoice periode pertama atomik via `BillingService::syncPlacementInvoices`, pencatatan dual audit log atomik (`placements` dan `invoices`), penanganan MySQL error 1062 pada unique index active placement, dan invalidasi token sesi preview.
  - Membangun Form Request ketat `PreviewPlacementRequest` dan `StorePlacementRequest`: otorisasi Admin via `Gate::allows('create')` sebelum validasi, validasi integer ketat dengan `bail` anti-array injection, penolakan dan pembersihan field manipulatif (`started_on`, `agreed_monthly_rate`, `created_by`, `status`).
  - Membangun controller dan routing penempatan `PlacementController` (`app/Http/Controllers/PlacementController.php`) dan `routes/web.php`:
    - `index`: filter pencarian, filter status (Aktif, Selesai, Semua), pagination, eager loading anti-N+1 (`resident.user`, `room`, `creator`, `ender`, `withCount('invoices')`).
    - `create`: muat kamar kosong aktif dan penghuni tanpa penempatan.
    - `preview`: pemanggilan preview dan response JSON 200/422 terkendali (anti-500).
    - `store`: eksekusi penempatan atomik dan redirect PRG dengan flash message.
    - `show`: detail penempatan dengan eager loading `validPayment` pada invoice (bebas N+1) secara *read-only* (tanpa mutasi pembayaran atau pengakhiran sewa).
  - Membangun dan menyempurnakan Blade Views responsif:
    - `resources/views/placements/create.blade.php`: dropdown kamar & penghuni, tombol preview, modal konfirmasi Bootstrap 5 aman XSS (`textContent`), penanganan respons preview lama dengan pembatalan request in-flight via `AbortController`, penolakan data basi via pelacakan sequence (`requestSequence`) dan kesesuaian ID entitas sebelum dan sesudah `response.json()`, pemulihan loading state instan saat pilihan berubah, tombol konfirmasi default disabled, serta pencegahan double submit.
    - `resources/views/placements/index.blade.php` & `show.blade.php`: tabel penempatan dengan badge status, filter tab, rincian kontrak, dan invoice periode pertama.
    - `resources/views/layouts/partials/sidebar-nav.blade.php`: aktivasi menu "Penempatan" (Admin) dan "Data Penempatan" (Pemilik).
  - Memperbarui model `Resident` (`app/Models/Resident.php`) dengan method `isArchived(): bool`.
  - Membangun test suite `tests/Feature/PlacementTest.php` mencakup 18 metode pengujian (153 assertions) yang memverifikasi seluruh skenario TC-10 & TC-11, termasuk 3 skenario rollback terpisah: kegagalan BillingService, kegagalan audit invoice, dan kegagalan audit placement tahap akhir dengan BillingService nyata.
- **File utama**:
  - `app/Policies/PlacementPolicy.php`
  - `app/Services/PlacementService.php`
  - `app/Http/Requests/Placement/PreviewPlacementRequest.php`
  - `app/Http/Requests/Placement/StorePlacementRequest.php`
  - `app/Http/Controllers/PlacementController.php`
  - `resources/views/placements/index.blade.php`
  - `resources/views/placements/create.blade.php`
  - `resources/views/placements/show.blade.php`
  - `routes/web.php`
  - `resources/views/layouts/partials/sidebar-nav.blade.php`
  - `tests/Feature/PlacementTest.php`
  - `docs/evidence/t10-walkthrough.md`
  - `docs/evidence/test-results.md`
- **Keputusan/asumsi yang berubah dan dampaknya**:
  - *Server-Verified Preview Token*: Token preview sesi server menggantikan payload client murni, menjamin bahwa penempatan yang dieksekusi benar-benar telah melalui persetujuan Administrator atas tarif dan tanggal mulai yang sah.
  - *Pembatalan Fetch Asinkron & Invalidasi State*: Saat admin mengubah dropdown kamar/penghuni ketika proses preview masih memuat, fetch sebelumnya dibatalkan seketika via `AbortController`, `preview_token` dikosongkan, tombol submit modal dinonaktifkan, dan UI loading segera pulih. Respons jaringan yang terlambat tidak akan membuka modal untuk kombinasi entitas yang sudah diganti.
  - *Pemisahan Pengujian Rollback 3 Skenario*: Pengujian rollback atomik dipisahkan menjadi 3 skenario eksplisit untuk membuktikan bahwa rollback terjadi pada tahap awal (BillingService), tahap tengah (audit invoice), maupun tahap akhir (audit placement setelah invoice dan audit invoice tersimpan dalam transaksi).
- **Perintah verifikasi yang benar-benar dijalankan**:
  - `php vendor/bin/phpunit --testdox tests/Feature/PlacementTest.php` (Hasil: 18 tests, 18 passed, 153 assertions)
  - `php artisan test` (Hasil: 204 tests, 204 passed, 1246 assertions, 0 failure)
  - Verifikasi browser riil dengan respons diperlambat (3 detik): pembatalan fetch lama, pemulihan tombol preview, dan render akurat pada request baru terekam di `07-preview-abort-and-recovery.png`.
- **Hasil dan lokasi bukti**:
  - Walkthrough lengkap: [docs/evidence/t10-walkthrough.md](docs/evidence/t10-walkthrough.md)
  - Log rinci pengujian TC-10 & TC-11: [docs/evidence/test-results.md](docs/evidence/test-results.md)
  - Bukti tangkapan layar visual: [docs/evidence/t10/](docs/evidence/t10/) (7 tangkapan layar)
- **Hal yang belum diuji**:
  - Antarmuka daftar tagihan dan sinkronisasi manual penagihan (T12).
  - Pencatatan pembayaran tunai/transfer (T13).
- **Kendala tersisa**:
  - Tidak ada kendala teknis. Seluruh kriteria bisnis, koreksi penutupan, dan verifikasi antarmuka visual T10 terpenuhi dengan sempurna.
- **Cara menjalankan keadaan saat ini**:
  - Pastikan server aktif: `php artisan serve`
  - Jalankan test suite Placement: `php vendor/bin/phpunit --testdox tests/Feature/PlacementTest.php`
  - Jalankan test suite penuh: `php artisan test`
  - Buka halaman Penempatan di browser: `http://127.0.0.1:8000/placements`
- **Task berikutnya**:
  - T11: SELESAI (lanjut ke T12).

### T11 - Akhiri Penempatan (Check-out / Pengakhiran Kontrak Sewa)
- **Tanggal**: 23 September 2026
- **Task dan status**: T11 SELESAI
- **Ringkasan perubahan**:
  - Membangun otorisasi kebijakan `end(User $user, Placement $placement): bool` pada `app/Policies/PlacementPolicy.php` yang memverifikasi wewenang Administrator aktif, dengan pemisahan status bisnis penempatan (penolakan bisnis yang ramah ditangani service layer, bukan 403).
  - Mengembangkan service layer `PlacementService` (`app/Services/PlacementService.php`):
    - `computeFinancialSnapshot`: Helper murni in-memory (0 kueri basis data) untuk kalkulasi snapshot finansial dan signature SHA-256 yang menerima collection invoice terkunci dan periode wajib dari `BillingService::getRequiredPeriods()`. Mengurutkan invoice berdasarkan ID (`sortBy('id')`), membentuk token material invoice, menghitung `missing_periods` in-memory, serta menyusun signature deterministik tanpa mencampurkan hasil *locking read* dengan *consistent read* biasa yang berpotensi memakai snapshot MVCC usang pada MySQL isolasi `REPEATABLE READ`.
    - `buildMaterialFinancialSnapshot`: Helper pengambil data khusus tahap pratinjau yang mendelegasikan kalkulasi ke `computeFinancialSnapshot`.
    - `previewEndPlacement`: Memverifikasi Admin aktif dan penempatan aktif, menetapkan satu tanggal bisnis `Asia/Jakarta`, menghitung snapshot finansial murni *read-only* (0 mutasi database), menyimpan token sesi terverifikasi server `placement_end_preview_{token}` selama 15 menit, dan mengembalikan payload tampilan modal.
    - `endPlacement`: Memvalidasi token preview dari session, membungkus seluruh mutasi dalam transaksi database `DB::transaction`, menerapkan penguncian baris pesimistik terurut `Room` $\rightarrow$ `Resident` $\rightarrow$ `User` $\rightarrow$ `Placement` $\rightarrow$ `Invoice` $\rightarrow$ `Payment`, memasang relasi payment valid terkunci, mengevaluasi ulang status penempatan di bawah lock, menetapkan tanggal bisnis sekali, memverifikasi ketiadaan drift tanggal bisnis kalender dan drift material snapshot finansial pasca-lock via `computeFinancialSnapshot`, menyinkronkan invoice hilang sampai bulan keluar penuh tanpa prorata via `BillingService::syncPlacementInvoices`, memperbarui `ended_on`, `ended_by`, `end_reason` (terpangkas spasi), mencatat audit log atomik pada modul `placements` action `end`, dan mendaftarkan pembersihan token sesi via `DB::afterCommit` pada transaksi terluar.
  - Membangun Form Request `PreviewEndPlacementRequest` dan `StoreEndPlacementRequest` pada `app/Http/Requests/Placement/`:
    - `PreviewEndPlacementRequest`: Otorisasi via `Gate::allows('end', $placement)`.
    - `StoreEndPlacementRequest`: Otorisasi via policy, pemangkasan spasi `end_reason` di `prepareForValidation` sebelum aturan `min:5` dan `max:255` sehingga input spasi murni ditolak, validasi `preview_token`, serta sanitasi ketat field yang divalidasi.
  - Menambahkan method controller `endPreview` dan `end` pada `app/Http/Controllers/PlacementController.php` dan mendaftarkan rute web `placements.end-preview` dan `placements.end` pada `routes/web.php`.
  - Memperbarui tampilan Blade `resources/views/placements/show.blade.php`:
    - Menambahkan tombol "Akhiri Penempatan" yang hanya tampil jika Administrator berwenang DAN penempatan masih aktif (`@if($placement->isActive() && auth()->user()->can('end', $placement))`).
    - Modal konfirmasi `#endPlacementModal` interaktif: ringkasan penghuni & kamar, tarif kontrak, ketentuan tarif penuh bulan keluar tanpa prorata, ringkasan invoice eksisting dan invoice baru yang akan terbit, peringatan kewajiban tertunggak, textarea alasan dengan penghitung karakter dinamis, proteksi pembatalan fetch via `AbortController` dan `requestSequence`, serta render teks aman XSS via `textContent`.
  - Membangun test suite komprehensif TC-14 pada `tests/Feature/PlacementEndTest.php` mencakup 25 metode pengujian (174 assertions):
    1. Pratinjau pengakhiran read-only (0 mutasi database).
    2. Eksekusi pengakhiran atomik dengan invoice keluar dan audit log.
    3. Pembebasan kamar instan via status hunian turunan.
    4. Integrasi penempatan ulang T10 bagi penghuni yang telah keluar.
    5. Penolakan ramah bagi penempatan yang sudah selesai.
    6. Matriks otorisasi (Admin aktif diizinkan, Owner/Resident/Admin nonaktif ditolak).
    7. Validasi alasan pangkas spasi, batas panjang, dan penolakan array anti-500.
    8. Retensi error hanya pada input `end_reason`.
    9. Deteksi drift tanggal operasional bisnis melintasi tengah malam.
    10. Deteksi drift penambahan invoice pasca-preview.
    11. Deteksi drift pencatatan payment valid pasca-preview.
    12. Deteksi drift pembatalan/void payment valid pasca-preview.
    13. Konsistensi signature deterministik bebas pengaruh urutan kueri.
    14. Integritas status aktif/nonaktif akun penghuni yang tidak berubah pasca-checkout.
    15. Pelestarian seluruh histori tagihan dan kewajiban belum lunas.
    16. Pembuktian rollback atomik total pada kegagalan audit placement tahap akhir dengan BillingService nyata (invoice dan audit invoice sempat tersimpan lalu di-rollback bersih).
    17. Submit ulang idempoten tanpa mengubah histori yang sudah selesai.
    18. Pengabaian manipulasi payload finansial dari browser.
    19. Kondisi render antarmuka tombol dan modal.
    20. Penolakan token preview kedaluwarsa (>15 menit).
    21. Pembuktian rollback atomik total saat BillingService gagal pada pengakhiran (exception expected, penempatan aktif, invoice/audit baseline, token dipertahankan).
    22. Pembuktian rollback atomik total saat audit invoice gagal setelah invoice mulai dibuat (exception expected, penempatan aktif, invoice/audit baseline, token dipertahankan).
    23. Integrasi: keluar tanggal 1 tetap ditagih penuh sebulan (tanpa prorata 1 hari).
    24. Integrasi: mulai dan keluar pada hari yang sama tidak menggandakan invoice.
    25. Integrasi: celah invoice di tengah rentang dilengkapi otomatis tanpa menerbitkan invoice setelah bulan keluar.
- **File utama**:
  - `app/Policies/PlacementPolicy.php`
  - `app/Services/PlacementService.php`
  - `app/Http/Requests/Placement/PreviewEndPlacementRequest.php`
  - `app/Http/Requests/Placement/StoreEndPlacementRequest.php`
  - `app/Http/Controllers/PlacementController.php`
  - `app/Models/Room.php`
  - `app/Models/Invoice.php`
  - `routes/web.php`
  - `resources/views/placements/show.blade.php`
  - `tests/Feature/PlacementEndTest.php`
  - `docs/evidence/t11-walkthrough.md`
  - `docs/evidence/test-results.md`
  - `docs/evidence/t11/`
- **Keputusan/asumsi yang berubah dan dampaknya**:
  - *Pemisahan Snapshot dari Kueri Pasca-Lock*: Helper `computeFinancialSnapshot` mengevaluasi koleksi baris terkunci di memori tanpa kueri database. Menghilangkan risiko pembacaan snapshot MVCC usang pada MySQL `REPEATABLE READ`.
  - *Verifikasi Isolasi Aktual*: Tingkat isolasi database diverifikasi via `SELECT @@transaction_isolation;` bernilai `REPEATABLE-READ`. Batasan pengujian satu koneksi dicatat secara transparan.
  - *Penghapusan Token Sesi Pasca-Commit Terluar*: Token preview sesi didaftarkan via `DB::afterCommit`. Menjamin pembersihan hanya terjadi ketika transaksi terluar commit, dan token tetap aman bila terjadi rollback.
  - *Penjelasan Hierarki Kunci*: Menghapus klaim bebas deadlock dan menjelaskan kompatibilitas urutan kunci aktual: `Room` $\rightarrow$ `Resident` $\rightarrow$ `User` $\rightarrow$ `Placement` $\rightarrow$ `Invoice` $\rightarrow$ `Payment`.
  - *Pemisahan Otorisasi dari Status Bisnis*: Policy `end` hanya memeriksa hak Admin aktif. Penempatan yang sudah berstatus selesai ditangani service dengan penolakan bisnis yang ramah ("Penempatan ini sudah berstatus selesai..."), bukan HTTP 403.
- **Perintah verifikasi yang benar-benar dijalankan**:
  - `php vendor/bin/phpunit --testdox tests/Feature/PlacementEndTest.php` (Hasil: 25 tests, 25 passed, 174 assertions, 0 failure)
  - `php artisan test` (Hasil: 229 tests, 229 passed, 1420 assertions, 0 failure)
  - `npm run build` (Hasil: Sukses kompilasi CSS & JS Vite production bundle)
  - Verifikasi browser riil (Desktop & Mobile 390x844) terekam di `docs/evidence/t11/`.
- **Hasil dan lokasi bukti**:
  - Walkthrough lengkap: [docs/evidence/t11-walkthrough.md](docs/evidence/t11-walkthrough.md)
  - Log rinci pengujian TC-14: [docs/evidence/test-results.md](docs/evidence/test-results.md)
  - Bukti tangkapan layar visual: [docs/evidence/t11/](docs/evidence/t11/) (4 tangkapan layar desktop & mobile)
- **Hal yang belum diuji**:
  - Antarmuka daftar tagihan dan sinkronisasi manual penagihan (T12).
  - Pencatatan pembayaran tunai/transfer (T13).
- **Kendala tersisa**:
  - Tidak ada kendala teknis. Seluruh kriteria bisnis, koreksi penutupan, dan verifikasi antarmuka visual T11 terpenuhi dengan sempurna.
- **Cara menjalankan keadaan saat ini**:
  - Pastikan server aktif: `php artisan serve`
  - Jalankan test suite Placement End: `php vendor/bin/phpunit --testdox tests/Feature/PlacementEndTest.php`
  - Jalankan test suite penuh: `php artisan test`
  - Buka halaman Penempatan di browser: `http://127.0.0.1:8000/placements`
- **Task berikutnya**:
  - T12: Halaman Tagihan & Sinkronisasi Manual (Billing Management).








