# Walkthrough Bukti Implementasi & Verifikasi T07: Master Penghuni & Akun (Resident & Account Management)

Dokumen ini mencatat bukti implementasi teknis dan verifikasi komprehensif untuk Task **T07** (Master Penghuni & Akun) pada Sistem Informasi Manajemen Kos (SIM Kos), mencakup CRUD penghuni, pembuatan akun login ber-role `resident` secara atomik, pencarian nama/telepon/email di server, paginasi (10/25/50), otorisasi server-side (`Gate::authorize()`) dan penolakan akses master bagi Penghuni, kebijakan integritas referensi (hapus fisik vs. arsip sesuai TC-07/TC-09), aksi eksplisit idempoten aktifkan/nonaktifkan akun, pencabutan sesi lama kompatibel `SESSION_DRIVER=file` melalui hash signature, penyerahan password sementara acak 12 karakter aman dengan proteksi `Cache-Control: no-store` dan masking tampilan, imutabilitas snapshot nama tagihan historis, penolakan manipulasi role, serta pengujian terotomasi menyeluruh (TC-07, TC-05, TC-30).

---

## 1. Perubahan dan File Utama

### A. Model dan Scopes Penghuni (`app/Models/Resident.php`)
- **Pencarian Terkelompok (Grouped OR)**:
  - `scopeSearch($query, ?string $keyword)`: Mengelompokkan kondisi pencarian nama penghuni, nomor telepon, email akun, dan nama pengguna ke dalam sub-closure `where(function($q) { ... })` agar klausul `OR` tidak membocorkan atau melewati filter status atau tab arsip.
  - `scopeActive($query)`: Menyaring penghuni aktif (`whereNull('residents.archived_at')`).
  - `scopeArchived($query)`: Menyaring penghuni diarsipkan (`whereNotNull('residents.archived_at')`).
- **Pencegahan Kueri N+1**:
  - Accessor `getHasHistoricalReferencesAttribute()` membaca atribut hasil `withExists(['placements', 'user as has_complaints'])` jika sudah dimuat sebelumnya oleh kueri controller, atau fallback ke relasi yang telah dimuat (`relationLoaded`).
  - Accessor `canBeArchived()` membaca atribut hasil `withExists(['activePlacement as has_active_placement'])` atau `relationLoaded('activePlacement')`, sehingga tidak melakukan kueri database individual per baris saat rendering tabel daftar penghuni.
- **Pemeriksaan Integritas & Helper**:
  - `hasHistoricalReferences()`: Memeriksa apakah penghuni memiliki riwayat penempatan (`placements`) atau keluhan (`complaints`).
  - `canBeDeleted()`: Penghuni hanya dapat dihapus fisik jika tidak memiliki riwayat referensi bisnis sama sekali (`!hasHistoricalReferences()`).
  - `canBeArchived()`: Penghuni hanya dapat diarsipkan jika tidak sedang menempati kamar aktif (`!activePlacement()->exists()`).

### B. Service Transaksional & Atomik (`app/Services/ResidentService.php`)
- **Single Entry Point**: Seluruh mutasi data penghuni dan akun dipusatkan melalui class `ResidentService` di dalam transaksi database atomik (`DB::transaction`).
- **Row-Locking Konsisten (`lockForUpdate`)**:
  - Penguncian baris dilakukan dalam urutan konsisten: baris `Resident` dikunci terlebih dahulu, disusul oleh baris `User` terkait.
  - Seluruh verifikasi kelayakan (role `resident`, penempatan aktif, riwayat referensi bisnis, dan status terkini) dievaluasi **di dalam transaksi setelah baris dikunci**.
- **Pembuatan Akun & Profil Atomik (TC-07 & TC-30)**:
  - Membuat record `User` (role: `resident`, `is_active = true`, `must_change_password = true`, `remember_token` dirotasi) dan record `Resident` secara bersamaan.
  - Menghasilkan password sementara acak 12 karakter alfanumerik menggunakan generator kriptografis aman (`Str::password(12)`).
  - Mencatat dua log audit (`module: 'residents'` dan `module: 'users'`).
  - Jika pembuatan profil gagal atau audit kedua gagal, seluruh mutasi di-rollback penuh tanpa meninggalkan akun yatim di tabel `users`.
