# Progres SIM Kos

## Status terkini

- **Progres Implementasi**: Task T01, T02, T03, T04, dan T05 telah SELESAI. Task T06–T24 belum dimulai.
- **Environment**: PHP 8.3.32 (cli), Composer 2.10.2, Node v24.18.0, npm 11.16.0, Git 2.55.0, MySQL 8.0.30 (Laragon). Ekstensi `intl` telah aktif.
- **Pemisahan Status Database Nyata**:
  - **Database Uji (`sim_kos_test`)**: Dimigrasikan penuh (12 migrasi) dan di-seed dengan `RoleSeeder` dan `UserSeeder`. Dilindungi oleh database guard pada `tests/TestCase.php`. Terbukti lulus seluruh pengujian otomatis: TC-31 (18 tests, 86 assertions), TC-24/TC-32 fondasi (10 tests, 46 assertions), TC-01–TC-05 (24 tests, 113 assertions), TC-29 (6 tests, 49 assertions), total suite 60 tests (296 assertions).
  - **Database Aplikasi (`sim_kos`)**: Diverifikasi koneksi aktual dan tabel awal (0 tabel), lalu dimigrasikan normal melalui `php artisan migrate` (12 migrasi `Ran` tanpa reset) dan di-seed dengan `DatabaseSeeder` (`RoleSeeder` dan `UserSeeder` idempoten). Akun demo tersimpan aman dengan password dari variabel `.env` lokal tanpa hardcoding dan tanpa fallback string di kode sumber.
- **Automated Tests**: 60 passed, 296 assertions (0 failure) pada suite pengujian `sim_kos_test`.
- **Frontend**: Layout bersama Bootstrap 5 lokal via Vite/npm, navigasi role terproteksi (Admin, Pemilik, Penghuni), navigasi terbatas password sementara, aset offline terkompilasi (CSS 236 kB, JS 79 kB tanpa Vite dev server / `public/hot`), visualisasi login/dashboard/ganti password responsif desktop 1366×768 & mobile 390px, pengujian keyboard offcanvas (buka, Escape, fokus kembali).

## Langkah berikut

Task T05 telah selesai secara penuh. Langkah berikutnya adalah mempersiapkan implementasi Task **T06: Master Kamar** (CRUD kamar, nomor kamar unik, tarif bulanan, status kamar terisi/kosong; TC-06).

## Tabel progres

| Fase | Task | Status | Bukti |
|---|---|---|---|
| 1 Fondasi | T01 | Selesai | docs/evidence/t01-welcome-page.png, log pengujian skeleton |
| 1 Fondasi | T02 | Selesai | docs/evidence/test-results.md (TC-31: 18 tests, 86 assertions) |
| 1 Fondasi | T03 | Selesai | docs/audit-service.md, docs/evidence/test-results.md (TC-24 & TC-32 Fondasi: 10 tests, 46 assertions) |
| 1 Fondasi | T04 | Selesai | docs/evidence/test-results.md (TC-01–TC-05: 24 tests, 113 assertions) |
| 1 Fondasi | T05 | Selesai | docs/evidence/t05-walkthrough.md, docs/evidence/t05/ (13 screenshot nyata), test-results.md (TC-29: 6 tests, 49 assertions) |
| 2 Data/penempatan | T06–T11 | Belum dimulai | — |
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
  - T06: Master Kamar (CRUD kamar, nomor kamar unik, tarif bulanan, status kamar terisi/kosong; TC-06).


