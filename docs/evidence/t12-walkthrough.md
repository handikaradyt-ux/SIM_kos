# Walkthrough T12: Antarmuka Tagihan, Preview, dan Sinkronisasi Invoice (Billing Management)

Dokumen ini mendokumentasikan implementasi teknis, pengujian komprehensif, dan verifikasi antarmuka visual untuk **Task T12: Antarmuka Tagihan, Preview, dan Sinkronisasi Invoice** pada Sistem Informasi Manajemen (SIM) Kos. Modul ini melengkapi siklus penerbitan tagihan dari fondasi T09–T11 dengan menyediakan antarmuka terpusat bagi Administrator untuk memantau status penagihan dan menjalankan sinkronisasi invoice berbasis pratinjau (*preview-then-sync*), bagi Pemilik (*Owner*) untuk pemantauan piutang secara transparan (*read-only*), serta bagi Penghuni (*Resident*) untuk memeriksa riwayat tagihan penempatan pribadinya tanpa kebocoran data global maupun antar-penghuni (anti-IDOR).

---

## 1. Ringkasan Perubahan Kode & Arsitektur

### A. Kebijakan Otorisasi: `app/Policies/InvoicePolicy.php`
- **`viewAny(User $user): bool`**: Mengizinkan Admin, Owner, dan Resident aktif untuk membuka daftar tagihan. Scope query basis data disesuaikan menurut role di controller.
- **`view(User $user, Invoice $invoice): bool`**:
  - Admin dan Owner dapat melihat seluruh invoice.
  - Resident **hanya dapat melihat invoice milik penempatannya sendiri** (`$placement->resident_id === $resident->id`). Akses langsung via URL ke invoice milik penghuni lain ditolak dengan **HTTP 403 Forbidden** (anti-IDOR).
- **`preview(User $user): bool` & `sync(User $user): bool`**: Khusus Administrator aktif (`$user->role?->code === 'admin' && (bool) $user->is_active`). Owner dan Resident dilarang keras mengakses operasi preview maupun eksekusi mutasi sinkronisasi tagihan.

---

### B. Model & Logika Tanggal: `app/Models/Invoice.php` & `app/Models/Placement.php`
1. **Normalisasi Tanggal Kalender (`parseNormalizedDate`)**:
   - Menghindari perbandingan langsung objek Carbon model UTC dengan midnight `Asia/Jakarta`.
   - Mengambil representasi string `Y-m-d` murni lalu mem-parse-nya ke kalender `Asia/Jakarta` tanpa memutasi (*no mutation*) objek Carbon input aslinya, sejalan dengan pola deterministik `BillingService::parseModelDate()`.
   - Menggunakan satu tanggal bisnis kalender acuan (`$businessDate = Carbon::now('Asia/Jakarta')->startOfDay()`) per request.
2. **Semantik Status Finansial**:
   - **`isPaid(): bool`**: Mengharuskan keberadaan pembayaran berstatus valid (`hasValidPayment()`). Pembayaran yang dibatalkan (*void*) **tidak membuat invoice lunas**.
   - **`isOverdue(?CarbonInterface $asOfDate): bool`**: Strict subset dari `unpaid` dengan kondisi `due_on < $businessDate`.
   - **`isDueToday(?CarbonInterface $asOfDate): bool`**: Tagihan belum lunas dengan kondisi `due_on == $businessDate`. Tagihan jatuh tempo hari ini **BUKAN** terlambat (*not overdue*).
   - **`isDueFuture(?CarbonInterface $asOfDate): bool`**: Tagihan belum lunas dengan kondisi `due_on > $businessDate` ("Belum Jatuh Tempo").
3. **Query Scopes**:
   - `scopeSearch($query, ?string $keyword)`: Mencari berdasarkan nama penghuni snapshot, nomor kamar snapshot, atau bulan periode.
   - `scopeStatus($query, ?string $status, ?CarbonInterface $asOfDate)`:
     - `all`: Tanpa filter status.
     - `paid`: `whereHas('validPayment')`.
     - `unpaid`: `whereDoesntHave('validPayment')` (mencakup overdue, due today, dan future due date).
     - `overdue`: `whereDoesntHave('validPayment')->where('due_on', '<', $todayDate)`.
   - `scopePeriod($query, ?string $period)`: Filter bulan periode `YYYY-MM`.
   - `scopePlacementStatus($query, ?string $placementStatus)`: Filter penempatan aktif atau selesai.
