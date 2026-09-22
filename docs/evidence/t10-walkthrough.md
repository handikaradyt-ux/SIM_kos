# Walkthrough T10: Mulai Penempatan (Check-in / New Placement)

Dokumen ini mendokumentasikan implementasi teknis, pengujian komprehensif, dan verifikasi antarmuka visual untuk **Task T10: Mulai Penempatan** pada Sistem Informasi Manajemen (SIM) Kos. Sesuai batasan roadmap proyek, T10 berfokus murni pada proses mulai penempatan (*check-in*), daftar penempatan, detail penempatan, dan penerbitan otomatis invoice pertama periode awal sewa secara terpadu melalui `BillingService`. Fitur pengakhiran sewa (*check-out*) dijadwalkan pada T11, antarmuka penagihan pada T12, dan pencatatan pembayaran pada T13.

---

## 1. Ringkasan Perubahan Kode & Arsitektur

### A. Kebijakan Otorisasi: `app/Policies/PlacementPolicy.php`
- `viewAny(User $user): bool`: Diizinkan untuk Administrator aktif dan Pemilik aktif (`in_array($user->role?->code, ['admin', 'owner'], true) && $user->is_active`).
- `view(User $user, Placement $placement): bool`: Diizinkan untuk Administrator aktif dan Pemilik aktif. Penghuni dilarang mengakses (**HTTP 403 Forbidden**) termasuk terhadap data sewanya sendiri via master penempatan.
- `create(User $user): bool`: Hanya Administrator aktif (`$user->role?->code === 'admin' && $user->is_active`). Pemilik dan Penghuni memperoleh **HTTP 403 Forbidden**.

---

### B. Service Layer: `app/Services/PlacementService.php`
Pintu masuk mutasi penempatan kamar yang mengintegrasikan penguncian baris, verifikasi token preview sesi, dan transaksi atomik:
1. **`previewPlacement(int $residentId, int $roomId, User $actor): array`**:
   - Operasi murni *read-only* (0 insert, 0 update, 0 delete, 0 audit log).
   - Memvalidasi bahwa actor adalah Administrator aktif di basis data (`ensureActorIsAdmin`).
   - Memeriksa kelayakan kamar: kamar ditemukan, tidak diarsipkan (`!$room->isArchived()`), dan belum terisi (`!$room->activePlacement()->exists()`).
   - Memeriksa kelayakan penghuni: penghuni ditemukan, tidak diarsipkan (`!$resident->isArchived()`), akun pengguna aktif ber-role `resident`, dan belum memiliki penempatan aktif.
   - Mendeteksi riwayat penempatan selesai pada bulan berjalan (`same_month_warning`) untuk memberikan peringatan bisnis bahwa tagihan bulan berjalan tetap diterbitkan penuh tanpa prorata.
   - Menghitung proyeksi tanggal mulai (hari ini `Asia/Jakarta`) dan memanfaatkan helper `BillingService::calculateDueDate` menggunakan representasi objek `Placement` in-memory (tanpa menyimpan baris ke basis data).
   - Menghasilkan token acak unik aman (`Str::random(40)`) dan menyimpannya di server session (`placement_preview_{token}`) yang mengikat:
     - `token`: string token
     - `admin_id`: ID Administrator pembuat preview
     - `resident_id`: ID penghuni terpilih
     - `room_id`: ID kamar terpilih
     - `room_rate`: Tarif bulanan kamar saat preview dibuat
     - `started_on`: Tanggal bisnis kalender `Asia/Jakarta` (`Y-m-d`)
     - `expires_at`: Waktu kedaluwarsa UNIX timestamp (15 menit)
   - Mengembalikan payload data siap render beserta `preview_token`.

