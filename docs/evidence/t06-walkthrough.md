# Walkthrough Bukti Implementasi & Verifikasi T06: Master Kamar (Room Management)

Dokumen ini mencatat bukti implementasi teknis dan verifikasi komprehensif untuk Task **T06** (Master Kamar) pada Sistem Informasi Manajemen Kos (SIM Kos), mencakup CRUD kamar, validasi nomor unik lintas aktif & arsip, pemisahan status hunian dari status arsip, otorisasi role berbasis server (`Gate::authorize()`), integritas referensi foreign key RESTRICT (TC-09), imutabilitas tarif terhadap kontrak yang berjalan (TC-06), transaksi atomik dan rollback audit, antarmuka Bootstrap 5 responsif, serta pengujian browser otomatis.

---

## 1. Perubahan dan File Utama

### A. Model dan Relasi Kamar (`app/Models/Room.php`)
- **Relasi Eloquent**:
  - `placements()`: `hasMany(Placement::class)` untuk seluruh histori penempatan kamar.
  - `activePlacement()`: `hasOne(Placement::class)->whereNull('ended_on')` untuk penempatan aktif saat ini.
  - `facilities()`: `hasMany(Facility::class)` untuk fasilitas yang ditempatkan di kamar ini (termasuk fasilitas berstatus arsip).
- **Pencarian Aman & Filter Status (Grouped OR)**:
  - `scopeSearch($query, ?string $keyword)`: Mengelompokkan kondisi OR pencarian nomor kamar dan tipe kamar ke dalam sub-closure `where(function($q) { ... })` agar klausa OR tidak membocorkan atau menerobos filter tab arsip maupun hunian.
  - `scopeActive($query)`: Menyaring kamar aktif (`whereNull('archived_at')`).
  - `scopeArchived($query)`: Menyaring kamar diarsipkan (`whereNotNull('archived_at')`).
  - `scopeOccupied($query)`: Menyaring kamar yang memiliki penempatan aktif (`whereHas('activePlacement')`).
  - `scopeVacant($query)`: Menyaring kamar tanpa penempatan aktif (`whereDoesntHave('activePlacement')`).
- **Efisiensi Kueri Bebas N+1**:
  - Accessor `getIsOccupiedAttribute()` menggunakan atribut hasil `withExists('activePlacement as has_active_placement')` jika sudah dimuat sebelumnya oleh kueri controller, atau fallback ke `relationLoaded('activePlacement')`, sehingga tidak melakukan kueri individual per baris saat rendering tabel index.
- **Pemeriksaan Integritas & Helper**:
  - `hasHistoricalReferences()`: Memeriksa apakah kamar memiliki riwayat penempatan (aktif maupun selesai) atau relasi fasilitas (aktif maupun diarsipkan).
  - `canBeDeleted()`: Kamar hanya dapat dihapus fisik jika tidak memiliki relasi/referensi sama sekali (`!hasHistoricalReferences()`).
  - `canBeArchived()`: Kamar hanya dapat diarsipkan jika tidak sedang dihuni oleh penempatan aktif (`!is_occupied`).

### B. Otorisasi Server-Side (`app/Policies/RoomPolicy.php`)
- Menggunakan `Gate::authorize()` pada seluruh aksi controller:
  - `viewAny` & `view`: Diizinkan untuk Admin dan Pemilik (*read-only*). Penghuni ditolak (HTTP 403).
  - `create`, `update`, `delete`, `archive`, `unarchive`: Khusus Admin. Pemilik dan Penghuni ditolak secara ketat (HTTP 403).

### C. Validasi Form Request (`app/Http/Requests/Room/`)
1. **`StoreRoomRequest`**:
   - `authorize()`: Menjalankan `Gate::allows('create', Room::class)` sebelum proses validasi aturan, sehingga pengguna tanpa hak akses langsung menerima respons HTTP 403 meskipun mengirim payload yang tidak valid.
   - Aturan validasi:
     - `number`: Wajib, string, maksimal 50 karakter, unik di tabel `rooms` (mencakup kamar aktif dan kamar diarsipkan).
     - `type`: Wajib, string, maksimal 100 karakter.
     - `monthly_rate`: Wajib, bilangan bulat (integer), nilai positif antara Rp 1 hingga Rp 999.999.999.
     - `notes`: Opsional, teks.
   - Pesan validasi berbahasa Indonesia yang jelas.
2. **`UpdateRoomRequest`**:
   - `authorize()`: Menjalankan `Gate::allows('update', $room)` sebelum validasi.
   - Aturan `number`: Unik dengan mengabaikan ID kamar itu sendiri (`Rule::unique('rooms', 'number')->ignore($room->id)`).
   - Sanitasi payload ketat: Kolom `archived_at` dan status hunian tidak diizinkan masuk dari payload create/update.