- **Pembaruan & Sinkronisasi Nama**:
  - Memperbarui profil `Resident` menyinkronkan nama dan email pada akun `User` terkait.
  - Menjaga imutabilitas snapshot faktur: kolom `invoices.resident_name_snapshot` pada transaksi masa lalu secara tegas tidak diubah.
- **Hapus Profil vs. Deaktivasi Akun (TC-07 & TC-09)**:
  - Hapus fisik profil diizinkan hanya jika tidak ada referensi bisnis (`!hasHistoricalReferences()`).
  - Profil `residents` dihapus fisik, sedangkan akun `users` terkait **dinonaktifkan** (`is_active = false`) dan `remember_token` dirotasi untuk menjaga jejak histori jejak audit.
- **Pengarsipan & Buka Arsip**:
  - Mengarsipkan menyetel `residents.archived_at = now()` dan menonaktifkan akun `users` terkait. Ditolak jika memiliki penempatan aktif.
  - Membuka arsip menyetel `residents.archived_at = null` dan mengaktifkan kembali akun `users`. Ditolak jika profil tidak sedang diarsipkan.
- **Aksi Eksplisit Aktifkan / Nonaktifkan Akun (Idempoten)**:
  - `activateAccount()`: Mengaktifkan akun pengguna (`is_active = true`). Ditolak jika profil berstatus diarsipkan. Bersifat idempoten.
  - `deactivateAccount()`: Menonaktifkan akun pengguna (`is_active = false`) dan merotasi `remember_token`. Bersifat idempoten.
- **Reset Password Sementara**:
  - Menghasilkan password sementara 12 karakter baru yang di-hash, menyetel `must_change_password = true`, dan merotasi `remember_token`. Plaintext password tidak pernah dicatat ke audit log.

### C. Otorisasi Server-Side (`app/Policies/ResidentPolicy.php`)
- **Pemisahan Hak Master vs. Portal**:
  - Menambahkan method `viewMaster(User $user, ?Resident $resident = null)`: Hanya Admin dan Pemilik yang diizinkan melihat data penghuni pada modul master. Penghuni ditolak secara ketat (**HTTP 403 Forbidden**), termasuk saat mengakses endpoint detail dirinya sendiri di `/residents/{resident}`.
  - Mempertahankan `view()` untuk hak akses portal mandiri penghuni (T04/T15).
  - Aksi mutasi (`create`, `update`, `delete`, `archive`, `unarchive`, `activate`, `deactivate`, `resetPassword`): Khusus role `admin`.

### D. Form Request Pre-Validation Authorization (`app/Http/Requests/Resident/`)
1. **`StoreResidentRequest`**:
   - `authorize()` mengevaluasi `Gate::allows('create', Resident::class)`. Role tidak berhak (Pemilik/Penghuni) langsung menerima respons **HTTP 403 Forbidden** bahkan jika mengirim payload kosong/invalid.
   - Aturan validasi:
     - `name`: Wajib, 2–100 karakter.
     - `email`: Wajib, format valid, maksimal 191 karakter, unik di tabel `users` (mencakup akun aktif dan nonaktif).
     - `phone`: Wajib, 8–20 karakter, regex wajib memuat angka (`/^(?=.*[0-9])[0-9+\-\s]{8,20}$/`).
     - `origin_address`: Wajib, 5–255 karakter.
   - Pesan kesalahan bahasa Indonesia yang ramah dan jelas.
2. **`UpdateResidentRequest`**:
   - `authorize()` mengevaluasi `Gate::allows('update', $this->route('resident'))`.
   - Aturan `email`: Unik dengan mengabaikan ID user akun itu sendiri (`Rule::unique('users', 'email')->ignore($userId)`).
   - Sanitasi payload ketat: Kolom `role_id`, `role`, `is_active`, `archived_at`, `password`, dan `must_change_password` tidak dapat diinjeksi via payload.

