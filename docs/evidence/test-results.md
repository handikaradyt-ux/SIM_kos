# Hasil Pengujian SIM Kos

Dokumen ini merekam hasil aktual pengujian otomatis dan manual Sistem Informasi Manajemen Kos pada database nyata MySQL `sim_kos_test`.

## Ringkasan Eksekusi

- **Lingkungan**: Windows 10/11, PHP 8.3.32, MySQL 8.0.30 (Laragon)
- **Database Uji**: `sim_kos_test`
- **Driver**: MySQL PDO (`mysql`, bukan SQLite)
- **Safety Guard**: `tests/TestCase.php` menghentikan proses jika koneksi bukan `sim_kos_test`

---

## Tabel Hasil Test Case

| **TC-01** | Autentikasi multi-role (Admin, Owner, Resident), pesan kegagalan generik kredensial salah, dan penolakan akun nonaktif. | Sukses login untuk ketiga role diarahkan ke rute masing-masing. Password salah atau user tidak ada mengembalikan pesan generik 'Email atau password salah.' tanpa membocorkan eksistensi akun. User nonaktif ditolak saat login dengan pesan generik yang sama dan tidak meninggalkan session aktif. | 19 September 2026 | **LULUS** | `tests/Feature/AuthTest.php` (5 tests spesifik TC-01) |
| **TC-02** | Keamanan sesi dan logout: invalidasi session, regenerasi token CSRF, proteksi akses balik, dan pembatasan rate limiting (throttle). | Logout via POST mengakhiri sesi, membersihkan session data, meregenerasi CSRF token, dan mengarahkan kembali ke login. Upaya login dibatasi (throttled) setelah 5 kegagalan berturut-turut dalam 1 menit dengan HTTP 302 redirect dan pesan validasi throttle ('Terlalu banyak percobaan login...'). | 19 September 2026 | **LULUS** | `tests/Feature/AuthTest.php` (2 tests spesifik TC-02) |
| **TC-03** | Otorisasi role: Owner & Resident dilarang mengakses dashboard atau aksi mutasi Admin (HTTP 403 Forbidden). | User dengan role owner dan resident menerima HTTP 403 saat mencoba mengakses dashboard admin atau endpoint mutasi dummy admin. Admin dapat mengakses rute dan mutasi tersebut secara penuh. | 19 September 2026 | **LULUS** | `tests/Feature/AuthTest.php` (3 tests spesifik TC-03) |
| **TC-04** | Fondasi Kebijakan Akses & Kepemilikan Data Profil (`ResidentPolicy`): Pemisahan view dan mutasi, isolasi data penghuni lain, penanganan user tanpa profil resident. | Resident hanya dapat melihat profil miliknya sendiri dan menerima HTTP 403 saat mencoba melihat profil penghuni lain. Owner memiliki hak baca-saja (*read-only*) untuk seluruh profil. User tanpa profil resident ditangani secara *null-safe* tanpa memicu error 500. *(Catatan: Pengujian kepemilikan penuh entitas bisnis seperti invoice/pembayaran/keluhan dilanjutkan saat modul-modul tersebut dibangun).* | 19 September 2026 | **LULUS (Fondasi)** | `tests/Feature/AuthTest.php` (4 tests spesifik TC-04) |
| **TC-05** | Akun nonaktif pasca-login, penegakan ganti password sementara, validasi password, atomisitas transaksi ganti password, dan asimetri audit login/logout. | 1. Sesi pengguna aktif langsung dicabut pada request berikutnya setelah status akun diubah menjadi nonaktif di database.<br>2. Pengguna dengan `must_change_password = true` dipaksa ke rute `/password/change` dan dikecualikan dari loop redirect; rute logout tetap dapat diakses.<br>3. Ganti password memvalidasi password lama, panjang minimal 12 karakter, dan konfirmasi cocok.<br>4. Perubahan password, reset flag, dan audit log dibungkus dalam `DB::transaction` yang sama; jika audit gagal, perubahan password dan flag ikut rollback penuh.<br>5. Session diregenerasi setelah ganti password berhasil.<br>6. Penanganan asimetris kegagalan audit: jika audit login gagal, pengguna tetap guest, session dibersihkan, dan CSRF token diregenerasi; jika audit logout gagal, sesi pengguna tetap dipastikan berakhir melalui blok `finally`. | 19 September 2026 | **LULUS** | `tests/Feature/AuthTest.php` (8 tests spesifik TC-05 & ketahanan audit) |
| **TC-31** | Constraint skema database komprehensif: <br>1. Penolakan duplikasi `roles.code` (1062)<br>2. Penolakan duplikasi `residents.user_id` (1062)<br>3. Penolakan duplikasi `payments.receipt_number` pada invoice berbeda (1062)<br>4. Penolakan `payments.invoice_id` tidak ada dengan field lain valid (1452)<br>5. Penolakan penghapusan parent ter-referensi membuktikan RESTRICT menjaga histori (1451)<br>6. Penolakan CHECK constraint spesifik via MySQL error code 3819 tanpa menerima keyword generik 'CONSTRAINT'<br>7. Generated unique columns untuk active placement & valid payment (insert & update bentrok) | Seluruh 18 skenario pengujian lulus pada MySQL 8.0.30. FK insert ditolak (1452), FK parent delete ditolak demi proteksi histori (1451), Duplikat ditolak (1062), CHECK spesifik ditolak (3819 dengan nama constraint eksak), generated unique column aktif/valid mencegah bentrok sekaligus mengizinkan multi ended/void, update bentrok ditolak. | 19 September 2026 | **LULUS** | `php vendor/bin/phpunit --testdox tests/Feature/DatabaseConstraintTest.php` (18 tests, 86 assertions) |
| **TC-24** | Fondasi Audit: Identitas pelaku server-side, snapshot nama independen, aktor sistem eksplisit, waktu server UTC & display Asia/Jakarta, kontrak changes `{"before": ..., "after": ...}`, dan penyaringan allowlist/sensitif/nested. | Terverifikasi pada tingkat fondasi: Snapshot nama `actor_name` tidak berubah saat nama user berganti, `logSystem()` mencatat `actor_id = null` dan `actor_name = 'Sistem'`, waktu UTC deterministik dengan frozen time, field sensitif (password, token, cookie) dan array bersarang pada kolom skalar tersaring bersih. | 19 September 2026 | **LULUS (Fondasi)** | `php vendor/bin/phpunit --testdox tests/Feature/AuditServiceTest.php` (6 tests spesifik) |
| **TC-32** | Fondasi Rollback Audit: Atomisitas mutasi bisnis dan log audit dalam transaksi yang sama jika audit gagal atau operasi lanjutan gagal. | Terverifikasi pada tingkat fondasi: Jika audit gagal di database nyata (MySQL error 1406 data too long), mutasi bisnis ikut rollback penuh (terbukti via DB missing). Jika audit berhasil lalu operasi lanjutan gagal, seluruh mutasi dan audit ikut rollback. Jika mutasi gagal sebelum audit, total count log audit terbukti tetap sama dan mutasi tidak tersimpan. | 19 September 2026 | **LULUS (Fondasi)** | `php vendor/bin/phpunit --testdox tests/Feature/AuditServiceTest.php` (4 tests transaksional) |
| **TC-29** | Layout responsif Bootstrap 5 lokal, navigasi per role (Admin, Pemilik, Penghuni), pencegahan dummy route/badge fase, navigasi terbatas password sementara, aksesibilitas form (aria-invalid, aria-describedby, no password in value/old), uji visual desktop 1366×768 dan mobile 390px, uji keyboard offcanvas (buka, Escape, pengembalian fokus), dan verifikasi build offline tanpa ketergantungan CDN/dev server/public-hot. | 1. Layout Bootstrap 5 terintegrasi murni via npm/Vite lokal tanpa Tailwind/CDN.<br>2. Navigasi role hanya mengarah ke route riil; menu fitur mendatang berlabel non-interaktif 'Belum tersedia' tanpa href='#' atau badge fase.<br>3. Dashboard awal untuk Admin, Pemilik, dan Penghuni netral menyatakan informasi belum tersedia tanpa klaim data palsu dan tanpa query bisnis prematur sebelum T18.<br>4. Password fields tidak pernah memuat atribut value atau old().<br>5. Input terhubung aria-describedby, label terhubung id, dan aria-invalid='true' saat error validasi.<br>6. Pengguna password sementara hanya melihat navigasi terbatas (Ganti Password dan Logout) tanpa loop redirect.<br>7. Pengujian visual pada desktop 1366×768 dan mobile 390px terverifikasi nyata.<br>8. Pengujian offcanvas mobile dengan keyboard: buka menu, tutup via Escape, fokus kembali ke tombol toggle teruji sukses.<br>9. Build offline lulus: `public/build` berisi manifes dan bundel lokal, `public/hot` tidak ada, localhost dapat diakses normal. | 20 September 2026 | **LULUS** | `tests/Feature/LayoutNavigationTest.php` (6 tests, 49 assertions) & 13 screenshots di `docs/evidence/t05/` |