4. **Helper Model Placement**:
   - Menambahkan method `isEnded(): bool` pada `App\Models\Placement` melengkapi `isActive(): bool`.

---

### C. Controller & Form Requests: `app/Http/Controllers/InvoiceController.php`
1. **Sanitasi Parameter & Anti-500**:
   - Validasi query string graceful pada `index()` (`search`, `status`, `period`, `placement_status`, `sort`, `direction`, `per_page`). Parameter bertipe array atau manipulasi injeksi ditangani dengan validasi terstruktur tanpa memicu HTTP 500 error.
2. **Eager Loading Anti-N+1 pada Tabel Tagihan**:
   - Memuat relasi `placement.room`, `placement.resident.user`, dan `validPayment` secara eager. Menghilangkan N+1 query pada perenderan tabel baris tagihan.
3. **Pemisahan Cakupan Global dari Penghuni**:
   - Evaluasi cakupan global (`checkGlobalCoverage`) hanya dijalankan untuk Administrator dan Owner.
   - Untuk Resident, info cakupan global bernilai `null` dan seluruh banner/indikator global disembunyikan total untuk mencegah kebocoran data (*anti data leakage*).
4. **Alur Pratinjau Terikat Sesi Server (`syncPreview`)**:
   - Murni operasi baca (0 insert, 0 update, 0 delete, 0 audit log).
   - Menentukan scope (`global` atau `placement`).
   - Menyimpan token acak 40 karakter di session server (`billing_sync_preview_{token}`) yang mengikat: `admin_id`, `business_date`, `scope_type`, `placement_id`, dan `expires_at` (15 menit).
   - Menampilkan ringkasan estimasi dan disclaimer: *"Pratinjau ini merupakan estimasi terikat waktu. Sinkronisasi akan mengevaluasi dan mengunci setiap penempatan secara mandiri. Hasil aktual akan dilaporkan setelah proses selesai."*
5. **Eksekusi Sinkronisasi Mandiri Tanpa Outer Transaction (`sync`)**:
   - Verifikasi token preview, kesesuaian actor (`admin_id === Auth::id()`), dan drift tanggal bisnis.
   - **Enforcement Scope Server (Anti Scope-Tampering)**: Lingkup eksekusi sepenuhnya ditentukan oleh server session. Payload client yang mencoba mengubah preview single-placement menjadi global atau placement lain ditolak.
   - Memanggil `BillingService::syncAllPlacements` atau `syncPlacementInvoices` **tanpa dibungkus transaksi luar** agar commit per-penempatan tetap terisolasi secara mandiri.
   - Menghapus token preview segera setelah upaya eksekusi dijalankan.
   - Melaporkan hasil aktual secara aman dan transparan (jumlah sukses terbit, sudah ada, dan rincian kegagalan tanpa mengekspos SQL/stack trace).

---

### D. Antarmuka Pengguna (UI Blade Views)
1. **`resources/views/invoices/index.blade.php`**:
   - Judul dan peran adaptif (*role-adaptive*): Admin ("Manajemen Tagihan (Invoice)"), Owner ("Status Tagihan Kos"), Resident ("Tagihan Saya").
   - Banner evaluasi cakupan global hingga bulan operasional berjalan untuk Admin dan Owner.
   - Form filter dan pencarian responsif lengkap dengan input tersembunyi `sort` dan `direction` agar penyaringan mempertahankan pengurutan yang sedang aktif.
   - Kontrol pengurutan interaktif pada header kolom yang didukung backend (`period_month`, `room_number`, `resident_name`, `amount`, `due_on`). Menjaga seluruh filter yang aktif via `request()->except(['page', 'sort', 'direction'])`, secara otomatis mereset halaman paginasi (`page=1`), dan menampilkan indikator visual arah pengurutan (ikon panah naik/turun) yang elegan.
   - Tabel tagihan interaktif dengan snapshot nomor kamar, snapshot nama penghuni, nominal, tanggal jatuh tempo, dan badge status warna (*Lunas*, *Terlambat*, *Jatuh Tempo Hari Ini*, *Belum Jatuh Tempo*).
   - Modal pratinjau sinkronisasi `#syncInvoicesModal` (khusus Admin) dengan proteksi XSS via safe `textContent` DOM nodes, indikator loading spinner, dan proteksi klik ganda submit.