### E. Mekanisme Pencabutan Sesi & Penanda Versi Sesi Khusus (`SESSION_DRIVER=file`)
- **Penanda Versi Sesi Khusus (`session_version`)**:
  - Tabel `users` dilengkapi kolom `session_version` integer default 1 via migrasi `2026_09_21_000001_add_session_version_to_users_table`.
  - Signature sesi dihitung via `$user->getSessionSignature()` = `sha1($this->password . '|' . ($this->session_version ?? 1))`.
  - Nilai signature disimpan di session dengan kunci spesifik per user: `auth_session_hash_{user_id}`.
  - Setiap mutasi pencabutan sesi (reset password, deaktif/reaktif akun, pengarsipan/buka arsip, atau ganti password):
    - Nilai `session_version` diinkremen di database dalam transaksi yang sama.
    - Nilai `remember_token` dirotasi menggunakan `Str::random(60)` untuk menggugurkan cookie remember-me lama.
    - Untuk perubahan password oleh pengguna di sesi aktifnya (`PasswordController::update`), pembaruan password, `session_version`, dan audit dibungkus `DB::transaction`. Signature sesi pengguna diperbarui hanya setelah transaksi berhasil commit.
- **`app/Http/Middleware/EnsureAccountIsActive.php`**:
  - Memeriksa `$user->is_active`. Jika `false`, sesi dibatalkan (`Auth::guard()->logoutCurrentDevice()`, `$session->invalidate()`, `$session->regenerateToken()`).
  - Memeriksa signature `auth_session_hash_{user->id}`:
    - **Sesi tanpa signature**: Ditolak dan diarahkan login ulang. Middleware tidak otomatis menerima sesi tanpa signature dengan menyalin signature terbaru.
    - **Pemulihan remember-me**: Jika session signature belum ada namun `Auth::viaRemember()` bernilai `true` (berhasil diverifikasi guard melalui cookie remember-me yang sah), signature diinisialisasi untuk sesi baru tersebut.
    - **Sesi usang/mismatched**: Jika signature tidak cocok dengan `$user->getSessionSignature()`, sesi ditolak dan diakhiri hanya untuk perangkat tersebut (`Auth::guard()->logoutCurrentDevice()`) tanpa merotasi token global lagi di DB, sehingga sesi baru yang sah di perangkat lain tidak gugur.
- **Kompatibilitas 100% dengan `SESSION_DRIVER=file`**:
  - Evaluasi kriptografis per-request tanpa pemindaian filesystem. Logout biasa pada satu perangkat (`AuthController::logout`) memanggil `logoutCurrentDevice()` dan tidak menyentuh `session_version`, sehingga perangkat lain tidak terganggu.

### F. Controller Master Penghuni (`app/Http/Controllers/ResidentController.php`)
- Melindungi seluruh aksi menggunakan `Gate::authorize()`.
- Menangani filter pencarian dan sanitasi parameter (`status`, `account_status`, `per_page`, `q`) secara aman terhadap input bertipe array.
- Menambahkan header `Cache-Control: no-store, private, max-age=0, must-revalidate` saat merender halaman yang menampilkan kredensial flash password sementara.

### G. Tampilan Antarmuka Blade Bootstrap 5 Responsif (`resources/views/residents/`)
1. **`index.blade.php`**: Tab status (Aktif, Diarsipkan, Semua), filter pencarian nama/telepon/email, filter status akun, tabel responsif dengan badge status, tombol aksi (Detail, Edit, Arsip/Buka Arsip, Hapus), modal konfirmasi Bootstrap 5, dan banner mode baca saja untuk Pemilik Kos.
2. **`create.blade.php`**: Form tambah penghuni dengan alert edukasi pembuatan akun otomatis, input bertipe email/tel, teks helper, dan pesan error validasi inline dengan atribut aksesibilitas (`aria-invalid`, `aria-describedby`).
3. **`edit.blade.php`**: Form perubahan data penghuni dengan alert integritas riwayat faktur yang menjelaskan imutabilitas snapshot nama pada transaksi lama.
4. **`show.blade.php`**: Detail lengkap mencakup kartu identitas penghuni, kartu akun pengguna portal, kartu riwayat kamar/penempatan sewa terurut descending, panel pengelolaan akun (Reset Password Sementara dan Nonaktifkan/Aktifkan Akun dengan modal konfirmasi), panel aksi master, dan kartu kredensial password sementara dengan fitur masking default (`••••••••••••`) serta tombol salin.