---

## Log Rinci Eksekusi TC-24 & TC-32 Fondasi (`AuditServiceTest`)

```text
PHPUnit 12.5.35 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.3.32
Configuration: C:\SEMESTER 5\SISTEM INFORMASI PRAKTIKUM\TA\phpunit.xml

Audit Service (Tests\Feature\AuditService)
 ✔ Occurred at is stored in utc and converted to jakarta only for display
 ✔ Changes adheres to contract and strictly filters sensitive or unknown fields
 ✔ Flat changes array is converted to after contract
 ✔ User actor snapshot remains intact even after user is renamed
 ✔ System actor is explicitly recorded with null actor id and sistem label
 ✔ System actor accepts custom system component name
 ✔ Business mutation and audit are both committed in successful transaction
 ✔ Business mutation rolls back when real database audit insert fails
 ✔ Both mutation and audit roll back when subsequent operation in transaction fails
 ✔ No audit log created when business mutation fails before audit

OK (10 tests, 46 assertions)
```

---

## Log Rinci Eksekusi TC-31 (`DatabaseConstraintTest`)

```text
PHPUnit 12.5.35 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.3.32
Configuration: C:\SEMESTER 5\SISTEM INFORMASI PRAKTIKUM\TA\phpunit.xml

Database Constraint (Tests\Feature\DatabaseConstraint)
 ✔ 01 database guard and actual connection is sim kos test
 ✔ 02 role code check constraint rejects invalid role
 ✔ 03 roles code unique constraint rejects duplicate code
 ✔ 04 role seeder is idempotent and creates exactly three roles
 ✔ 05 residents user id unique constraint rejects duplicate user
 ✔ 06 payments receipt number unique constraint rejects duplicate across different invoices
 ✔ 07 payments foreign key rejects nonexistent invoice id with other fields valid
 ✔ 08 foreign key restrict prevents deleting referenced parents preserving history
 ✔ 09 foreign key constraints reject invalid insert references
 ✔ 10 unique constraints reject duplicate user room facility and invoice period
 ✔ 11 check constraints reject invalid business data with mysql 3819
 ✔ 12 generated unique column prevents duplicate active placement per room and per resident
 ✔ 13 generated unique column allows multiple ended placements
 ✔ 14 generated unique column update prevents reactivating to colliding active placement
 ✔ 15 generated unique column prevents duplicate valid payment per invoice
 ✔ 16 generated unique column allows multiple void payments
 ✔ 17 generated unique column update prevents unvoiding to colliding valid payment
 ✔ 18 generated columns are protected from mass assignment

OK (18 tests, 86 assertions)
```