2. **`resources/views/invoices/show.blade.php`**:
   - Halaman detail tagihan read-only.
   - Perbandingan snapshot nomor kamar dan nama penghuni terhadap data kamar dan penghuni operasional saat ini.
   - Tampilan waktu penerbitan (`created_at`) dan waktu pembatalan (`voided_at`) dikonversikan secara presisi ke zona waktu `Asia/Jakarta` (`->setTimezone('Asia/Jakarta')`) sebelum diformat dengan label **WIB**.
   - Kolom bertipe `DATE` kalender murni seperti batas jatuh tempo (`due_on`) dan tanggal bayar (`paid_on`) dipertahankan secara utuh tanpa pergeseran zona waktu.
   - Rincian pembayaran sah (nominal, tanggal bayar, metode, pencatat) atau notifikasi tagihan belum lunas / terlambat.
   - Daftar riwayat pembayaran void (jika ada) dengan timestamp WIB dan catatan bahwa pembayaran void tidak membuat tagihan lunas.
   - Navigasi kembali ke daftar tagihan dan penempatan terkait. Tidak menyediakan tombol edit atau hapus manual (tagihan bersifat immutable dan dikelola sistem).
3. **`resources/views/layouts/partials/sidebar-nav.blade.php`**:
   - Mengaktifkan menu "Tagihan" untuk Administrator (`route('invoices.index')`).
   - Mengaktifkan menu "Status Tagihan" untuk Pemilik (`route('invoices.index')`).
   - Mengaktifkan menu "Tagihan Saya" pada bagian Layanan Mandiri untuk Penghuni (`route('invoices.index')`).

---

## 2. Hasil Pengujian Otomatis (PHPUnit)

Pengujian dilakukan menggunakan basis data MySQL `sim_kos_test` dengan transaksi rollback terisolasi.

### A. Test Suite Khusus T12: `tests/Feature/InvoiceTest.php`
Hasil eksekusi: **29 tests passed, 204 assertions, 0 failures, 0 errors**:
```text
PHPUnit 12.5.35 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.3.32
Configuration: C:\SEMESTER 5\SISTEM INFORMASI PRAKTIKUM\TA\phpunit.xml

Invoice (Tests\Feature\Invoice)
 ✔ Guest is redirected to login
 ✔ User with temp password is redirected to password change
 ✔ Inactive user is logged out or forbidden
 ✔ Admin can view all invoices and global coverage
 ✔ Owner can view all invoices and coverage read only without sync action
 ✔ Resident can only view own invoices and global coverage is strictly hidden
 ✔ Resident cannot access other resident invoice detail anti idor
 ✔ Resident user without resident profile renders safe empty page
 ✔ Unpaid filter includes overdue due today and future due dates
 ✔ Void payment does not make invoice paid
 ✔ Due date boundary around midnight wib and utc normalization
 ✔ Search and filter period and placement status
 ✔ Invalid query parameters sanitized without 500
 ✔ Invoice snapshots remain immutable after room and resident updates
 ✔ Sync preview performs zero database mutations and returns bound token
 ✔ Sync preview for single placement
 ✔ Sync execution creates invoices and invalidates token
 ✔ Scope tampering is rejected
 ✔ Actor mismatch is rejected
 ✔ Expired preview token is rejected
 ✔ Sync execution is strictly idempotent
 ✔ Invoices listing table eager loads relations without n plus one
 ✔ Invoices sorting preserves filters and orders correctly
 ✔ Invoice detail displays created at and voided at in wib timezone without shifting date columns
 ✔ Global preview and sync genuinely creates invoices via billing service
 ✔ Partial failure reporting when one placement fails during sync
 ✔ Retry with new preview does not duplicate invoices or audits
 ✔ Sync rejected when wib date drifts even within token ttl
 ✔ Post preview data changes are recalculated safely upon sync

OK (29 tests, 204 assertions)
```