---

## 2. Hasil Eksekusi Test Suite & Uji Regresi

Seluruh pengujian dijalankan pada database MySQL `sim_kos_test` yang dilindungi oleh safety guard:

### A. Hasil Uji Fitur Master Penghuni & Akun (`tests/Feature/ResidentTest.php`)
```text
   PASS  Tests\Feature\ResidentTest
  ✓ admin can access all master resident endpoints
  ✓ owner can only view residents as readonly
  ✓ resident cannot access any master resident endpoints including own detail
  ✓ unauthorized roles receive 403 before validation
  ✓ tc07 admin can create resident with account atomically
  ✓ resident creation validates required fields and phone format
  ✓ tc07 email must be unique including inactive accounts
  ✓ tc30 creation rolls back fully when profile creation fails
  ✓ tc30 creation rolls back when second audit fails
  ✓ tc30 role manipulation via payload is strictly rejected
  ✓ admin or owner accounts cannot be manipulated through resident service
  ✓ tc30 name update syncs user and resident without altering invoice snapshot
  ✓ resident update rolls back when audit fails
  ✓ tc07 delete profile without references deletes profile and deactivates user
  ✓ tc09 delete rejected when resident has placement history
  ✓ delete rolls back when audit fails
  ✓ archive resident without active placement deactivates user
  ✓ tc09 archive rejected when resident has active placement
  ✓ archive rolls back when audit fails
  ✓ unarchive restores profile and reactivates user
  ✓ unarchive rolls back when audit fails
  ✓ unarchive rejected if not currently archived
  ✓ explicit deactivate and activate account
  ✓ activate rolls back when audit fails
  ✓ deactivate rolls back when audit fails
  ✓ activate rejected when resident profile is archived
  ✓ tc05 reset temporary password updates credentials and omits password from audit
  ✓ reset temporary password rolls back when audit fails
  ✓ two independent sessions revocation on reset temporary password
  ✓ new login remains valid even when old session sends request
  ✓ session without signature is rejected
  ✓ legitimate remember me works and old cookie rejected after revocation
  ✓ deactivation then reactivation does not revive old session
  ✓ audit failure rolls back password status and revocation marker
  ✓ index search and filter work accurately
  ✓ index rejects array inputs gracefully without 500
  ✓ index rendering does not trigger n plus one queries

  Tests:    37 passed (185 assertions)
  Duration: 2.43s
```