---

## Log Rinci Eksekusi TC-01–05 (`AuthTest`)

```text
PHPUnit 12.5.35 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.3.32
Configuration: C:\SEMESTER 5\SISTEM INFORMASI PRAKTIKUM\TA\phpunit.xml

Auth (Tests\Feature\Auth)
 ✔ Tc01 admin user can login with valid credentials and redirects to admin dashboard
 ✔ Tc01 owner user can login with valid credentials and redirects to owner dashboard
 ✔ Tc01 resident with permanent password can login and redirects to portal
 ✔ Tc01 login fails with invalid password returns generic error and leaves no authenticated session
 ✔ Tc01 inactive user cannot login and receives generic error without authenticated session
 ✔ Tc02 logout invalidates session regenerates csrf token and blocks back access
 ✔ Tc02 login is throttled after five failed attempts in one minute
 ✔ Tc03 owner cannot access admin dashboard or mutation route and receives 403
 ✔ Tc03 resident cannot access admin dashboard or mutation route and receives 403
 ✔ Tc03 admin can access admin dashboard and admin mutation route
 ✔ Tc04 resident can view own profile via policy
 ✔ Tc04 resident cannot view other resident profile and receives 403
 ✔ Tc04 owner can view any resident profile as readonly
 ✔ Tc04 user without resident profile does not trigger 500 error
 ✔ Tc05 active session is immediately terminated when user is deactivated in database
 ✔ Tc05 user with temporary password is forced to change password route
 ✔ Tc05 change password fails if current password is wrong
 ✔ Tc05 change password fails if new password less than twelve chars or confirmation mismatches
 ✔ Tc05 change password succeeds resets flag regenerates session and records audit without passwords
 ✔ Tc05 password change rolls back if audit fails
 ✔ Login leaves no session if login audit recording fails
 ✔ Logout always terminates session even if logout audit recording fails
 ✔ Seeder throws exception when password config is empty for unseeded account
 ✔ Seeder is idempotent and does not modify existing accounts on subsequent runs

OK (24 tests, 113 assertions)
```