### B. Test Suite Navigasi: `tests/Feature/LayoutNavigationTest.php`
Hasil eksekusi: **6 tests passed, 49 assertions, 0 failures, 0 errors**.

### C. Pengujian Regresi Penuh Seluruh Aplikasi (`php artisan test`)
Hasil eksekusi: **258 tests passed, 1624 assertions, 0 failures, 0 errors**:
- Baseline sebelum T12: 229 tests passed, 1420 assertions.
- Tambahan bersih T12: **+29 tests, +204 assertions**.
- Total suite saat ini: **258 tests passed, 1624 assertions**.

---

## 3. Bukti Verifikasi Antarmuka Visual (Browser Evidence)

Verifikasi visual dilakukan pada browser riil menggunakan akun demo aplikasi `sim_kos`:

| No | Berkas Tangkapan Layar | Deskripsi |
|---|---|---|
| 1 | `docs/evidence/t12/01_desktop_admin_invoices_index.png` | Tampilan desktop daftar tagihan Administrator. Memperlihatkan judul "Manajemen Tagihan (Invoice)", banner cakupan global yang mengidentifikasi invoice belum lengkap beserta tombol "Sinkronkan Tagihan", form filter pencarian, dan tabel tagihan dengan snapshot kamar/penghuni dan badge status. |
| 2 | `docs/evidence/t12/02_desktop_admin_sync_preview_modal.png` | Modal pratinjau sinkronisasi interaktif `#syncInvoicesModal`. Memperlihatkan ringkasan estimasi invoice baru yang akan terbit, tabel rincian penempatan dan periode yang kurang, teks disclaimer estimasi terikat waktu, serta tombol konfirmasi penerbitan tagihan. |
| 3 | `docs/evidence/t12/03_desktop_invoice_detail.png` | Halaman detail rincian tagihan (`/invoices/1`). Memperlihatkan nomor invoice, badge status "Lunas", nominal sewa kamar, rincian pembayaran sah yang telah terkonfirmasi, serta kartu snapshot finansial yang membandingkan data invoice permanen dengan data kamar/penghuni operasional. |
| 4 | `docs/evidence/t12/04_desktop_owner_invoices_index.png` | Tampilan desktop pemantauan tagihan Pemilik (*Owner*). Memperlihatkan judul "Status Tagihan Kos", banner pemantauan dengan keterangan "Sinkronisasi dijalankan oleh Administrator" tanpa tombol eksekusi, serta seluruh baris tagihan kos. |
| 5 | `docs/evidence/t12/05_mobile_invoices_index.png` | Tampilan responsif mobile (viewport 390x844) pada daftar tagihan. Tata letak form filter, tabel tagihan, dan badge status tertata rapi tanpa overflow horizontal. |
| 6 | `docs/evidence/t12/06_mobile_invoice_detail.png` | Tampilan responsif mobile (viewport 390x844) pada halaman detail tagihan. Rincian finansial, pembayaran, dan kartu snapshot tertata vertikal dengan rapi. |
| 7 | `docs/evidence/t12/07_desktop_resident_invoices_index.png` | Tampilan desktop daftar tagihan Penghuni (*Resident*). Memperlihatkan judul "Tagihan Saya", hanya menampilkan tagihan kamar milik penghuni login, dan banner cakupan global disembunyikan total (*anti-leakage*). |
| 8 | `docs/evidence/t12/08_desktop_invoices_sorting.png` | Tampilan desktop daftar tagihan dengan pengurutan kolom aktif (`?sort=amount&direction=asc`). Memperlihatkan header kolom Nominal dengan indikator visual panah aktif, pemeliharaan filter saringan, dan tabel tagihan yang tersusun terurut rapi. |

---

## 4. Analisis Bisnis, Batas Klaim, dan Pemetaan Rencana