2. **`startPlacement(int $residentId, int $roomId, string $previewToken, User $actor): Placement`**:
   - **Verifikasi Actor**: Memverifikasi actor adalah Administrator aktif langsung dari database.
   - **Verifikasi Token Preview di Session**:
     - Mengambil data sesi `placement_preview_{$previewToken}`.
     - Menolak jika token tidak ditemukan atau telah kedaluwarsa (`now()->timestamp > $preview['expires_at']`).
     - Menolak jika `admin_id` pada preview berbeda dengan `$actor->id`.
     - Menolak jika pasangan `resident_id` atau `room_id` tidak cocok dengan token preview.
   - **Buka Transaksi Atomik (`DB::transaction`)**:
     - **Urutan Penguncian Baris Pessimistic**: $\text{Room} \longrightarrow \text{Resident} \longrightarrow \text{User}$ (`lockForUpdate`).
     - **Evaluasi Ulang di Bawah Kunci**: Memastikan kamar dan penghuni belum diarsipkan, akun user aktif, dan keduanya belum memiliki penempatan aktif.
     - **Penentuan Tanggal Bisnis Tunggal**: Mengambil satu tanggal bisnis kalender setelah memperoleh lock: `$businessDate = Carbon::now('Asia/Jakarta')->startOfDay();`. Tanggal ini digunakan konsisten untuk `started_on` penempatan dan parameter `asOfDate` pemanggilan `BillingService`.
     - **Pemeriksaan Drift Pasca-Lock**:
       - Drift Tarif: jika `(int)$lockedRoom->monthly_rate !== (int)$preview['room_rate']`, transaksi ditolak dengan instruksi preview ulang.
       - Drift Tanggal Bisnis: jika `$businessDate->toDateString() !== $preview['started_on']` (misal proses melewati tengah malam/pergantian bulan), transaksi ditolak tanpa mutasi.
     - **Penyimpanan Placement**: Menyimpan record `Placement` dengan `agreed_monthly_rate` bersumber otoritatif dari basis data (`$lockedRoom->monthly_rate`), bukan dari payload form.
     - **Penanganan Konflik Unique Database**: Menangkap error kode MySQL `1062` pada index `active_room_id` atau `active_resident_id` dan menerjemahkannya secara bersih ke pesan bisnis "Kamar/Penghuni sedang terisi oleh penempatan aktif".
     - **Penerbitan Invoice Periode Pertama**: Memanggil `BillingService::syncPlacementInvoices($placement, $verifiedActor, $businessDate)`. Menghasilkan tepat 1 invoice periode pertama dan mencatat audit log invoice. Setiap kegagalan pada tahap ini melempar exception dan membatalkan seluruh transaksi secara atomik.
     - **Pencatatan Audit Log Penempatan**: Mencatat audit log melalui `AuditService::log()` (`module: 'placements'`, `action: 'create'`). Jika audit penempatan gagal, transaksi dibatalkan utuh.
     - **Invalidasi Sesi Preview**: Menghapus `session()->forget("placement_preview_{$previewToken}")`.

---

### C. HTTP Layer: Form Requests & Controller
1. **`app/Http/Requests/Placement/PreviewPlacementRequest.php`**:
   - Otorisasi Admin aktif via `Gate::allows('create', Placement::class)` dievaluasi sebelum validasi.
   - Validasi ketat `resident_id` dan `room_id` bertipe integer dengan aturan `bail` anti-array injection.

2. **`app/Http/Requests/Placement/StorePlacementRequest.php`**:
   - Memvalidasi `resident_id`, `room_id`, dan `preview_token` (string mandatory, max 100).
   - Menolak input array pada setiap field dengan pesan validasi terkendali (anti-500).
   - Menolak dan membuang field manipulatif (`started_on`, `agreed_monthly_rate`, `created_by`, `status`).

3. **`app/Http/Controllers/PlacementController.php`**:
   - `index(Request $request)`: Menampilkan daftar penempatan dengan filter pencarian nama/telepon/nomor kamar, filter tab status (Aktif, Selesai, Semua), pagination (10, 25, 50), dan eager loading anti-N+1 (`resident.user`, `room`, `creator`, `ender`, `withCount('invoices')`).
   - `create()`: Memuat daftar kamar kosong aktif (`Room::active()->vacant()`) dan penghuni aktif belum memiliki kamar.
   - `preview(PreviewPlacementRequest $request)`: Memanggil `PlacementService::previewPlacement` dan mengembalikan JSON 200 pada kondisi valid, atau JSON 422 terkendali jika kamar/penghuni tidak memenuhi syarat (bukan HTTP 500).
   - `store(StorePlacementRequest $request)`: Mengeksekusi `PlacementService::startPlacement` dan melakukan redirect PRG (*Post-Redirect-Get*) ke detail penempatan dengan flash message sukses.
   - `show(Placement $placement)`: Menampilkan detail penempatan dengan eager loading `invoices` yang memuat relasi `validPayment` via `withExists('validPayment')` dan `with('validPayment')` untuk mencegah N+1 query.

---

### D. Penyesuaian Model & Routing
- **`app/Models/Resident.php`**: Menambahkan method `isArchived(): bool` selaras dengan `Room::isArchived()` untuk memudahkan pengecekan status arsip.
- **`routes/web.php`**: Mendaftarkan rute penempatan (`placements.index`, `create`, `preview`, `store`, `show`) di bawah middleware grup `['auth', 'active', 'password.not_temp']`.
- **`resources/views/layouts/partials/sidebar-nav.blade.php`**: Mengaktifkan menu navigasi "Penempatan" untuk Administrator dan "Data Penempatan" untuk Pemilik.

---

### E. Antarmuka Pengguna (Blade Views)
1. **`resources/views/placements/index.blade.php`**:
   - Header dilengkapi tombol "+ Mulai Penempatan Baru" (khusus Admin).
   - Tab status penempatan ("Aktif", "Selesai", "Semua Riwayat") dengan badge jumlah data.
   - Tabel responsif menampilkan: Kamar, Penghuni, Tanggal Mulai/Selesai, Tarif Sewa Disepakati, Jumlah Tagihan, Badge Status, dan Tombol Detail.