### B. Hasil Uji Regresi Seluruh Suite Proyek (`php artisan test`)
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
  ✓ tc01 admin user can login with valid credentials and redirects to admin dashboard
  ✓ tc01 owner user can login with valid credentials and redirects to owner dashboard
  ✓ tc01 resident with permanent password can login and redirects to portal
  ✓ tc01 login fails with invalid password returns generic error and leaves no authenticated session
  ✓ tc01 inactive user cannot login and receives generic error without authenticated session
  ✓ tc02 logout invalidates session regenerates csrf token and blocks back access
  ✓ tc02 login attempts are throttled after maximum failures with standard error message
  ✓ tc03 owner cannot access admin dashboard and receives 403
  ✓ tc03 resident cannot access admin dashboard and receives 403
  ✓ tc03 admin can access admin dashboard
  ✓ tc04 resident can view own profile
  ✓ tc04 resident cannot view other resident profile and receives 403
  ✓ tc04 owner can view all resident profiles as readonly
  ✓ tc04 user without resident profile handled safely without 500
  ✓ tc05 session invalidated immediately on next request when user deactivated
  ✓ tc05 temporary password user must change password before accessing dashboard
  ✓ tc05 temporary password user can logout without redirect loop
  ✓ tc05 change password validates current password minimum length and confirmation
  ✓ tc05 change password updates password resets flag records audit and regenerates session
  ✓ tc05 change password rolls back fully when audit fails
  ✓ tc05 audit login failure cleans session and leaves user as guest
  ✓ tc05 audit logout failure logs technical error and still ends user session
  ✓ tc04 user seeder throws exception when password config empty and creates no accounts
  ✓ tc04 user seeder runs idempotently without modifying existing accounts

   PASS  Tests\Feature\LayoutNavigationTest
  ✓ login page renders accessible inputs without password value
  ✓ login validation error renders aria invalid and preserves no password value
  ✓ admin dashboard renders role layout and neutral empty state
  ✓ owner dashboard renders role layout and neutral empty state
  ✓ resident portal renders role layout and neutral empty state
  ✓ user with temporary password sees restricted navigation

   PASS  Tests\Feature\RoomTest
  ✓ admin can view rooms list with search and pagination
  ✓ owner can view rooms list and details as readonly
  ✓ owner cannot access mutation endpoints and receives 403
  ✓ resident cannot access any rooms endpoints and receives 403
  ✓ unauthorized roles receive 403 even with invalid payload
  ✓ admin can create room with valid data and records audit log
  ✓ room creation validates required unique positive rate and bounds
  ✓ create and update cannot manipulate archived at or occupancy fields
  ✓ room creation rolls back when audit fails
  ✓ admin can update room and records audit log
  ✓ room update validation and unique number ignoring self
  ✓ room update rolls back when audit fails
  ✓ room rate update does not change existing placement agreed rate or invoice amount
  ✓ occupancy status is derived dynamically from active placement
  ✓ admin can delete room with no references and records audit
  ✓ room physical deletion rolls back when audit fails
  ✓ room physical deletion is rejected when placement history exists
  ✓ room physical deletion is rejected when facility reference exists including archived facility
  ✓ admin can archive vacant room with history and records audit
  ✓ room archiving rolls back when audit fails
  ✓ room archiving is rejected when active placement exists
  ✓ admin can unarchive archived room and records audit
  ✓ room unarchive rolls back when audit fails
  ✓ rejected actions do not create audit logs
  ✓ admin can view room detail with active and ordered past placements
  ✓ owner can view room detail as readonly with placements and no mutation actions
  ✓ index rejects array inputs gracefully without 500 error
  ✓ index rejects invalid filter options
  ✓ index accepts valid filters
  ✓ index rendering does not trigger n plus one queries for historical references

   PASS  Tests\Feature\ResidentTest
  ✓ (37 pengujian lengkap lulus)

  Tests:    127 passed (666 assertions)
  Duration: 7.89s