1. **Batas Klaim Biaya Kueri `checkGlobalCoverage` vs Eager Loading**:
   - Eager loading pada controller `InvoiceController::index` (`placement.room`, `placement.resident.user`, `validPayment`) berhasil mengeliminasi N+1 pada perenderan tabel baris tagihan ($O(1)$ kueri relasi terlepas dari jumlah baris per halaman).
   - Di sisi lain, helper `BillingService::checkGlobalCoverage()` mengevaluasi cakupan penempatan menggunakan perulangan chunk dengan pemanggilan evaluasi cakupan per penempatan. Mekanisme chunking membantu efisiensi penggunaan memori PHP, namun tetap membutuhkan kueri verifikasi coverage per penempatan ($O(N)$ penempatan). Hal ini dilaporkan secara transparan dan dipisahkan dalam pengujian.
2. **Lifecycle Token Sesi Sinkronisasi & Rekalkulasi Aman**:
   - Token preview memiliki masa aktif 15 menit dan terikat pada ID admin pembuat, tanggal bisnis, dan scope penempatan.
   - Token ditolak seketika jika tanggal operasional bisnis di WIB telah berganti tengah malam, meskipun masih berada dalam batas TTL 15 menit.
   - Token dihapus (*forget*) seketika saat form sinkronisasi diproses. Jika terjadi perubahan data operasional setelah pratinjau (misal penempatan diakhiri lebih awal), sinkronisasi mengevaluasi status terkini di bawah kunci baris (*row lock*) dan hanya menerbitkan tagihan yang benar-benar wajib sesuai disclaimer estimasi terikat waktu.
3. **Transparansi Batas Transaksi & Isolasi Commit**:
   - Pada kode produksi, `InvoiceController::sync()` memanggil `BillingService::syncAllPlacements()` tanpa membungkusnya dalam transaksi luar (*no outer transaction*). Hal ini memastikan setiap penempatan diproses dan di-commit secara independen dalam transaksinya masing-masing melalui `syncPlacementInvoices()`.
   - Pada metode pengujian feature `test_partial_failure_reporting_when_one_placement_fails_during_sync`, test runner membungkus seluruh pengujian dalam transaksi terluar via trait `DatabaseTransactions` demi isolasi basis data uji. Oleh karena itu, pengujian membuktikan bahwa loop batch menangani pengecualian parsial secara aman, menghasilkan flash warning yang akurat, dan mempertahankan record penempatan yang berhasil beserta auditnya dalam konteks eksekusi tersebut. Kita tidak mengklaim pembuktian commit fisik mandiri di luar transaksi pengujian jika pengujian masih memakai `DatabaseTransactions`.
4. **Pemetaan Kriteria Pengujian Proyek vs Task Implementasi**:
   - **TC-04 (Otorisasi & Kontrol Akses)**: Terpenuhi penuh untuk modul tagihan (Admin full, Owner read-only, Resident strictly own data anti-IDOR, Guest & temp-password redirected).
   - **TC-12 (Logika Periode Pertama / Tanggal Jatuh Tempo & Sinkronisasi Idempoten)**: Terpenuhi penuh (perhitungan periode pertama, penentuan tanggal jatuh tempo berbasis kalender Asia/Jakarta, pratinjau 0 mutasi, eksekusi sinkronisasi idempoten, dan retry bebas duplikasi).
   - **TC-13 (Deteksi Periode Kurang & Sinkronisasi Tagihan)**: Terpenuhi untuk deteksi otomatis invoice yang kurang/hilang di tengah maupun akhir rentang sewa dan pelengkapan tagihan via sinkronisasi transaksional. Aspek dashboard terpadu, pelaporan piutang komprehensif, dan pencetakan tagihan tetap dialokasikan pada tahap berikutnya sesuai roadmap.
   - **T13 (Roadmap Task 13: Pencatatan Pembayaran & Kwitansi)**: Merupakan task implementasi berikutnya dalam roadmap untuk pencatatan pembayaran baru, verifikasi kasir, pembentukan nomor kwitansi, dan pembaruan saldo piutang.
   - **T14 (Roadmap Task 14: Void Pembayaran & Cetak Kuitansi)**: Merupakan task implementasi untuk pembatalan (*void*) pembayaran sah dengan audit trail ketat dan pencetakan fisik kuitansi.
   - *Catatan Penegasan*: ID test case **TC-13** (deteksi periode kurang) dibedakan secara tegas dari task implementasi **T13** (pencatatan pembayaran).