---

## Log Rinci Eksekusi TC-29 (`LayoutNavigationTest`)

```text
PHPUnit 12.5.35 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.3.32
Configuration: C:\SEMESTER 5\SISTEM INFORMASI PRAKTIKUM\TA\phpunit.xml

Layout Navigation (Tests\Feature\LayoutNavigation)
 ✔ Login page renders accessible inputs without password value
 ✔ Login validation error renders aria invalid and preserves no password value
 ✔ Admin dashboard renders role layout and neutral empty state
 ✔ Owner dashboard renders role layout and neutral empty state
 ✔ Resident portal renders role layout and neutral empty state
 ✔ User with temporary password sees restricted navigation

OK (6 tests, 49 assertions)
```

---

## Log Seluruh Suite Pengujian (`php artisan test`)

```text
   PASS  Tests\Unit\ExampleTest
  ✓ that true is true

   PASS  Tests\Feature\ExampleTest
  ✓ the application returns a successful response

   PASS  Tests\Feature\AuditServiceTest
  ✓ occurred at is stored in utc and converted to jakarta only for display
  ✓ changes adheres to contract and strictly filters sensitive or unknown fields
  ✓ flat changes array is converted to after contract
  ✓ user actor snapshot remains intact even after user is renamed
  ✓ system actor is explicitly recorded with null actor id and sistem label
  ✓ system actor accepts custom system component name
  ✓ business mutation and audit are both committed in successful transaction
  ✓ business mutation rolls back when real database audit insert fails
  ✓ both mutation and audit roll back when subsequent operation in transaction fails
  ✓ no audit log created when business mutation fails before audit

   PASS  Tests\Feature\DatabaseConstraintTest
  ✓ 01 database guard and actual connection is sim kos test
  ✓ 02 role code check constraint rejects invalid role
  ✓ 03 roles code unique constraint rejects duplicate code
  ✓ 04 role seeder is idempotent and creates exactly three roles
  ✓ 05 residents user id unique constraint rejects duplicate user
  ✓ 06 payments receipt number unique constraint rejects duplicate across different invoices
  ✓ 07 payments foreign key rejects nonexistent invoice id with other fields valid
  ✓ 08 foreign key restrict prevents deleting referenced parents preserving history
  ✓ 09 foreign key constraints reject invalid insert references
  ✓ 10 unique constraints reject duplicate user room facility and invoice period
  ✓ 11 check constraints reject invalid business data with mysql 3819
  ✓ 12 generated unique column prevents duplicate active placement per room and per resident
  ✓ 13 generated unique column allows multiple ended placements
  ✓ 14 generated unique column update prevents reactivating to colliding active placement
  ✓ 15 generated unique column prevents duplicate valid payment per invoice
  ✓ 16 generated unique column allows multiple void payments
  ✓ 17 generated unique column update prevents unvoiding to colliding valid payment
  ✓ 18 generated columns are protected from mass assignment

   PASS  Tests\Feature\AuthTest
  ✓ 24 auth, authorization, policy, session, password & seeder tests passed

   PASS  Tests\Feature\LayoutNavigationTest
  ✓ login page renders accessible inputs without password value
  ✓ login validation error renders aria invalid and preserves no password value
  ✓ admin dashboard renders role layout and neutral empty state
  ✓ owner dashboard renders role layout and neutral empty state
  ✓ resident portal renders role layout and neutral empty state
  ✓ user with temporary password sees restricted navigation

  Tests:    60 passed (296 assertions)
  Duration: 5.35s
```