```

### C. Hasil Kompilasi Aset Frontend (`npm run build`)
```text
vite v8.3.0 building client environment for production...
transforming...
✓ 4 modules transformed.
rendering chunks...
computing gzip size...
public/build/manifest.json              0.33 kB │ gzip:  0.16 kB
public/build/assets/app-CVq8h3i9.css  236.46 kB │ gzip: 32.41 kB
public/build/assets/app-6x2dcqrK.js    79.03 kB │ gzip: 23.56 kB
✓ built in 773ms
```

---

## 3. Hasil Pemeriksaan UI & Lokasi Screenshot Nyata

Semua pengujian antarmuka pengguna dijalankan menggunakan browser otomatis pada resolusi nyata:
- **Desktop**: 1366 × 768 piksel
- **Mobile**: 390 × 844 piksel
- Sesuai instruksi keamanan, **seluruh kredensial password disembunyikan/dimaskir (`••••••••••••`) dan tidak ada plaintext password yang bocor pada screenshot**.

Tabel artefak screenshot tersimpan di direktori `docs/evidence/t07/`:

| No | Nama File Bukti (Relatif) | Deskripsi Hasil Pemeriksaan | Status |
|:---|:---|:---|:---:|
| 1 | [`t07/01-admin-residents-index-desktop.png`](t07/01-admin-residents-index-desktop.png) | Tampilan daftar master penghuni Admin (1366×768): tab status (Aktif, Diarsipkan, Semua), tombol '+ Tambah Penghuni', filter pencarian teks (nama, telepon, email), filter status akun (Semua, Aktif, Nonaktif), tabel daftar penghuni lengkap dengan badge status akun/master, kontak telepon, alamat, penempatan kamar, dan tombol aksi (Detail, Edit, Arsipkan, Hapus). | **LULUS** |
| 2 | [`t07/02-admin-residents-create-validation.png`](t07/02-admin-residents-create-validation.png) | Pengujian validasi form tambah penghuni: pesan kesalahan bahasa Indonesia ("Nama lengkap wajib diisi.", "Email wajib diisi.", "Nomor telepon wajib diisi.", "Alamat asal wajib diisi."), atribut `aria-invalid="true"` aktif, dan fokus aksesibilitas form terjaga. | **LULUS** |
| 3 | [`t07/03-admin-resident-show-credentials.png`](t07/03-admin-resident-show-credentials.png) | Tampilan detail penghuni setelah pembuatan akun: kartu kredensial password sementara tampil sekali pakai dengan password termaskir (`••••••••••••`), tombol "Tampilkan", tombol "Salin Password", kartu identitas lengkap, kartu akun pengguna portal, riwayat sewa kamar, panel kelola akun, dan panel aksi master. | **LULUS** |
| 4 | [`t07/04-admin-resident-edit-desktop.png`](t07/04-admin-resident-edit-desktop.png) | Tampilan form edit data penghuni: nilai terisi dari data yang ada, input nomor telepon termonospace, textarea alamat asal, serta alert edukasi imutabilitas dokumen faktur masa lalu. | **LULUS** |
| 5 | [`t07/05-admin-resident-reset-password-modal.png`](t07/05-admin-resident-reset-password-modal.png) | Modal konfirmasi reset password sementara: dialog peringatan konsekuensi pencabutan sesi lama, pembuatan password sementara baru, dan kewajiban ganti password saat login. | **LULUS** |
| 6 | [`t07/06-admin-resident-archive-modal.png`](t07/06-admin-resident-archive-modal.png) | Modal konfirmasi pengarsipan penghuni: dialog peringatan interaktif yang menjelaskan bahwa pengarsipan otomatis menonaktifkan akun login pengguna terkait namun mempertahankan riwayat data sewa. | **LULUS** |
| 7 | [`t07/07-admin-residents-mobile-390px.png`](t07/07-admin-residents-mobile-390px.png) | Tampilan mobile 390px: tata letak responsif, pembungkus tabel `table-responsive`, tombol aksi tersusun rapi, dan filter dapat dioperasikan pada layar sempit. | **LULUS** |
| 8 | [`t07/08-owner-residents-index-desktop.png`](t07/08-owner-residents-index-desktop.png) | Tampilan daftar penghuni Pemilik (Read-Only): banner informasi "Mode Baca Saja (Pemilik)", tombol '+ Tambah Penghuni' disembunyikan, dan kolom aksi tabel hanya menyediakan tombol "Detail" tanpa opsi Edit, Arsip, atau Hapus. | **LULUS** |
| 9 | [`t07/09-owner-resident-show-desktop.png`](t07/09-owner-resident-show-desktop.png) | Tampilan detail penghuni Pemilik (Read-Only): seluruh data identitas, akun pengguna, dan riwayat penempatan sewa dapat dibaca secara transparan tanpa keberadaan panel aksi mutasi atau reset password. | **LULUS** |

---

## 4. Bagian yang Belum Selesai / Batasan Ruang Lingkup T07

Sesuai roadmap implementasi SIM Kos:
1. **Modul Master Fasilitas (T08)**: Manajemen inventaris master fasilitas kos, kondisi barang (baik, rusak, dalam perbaikan), dan penempatan fasilitas ke kamar atau area bersama akan dibangun pada tahap berikutnya (**T08**).
2. **Modul Tagihan & Pembayaran (Fase 3: T12–T15)**: Penagihan bulanan, pencatatan pembayaran sewa, cetak kuitansi bukti bayar, dan fitur portal pembayaran mandiri penghuni akan diimplementasikan pada Fase 3.
3. **Modul Keluhan (Fase 4: T16–T17)**: Pengajuan keluhan fasilitas oleh penghuni aktif dan penanganan tindak lanjut perbaikan oleh admin akan dibangun pada Fase 4.