2. **`resources/views/placements/create.blade.php`**:
   - Dropdown pilihan Kamar dan Penghuni yang aktif dan belum memiliki penempatan.
   - Catatan tanggal mulai operasional yang ditentukan oleh server (hari ini WIB).
   - Tombol "Lihat Preview & Konfirmasi" memicu pemanggilan asinkron ke `POST /placements/preview`.
   - **Penanganan Respons Preview Lama & Invalidasi State**:
     - Perubahan pilihan kamar atau penghuni memicu `invalidatePreviewState()`: membatalkan HTTP fetch yang sedang berjalan via `AbortController.abort()`, menaikkan `requestSequence`, mengosongkan `preview_token`, menonaktifkan tombol submit modal, menutup modal jika terbuka, dan memulihkan teks/spinner tombol secara instan.
     - Validasi ganda sebelum dan setelah `await response.json()` untuk memeriksa `currentSeq === requestSequence` serta kecocokan `room_id` dan `resident_id` dengan pilihan terkini.
     - Verifikasi kesesuaian payload dengan entitas yang diminta; respons lama atau berbeda pasangan entitas tidak dapat membuka modal maupun mengaktifkan tombol konfirmasi.
   - Render data preview menggunakan `.textContent` murni untuk mencegah potensi XSS.
   - Modal Bootstrap 5 konfirmasi menampilkan rincian kontrak dan proyeksi tagihan pertama.
   - Peringatan khusus `same_month_warning` jika penghuni pernah selesai sewa di bulan yang sama.
   - Tombol "Konfirmasi & Mulai Penempatan" berstatus awal `disabled` dan dilengkapi proteksi *disable-on-click* untuk mencegah *double submit*.
3. **`resources/views/placements/show.blade.php`**:
   - Kartu ringkasan penempatan dengan badge status "Penempatan Aktif" / "Penempatan Selesai".
   - Kartu Informasi Penghuni dan Kartu Informasi Kamar.
   - Rincian kontrak sewa dan tanggal pencatatan oleh Administrator.
   - Tabel riwayat tagihan dengan kolom Periode, Jatuh Tempo, Nominal, dan Status Pembayaran (Lunas / Belum Lunas) secara *read-only* tanpa tombol pembayaran atau pengakhiran sewa.

---

## 2. Test Suite & Hasil Pengujian Otomatis

Pengujian komprehensif dieksekusi pada database MySQL `sim_kos_test` dengan verifikasi guard lingkungan otomatis.

### A. Test Suite Penempatan: `tests/Feature/PlacementTest.php`
Seluruh 18 pengujian lulus dengan 153 assertions:

```text
Placement (Tests\Feature\Placement)
 ✔ Admin can preview and start placement atomically with invoice and audit
 ✔ Submit without valid preview token is rejected without mutation
 ✔ Submit with tampered or expired or different admin token is rejected
 ✔ Submit after room rate changed post preview is rejected
 ✔ Preview before midnight then submit after date change is rejected
 ✔ Preview is strictly read only
 ✔ Preview returns controlled json 422 when unavailable
 ✔ Submit rejects unavailable room or resident under lock
 ✔ Manipulated inputs are ignored and determined authoritatively by server
 ✔ Due date rules with frozen server times
 ✔ Double submit or conflict does not leave partial data
 ✔ Atomic rollback on billing service failure
 ✔ Atomic rollback on invoice audit failure
 ✔ Atomic rollback on final placement audit failure with real billing and invoice
 ✔ Authorization matrix for placement endpoints
 ✔ Input array on preview and store is handled gracefully anti 500
 ✔ Same month re placement triggers warning flag
 ✔ Placement show and index eager load valid payment preventing n plus one

OK (18 tests, 153 assertions)
```

#### Sorotan Pengujian Rollback Atomik 3 Skenario:
1. **Skenario A (`test_atomic_rollback_on_billing_service_failure`)**: Kegagalan `BillingService::syncPlacementInvoices` melempar exception terkendali. Transaksi dibatalkan secara penuh: 0 placement, 0 invoice, 0 audit log.
2. **Skenario B (`test_atomic_rollback_on_invoice_audit_failure`)**: Memakai `BillingService` nyata yang telah menerbitkan invoice, namun `AuditService` dipaksa gagal khusus saat logging `module: 'invoices'`. Transaksi rollback utuh.
3. **Skenario C (`test_atomic_rollback_on_final_placement_audit_failure_with_real_billing_and_invoice`)**: Rollback tahap akhir menggunakan `BillingService` nyata. Invoice pertama dan audit invoice terbukti benar-benar dieksekusi dalam transaksi (`invoiceAuditCallCount >= 1`), lalu `AuditService` dipaksa melempar exception khusus pada tahap akhir `module: 'placements'`. Exception wajib tertangkap (tes gagal jika tidak muncul), dan seluruh record kembali persis ke baseline (kamar/penghuni tidak memiliki penempatan aktif).