### D. Controller Transaksional & Audit (`app/Http/Controllers/RoomController.php`)
- **Otorisasi Menyeluruh**: Setiap endpoint dilindungi oleh `Gate::authorize()` di tingkat controller sebagai pertahanan berlapis selain Form Request.
- **Validasi Parameter Filter**: Parameter `status` dibatasi hanya `active`, `archived`, atau `all`. Parameter `occupancy` dibatasi hanya `all`, `occupied`, atau `vacant`. Parameter `per_page` dibatasi hanya `10`, `25`, atau `50` (default: 10).
- **Kueri Teroptimasi**: Memuat `withExists(['activePlacement as has_active_placement'])` dan relasi `activePlacement.resident` untuk mencegah N+1 query.
- **Transaksi & Row-Locking (`DB::transaction` & `lockForUpdate`)**:
  - Operasi pembaruan (`update`), penghapusan fisik (`destroy`), pengarsipan (`archive`), dan buka arsip (`unarchive`) mengunci baris kamar terbaru sebelum memvalidasi kondisi bisnis.
  - **TC-09 Reference Integrity Protection**: Pada `destroy`, controller memeriksa `canBeDeleted()`. Jika kamar memiliki riwayat penempatan atau fasilitas terkait, penghapusan fisik ditolak dengan pesan peringatan edukatif yang mengarahkan admin untuk menggunakan fitur pengarsipan.
  - **Occupancy Invariant**: Pada `archive`, controller memeriksa `canBeArchived()`. Jika kamar memiliki penempatan aktif, pengarsipan ditolak demi menjaga keutuhan kontrak penempatan.
- **Pencatatan Audit Trail (`AuditService`)**:
  - Modul: `'rooms'`.
  - Serialisasi aman: Waktu `archived_at` diserialisasikan ke format string ISO 8601 (`toIso8601String()`) atau `null`, tidak pernah menyimpan objek Carbon.
  - Snapshot Before/After: Menyimpan nilai sebelum dan sesudah mutasi secara akurat.
  - Snapshot Hapus: Menyimpan identitas dan data kamar sebelum penghapusan fisik ke database.
  - Atomisitas: Eksepsi audit tidak pernah ditelan; jika audit gagal, seluruh transaksi database dibatalkan (*rollback*) penuh.

### E. Tampilan Antarmuka Bootstrap 5 (`resources/views/rooms/`)
1. **`index.blade.php`**:
   - Tab status navigasi: **Kamar Aktif**, **Diarsipkan**, dan **Semua Kamar** dengan badge jumlah data real-time.
   - Filter bar: Input pencarian dengan retain value `request('search')`, dropdown status hunian (Semua / Terisi / Kosong), dan dropdown batas per halaman (10, 25, 50).
   - Indikator Role: Banner informatif "Mode Baca Saja (Pemilik)" ditampilkan khusus untuk pemilik kos, menyembunyikan tombol mutasi.
   - Tabel responsif: Menampilkan kolom nomor kamar, tipe kamar, tarif bulanan (format Rupiah via `Number::currency`), status hunian dinamis (badge hijau "Terisi" dengan nama penghuni aktif, atau badge abu-abu "Kosong / Siap Dihuni"), serta status arsip.
   - Modal Konfirmasi Interaktif: Konfirmasi pengarsipan dan penghapusan fisik menggunakan modal Bootstrap 5 yang aman, memuat detail kamar dan peringatan konsekuensi.
   - Pagination Bootstrap 5 (`$rooms->links()`).
2. **`create.blade.php`**:
   - Form pembuatan kamar baru dengan input nomor, tipe, tarif bulanan, dan textarea catatan kamar.
   - Terhubung dengan `id`, `aria-describedby`, pesan validasi merah, dan retensi nilai lama (`old()`).
3. **`edit.blade.php`**:
   - Form pembaruan kamar dengan nilai pra-isi (`old('field', $room->field)`).
   - Catatan informatif mengenai imutabilitas tarif: Perubahan tarif kamar hanya berlaku untuk penempatan baru dan tidak memengaruhi kontrak penempatan berjalan maupun faktur tagihan yang telah diterbitkan (TC-06).
4. **`show.blade.php`**:
   - Tampilan rincian lengkap spesifikasi kamar, status hunian dinamis, dan catatan.
   - Kartu Informasi Penempatan Aktif jika kamar sedang terisi.
   - Tabel Inventaris Fasilitas Kamar (menampilkan kode, nama, kondisi, dan status arsip fasilitas).
   - Tabel Histori Penempatan (tanggal mulai, tanggal selesai, tarif penempatan yang disepakati, alasan selesai).
   - Panel Panduan Kebijakan Penghapusan & Pengarsipan (menjelaskan syarat proteksi referensi TC-09).

### F. Routing & Navigasi
- **`routes/web.php`**: Mendaftarkan `Route::resource('rooms', RoomController::class)` serta rute POST `rooms/{room}/archive` dan `rooms/{room}/unarchive` di dalam grup middleware `auth`, `active`, dan `password.not_temp`.
- **`resources/views/layouts/partials/sidebar-nav.blade.php`**: Mengaktifkan tautan navigasi "Kamar" untuk Admin dan "Data Kamar" untuk Pemilik.

---

## 2. Hasil Build dan Tes Otomatis

### A. Hasil Build Aset Frontend (`npm run build`)
```text
> build
> vite build

vite v8.3.0 building client environment for production...
transforming...
✓ 4 modules transformed.
rendering chunks...
computing gzip size...
public/build/manifest.json              0.33 kB │ gzip:  0.16 kB
public/build/assets/app-CVq8h3i9.css  236.46 kB │ gzip: 32.41 kB
public/build/assets/app-6x2dcqrK.js    79.03 kB │ gzip: 23.56 kB

✓ built in 1.18s
```
- **Verifikasi Penggunaan Aset Build Lokal**: File `public/hot` dipastikan **tidak ada** (`Test-Path public/hot` mengembalikan `False`), membuktikan bahwa aplikasi berjalan murni menggunakan aset lokal terkompilasi tanpa ketergantungan pada server Vite development runtime.

### B. Hasil Eksekusi Test Suite Kamar (`php vendor/bin/phpunit --testdox tests/Feature/RoomTest.php`)
```text
PHPUnit 12.5.35 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.3.32
Configuration: C:\SEMESTER 5\SISTEM INFORMASI PRAKTIKUM\TA\phpunit.xml

Room (Tests\Feature\Room)
 ✔ Admin can view rooms list with search and pagination
 ✔ Owner can view rooms list and details as readonly
 ✔ Owner cannot access mutation endpoints and receives 403
 ✔ Resident cannot access any rooms endpoints and receives 403
 ✔ Unauthorized roles receive 403 even with invalid payload
 ✔ Admin can create room with valid data and records audit log
 ✔ Room creation validates required unique positive rate and bounds
 ✔ Create and update cannot manipulate archived at or occupancy fields
 ✔ Room creation rolls back when audit fails
 ✔ Admin can update room and records audit log
 ✔ Room update validation and unique number ignoring self
 ✔ Room update rolls back when audit fails
 ✔ Room rate update does not change existing placement agreed rate or invoice amount
 ✔ Occupancy status is derived dynamically from active placement
 ✔ Admin can delete room with no references and records audit
 ✔ Room physical deletion rolls back when audit fails
 ✔ Room physical deletion is rejected when placement history exists
 ✔ Room physical deletion is rejected when facility reference exists including archived facility
 ✔ Admin can archive vacant room with history and records audit
 ✔ Room archiving rolls back when audit fails
 ✔ Room archiving is rejected when active placement exists
 ✔ Admin can unarchive archived room and records audit
 ✔ Room unarchive rolls back when audit fails
 ✔ Rejected actions do not create audit logs
 ✔ Admin can view room detail with active and ordered past placements
 ✔ Owner can view room detail as readonly with placements and no mutation actions
 ✔ Index rejects array inputs gracefully without 500 error
 ✔ Index rejects invalid filter options
 ✔ Index accepts valid filters
 ✔ Index rendering does not trigger n plus one queries for historical references

OK (30 tests, 185 assertions)
```

### C. Hasil Eksekusi Seluruh Test Suite Proyek (`php artisan test`)
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

  Tests:    90 passed (481 assertions)
  Duration: 10.50s