### B. Hasil Uji Rangkaian Penuh Proyek (`php artisan test`)
Seluruh rangkaian pengujian sistem SIM Kos dari T01 hingga T10 lulus 100% tanpa kegagalan:

```text
Tests:    204 passed (1246 assertions)
Duration: 18.43s
```

---

## 3. Bukti Verifikasi Visual Browser

Pengujian browser visual dilakukan menggunakan akun demo terpisah pada lingkungan dev dengan resolusi Desktop (1280x800) dan Mobile (390x844).

### 1. Daftar Penempatan (Admin - Desktop)
Admin melihat menu "Penempatan" aktif, daftar penempatan yang sedang berjalan, dan tombol "+ Mulai Penempatan Baru".
![Daftar Penempatan Admin Desktop](file:///c:/SEMESTER%205/SISTEM%20INFORMASI%20PRAKTIKUM/TA/docs/evidence/t10/01-admin-placements-index.png)

### 2. Modal Preview & Konfirmasi Penempatan (Admin - Desktop)
Modal preview terverifikasi server memuat data penghuni (Rian Hidayat), kamar (Kamar A-103 Deluxe - Rp 1.200.000), tanggal mulai kalender bisnis hari ini (21 September 2026), serta proyeksi tagihan pertama periode September 2026 dengan jatuh tempo hari ke-21 (karena mulai > tanggal 5).
![Modal Preview Penempatan Desktop](file:///c:/SEMESTER%205/SISTEM%20INFORMASI%20PRAKTIKUM/TA/docs/evidence/t10/02-admin-placement-preview-modal.png)

### 3. Detail Penempatan & Invoice Pertama (Admin - Desktop)
Halaman detail penempatan menampilkan kontrak aktif, tarif kesepakatan Rp 1.200.000 / bulan, rincian penghuni, kamar, serta invoice periode pertama yang telah berstatus Belum Lunas tanpa tombol mutasi pembayaran.
![Detail Penempatan Admin Desktop](file:///c:/SEMESTER%205/SISTEM%20INFORMASI%20PRAKTIKUM/TA/docs/evidence/t10/03-admin-placement-show.png)

### 4. Detail Penempatan (Admin - Mobile 390px)
Tampilan responsif pada perangkat mobile dengan navigasi adaptif, kartu informasi vertikal, dan tata letak yang ramah sentuhan.
![Detail Penempatan Admin Mobile](file:///c:/SEMESTER%205/SISTEM%20INFORMASI%20PRAKTIKUM/TA/docs/evidence/t10/04-admin-placement-mobile.png)

### 5. Daftar Data Penempatan (Pemilik - Desktop)
Pemilik kos memiliki akses pemantauan data penempatan (*read-only*). Tombol "Mulai Penempatan Baru" tidak ditampilkan.
![Daftar Penempatan Pemilik Desktop](file:///c:/SEMESTER%205/SISTEM%20INFORMASI%20PRAKTIKUM/TA/docs/evidence/t10/05-owner-placements-index.png)

### 6. Detail Penempatan (Pemilik - Desktop)
Pemilik dapat memantau seluruh rincian kontrak dan tagihan penempatan secara transparan tanpa tombol aksi mutasi.
![Detail Penempatan Pemilik Desktop](file:///c:/SEMESTER%205/SISTEM%20INFORMASI%20PRAKTIKUM/TA/docs/evidence/t10/06-owner-placement-show.png)

### 7. Uji Pembatalan Request Preview Usang (Browser Real-Time Test)
Pengujian browser dengan respons preview diperlambat (3 detik). Saat request Kamar B-201 masih berjalan, admin mengubah pilihan ke Kamar B-202. Request lama berhasil di-abort, modal B-201 tidak terbuka, tombol preview segera pulih ke status siap, dan request baru Kamar B-202 membuka modal dengan data Kamar B-202 secara akurat:
![Verifikasi Pembatalan Preview Usang Browser](file:///c:/SEMESTER%205/SISTEM%20INFORMASI%20PRAKTIKUM/TA/docs/evidence/t10/07-preview-abort-and-recovery.png)

---

## 4. Kesimpulan & Status Roadmap
Seluruh dua koreksi penutupan Task T10 (penanganan respons preview lama dengan AbortController dan pengujian rollback tahap akhir 3 skenario) telah diselesaikan secara tuntas dan terbukti di browser maupun pengujian otomatis. Sistem siap melanjutkan ke Task T11: Selesai Penempatan (*Check-out* / Penghentian Kontrak).