```

---

## 3. Hasil Pemeriksaan UI & Lokasi Screenshot Nyata

Semua pengujian antarmuka pengguna dijalankan menggunakan browser otomatis pada resolusi nyata:
- **Desktop**: 1366 × 768 piksel
- **Mobile**: 390 × 844 piksel
- Seluruh kredensial diverifikasi dari konfigurasi lokal; tidak ada password yang bocor atau ditampilkan pada screenshot.

Tabel artefak screenshot tersimpan di directori repo `docs/evidence/t06/`:

| No | Nama File Bukti (Relatif) | Deskripsi Hasil Pemeriksaan | Status |
|:---|:---|:---|:---:|
| 1 | [`t06/01-admin-rooms-index-desktop.png`](t06/01-admin-rooms-index-desktop.png) | Tampilan daftar kamar Admin (1366×768): tab status (Aktif, Diarsipkan, Semua), tombol '+ Tambah Kamar', filter pencarian & hunian, tabel daftar kamar dengan nomor, tipe, tarif Rupiah, status hunian dinamis ("Kosong / Siap Dihuni"), dan tombol aksi (Detail, Edit, Arsipkan, Hapus). | **LULUS** |
| 2 | [`t06/02-admin-rooms-create-validation.png`](t06/02-admin-rooms-create-validation.png) | Pengujian validasi form tambah kamar: error merah muncul dengan pesan bahasa Indonesia ("Nomor kamar wajib diisi.", "Tipe kamar wajib diisi.", "Tarif bulanan wajib diisi."), atribut `aria-invalid="true"` aktif, dan fokus aksesibilitas terjaga. | **LULUS** |
| 3 | [`t06/03-admin-room-show-desktop.png`](t06/03-admin-room-show-desktop.png) | Tampilan awal detail kamar Admin: kartu spesifikasi lengkap, tarif bulanan, status hunian dinamis, kartu inventaris fasilitas kamar, tabel histori penempatan, panel panduan kebijakan penghapusan/pengarsipan, dan tombol navigasi aksi. | **LULUS** |
| 4 | [`t06/04-admin-room-edit-desktop.png`](t06/04-admin-room-edit-desktop.png) | Tampilan edit kamar: nilai terisi dari data kamar yang ada, textarea catatan kamar responsif, serta alert edukasi imutabilitas tarif (TC-06) yang menjelaskan perubahan tarif tidak memengaruhi kontrak berjalan. | **LULUS** |
| 5 | [`t06/05-admin-room-archive-modal.png`](t06/05-admin-room-archive-modal.png) | Modal konfirmasi pengarsipan Bootstrap 5: dialog peringatan interaktif dengan nama kamar, penjelasan konsekuensi pengarsipan, dan tombol konfirmasi "Ya, Arsipkan Kamar". | **LULUS** |
| 6 | [`t06/06-admin-rooms-archived-tab.png`](t06/06-admin-rooms-archived-tab.png) | Tab kamar diarsipkan: kamar A-102 berstatus diarsipkan dengan badge abu-abu "Diarsipkan", tombol "Buka Arsip" muncul untuk mengaktifkan kembali kamar. | **LULUS** |
| 7 | [`t06/07-admin-rooms-mobile-390px.png`](t06/07-admin-rooms-mobile-390px.png) | Tampilan mobile 390px: tata letak responsif, pembungkus tabel `table-responsive`, tombol aksi tersusun rapi, dan filter dapat dioperasikan pada layar sempit. | **LULUS** |
| 8 | [`t06/08-owner-rooms-index-desktop.png`](t06/08-owner-rooms-index-desktop.png) | Tampilan daftar kamar Pemilik (Read-Only): banner informasi "Mode Baca Saja (Pemilik)", tombol '+ Tambah Kamar' disembunyikan, dan kolom aksi tabel hanya menyediakan tombol "Detail" tanpa opsi Edit, Arsip, atau Hapus. | **LULUS** |
| 9 | [`t06/09-owner-room-show-desktop.png`](t06/09-owner-room-show-desktop.png) | Tampilan awal detail kamar Pemilik: seluruh data spesifikasi, inventaris fasilitas, dan histori penempatan dapat dibaca secara transparan tanpa keberadaan tombol aksi mutasi. | **LULUS** |
| 10 | [`t06/10-admin-room-detail-relations.png`](t06/10-admin-room-detail-relations.png) | Tampilan detail kamar Admin dengan relasi berpenghuni aktif, inventaris fasilitas kamar, riwayat penempatan kamar terurut descending berdasarkan tanggal mulai sewa, serta penonaktifan tombol arsip/hapus fisik karena memiliki referensi aktif. | **LULUS** |
| 11 | [`t06/11-owner-room-detail-relations.png`](t06/11-owner-room-detail-relations.png) | Tampilan detail kamar Pemilik (Read-Only) dengan relasi berpenghuni aktif dan histori sewa berurutan tanggal descending, murni tanpa tombol aksi mutasi apa pun. | **LULUS** |

---

## 4. Bagian yang Belum Selesai / Batasan Ruang Lingkup T06

Sesuai roadmap implementasi SIM Kos:
1. **Modul Penghuni & Akun (T07)**: Pembuatan akun dan profil penghuni kos secara atomik, pencarian, detail, reset password, dan status akun akan diimplementasikan pada T07.
2. **Modul Fasilitas (T08)**: Manajemen master fasilitas kos, kondisi barang, dan penempatan ke kamar/area bersama akan dibangun pada T08. Relasi fasilitas pada kamar saat ini telah siap mendukung integritas RESTRICT (TC-09).
3. **Modul Tagihan & Pembayaran (T12 & T14)**: Penerbitan tagihan berkala dan pembayaran sewa akan dibangun pada Fase 3.
