# Walkthrough Bukti Implementasi & Verifikasi T08: Master Fasilitas (Facility Management)

Dokumen ini mencatat bukti implementasi teknis dan verifikasi komprehensif untuk Task **T08** (Master Fasilitas) pada Sistem Informasi Manajemen Kos (SIM Kos), mencakup CRUD fasilitas lengkap, penempatan kamar versus area bersama, penegakan integritas database dan CHECK constraints, imutabilitas lokasi fasilitas berhistori keluhan, toleransi pembaruan kamar arsip eksisting, otorisasi server-side (`Gate::authorize()`) dan penolakan dini FormRequest (Admin penuh, Pemilik baca-saja, Penghuni 403 Forbidden), transaksi atomik bersama `AuditService` dengan urutan lock konsisten, eliminasi query N+1, antarmuka responsif Blade Bootstrap 5 dengan alasan tindakan tidak tersedia yang terbaca langsung di layar, serta pengujian terotomasi menyeluruh (TC-08 dan TC-09).

---

## 1. Perubahan dan File Utama

### A. Model dan Scopes Fasilitas (`app/Models/Facility.php` & `app/Models/Room.php`)
- **Pencarian Terkelompok (Grouped OR)**:
  - `scopeSearch($query, ?string $term)`: Mengelompokkan pencarian kode fasilitas, nama, area bersama, dan nomor kamar relasi ke dalam sub-closure `where(function($q) { ... })` agar klausul `OR` tidak menerobos filter status arsip, kondisi, atau lokasi.
  - `scopeActive($query)`: Menyaring fasilitas aktif (`whereNull('facilities.archived_at')`).
  - `scopeArchived($query)`: Menyaring fasilitas diarsipkan (`whereNotNull('facilities.archived_at')`).
  - `scopeCondition($query, ?string $condition)`: Menyaring kondisi (`good`, `broken`, `repairing`).
  - `scopeLocationType($query, ?string $locationType)`: Menyaring tipe lokasi (`room`, `shared`).
  - `scopeRoom($query, ?int $roomId)`: Menyaring fasilitas pada kamar tertentu.
- **Pencegahan Kueri N+1**:
  - `hasHistoricalReferences()`: Membaca atribut `has_complaints` jika telah di-eager-load via `withExists`, atau fallback ke `complaints()->exists()`.
  - `canBeDeleted()`: Mengembalikan `true` hanya jika fasilitas tidak memiliki riwayat keluhan sama sekali (`!hasHistoricalReferences()`).
  - `hasActiveComplaints()`: Membaca atribut `has_active_complaints` jika di-eager-load, atau fallback ke `complaints()->whereIn('status', ['open', 'in_progress'])->exists()`.
  - `canBeArchived()`: Fasilitas hanya dapat diarsipkan jika tidak memiliki keluhan berstatus aktif (`!hasActiveComplaints()`).
- **Accessor Tampilan**:
  - `getLocationLabelAttribute()`: Memformat label lokasi ("Kamar 101" atau "Area Bersama: Dapur Lantai 1").
  - `getConditionLabelAttribute()`: Memformat kondisi dalam bahasa Indonesia (Baik, Rusak, Dalam Perbaikan).
  - `getConditionBadgeClassAttribute()`: Kelas badge Bootstrap 5 sesuai kondisi.
- **Penyelarasan Model Kamar (`app/Models/Room.php`)**:
  - Menambahkan method `isArchived(): bool` selaras dengan accessor `is_archived` agar konsisten dipanggil oleh FormRequest dan Service.

### B. Service Transaksional & Atomik (`app/Services/FacilityService.php`)
- **Pintu Mutasi Tunggal**: Seluruh operasi mutasi fasilitas dikelola secara transaksional (`DB::transaction`) bersama `AuditService`.
- **Urutan Row-Locking Konsisten (`lockForUpdate`)**:
  - Pada transaksi yang melibatkan fasilitas dan kamar, baris `Facility` dikunci terlebih dahulu lalu baris target `Room`. Pada pembuatan baru, baris `Room` dikunci sebelum fasilitas baru diinsert.
- **Validasi Mutasi di Dalam Transaksi**:
  - `createFacility`: Memverifikasi kamar tujuan belum diarsipkan dan menormalisasi pasangan `room_id`/`area_name`. Memvalidasi keunikan kode case-insensitive.
  - `updateFacility`: Memeriksa ulang histori keluhan setelah baris fasilitas dikunci. Jika memiliki histori keluhan, pemindahan lokasi (room→room, room→shared, shared→room, perubahan area) ditolak secara tegas. Memastikan pembaruan non-lokasi (nama/kondisi/catatan) tetap diizinkan. Memverifikasi kamar tujuan tidak diarsipkan jika berpindah kamar, namun mengizinkan mempertahankan kamar lama yang diarsipkan.
  - `deleteFacility`: Menolak penghapusan fisik jika fasilitas memiliki riwayat keluhan (`DomainException`), menjaga integritas referensi FK RESTRICT.
  - `archiveFacility`: Menolak pengarsipan jika memiliki keluhan aktif (`open` atau `in_progress`). Idempoten (tidak melakukan mutasi atau mencatat audit jika sudah diarsipkan).
  - `unarchiveFacility`: Mengaktifkan kembali fasilitas diarsipkan. Idempoten (tidak melakukan mutasi atau mencatat audit jika sudah aktif).
- **Integrasi Audit Trail (`module: 'facilities'`)**:
  - Menyusun snapshot `before` dan `after` yang aman dengan serialisasi tanggal ISO-8601 string/null.
  - Kegagalan audit log membatalkan seluruh mutasi database (rollback). Tindakan yang ditolak tidak mencatat audit keberhasilan.

### C. Otorisasi Server-Side (`app/Policies/FacilityPolicy.php`)
- `viewAny`, `view`: Admin dan Pemilik Kos diizinkan. Penghuni kos ditolak penuh (**HTTP 403 Forbidden**).
- `create`, `update`, `delete`, `archive`, `unarchive`: Khusus role `admin`.

### D. Form Request Pre-Validation Authorization (`app/Http/Requests/Facility/`)
1. **`StoreFacilityRequest`**:
   - `authorize()` mengevaluasi `Gate::allows('create', Facility::class)`. Role tanpa izin (Pemilik/Penghuni) langsung mendapat **HTTP 403 Forbidden** bahkan jika mengirim payload invalid.
   - Validasi: `code` wajib unik case-insensitive (`LOWER(code)`) termasuk fasilitas diarsipkan (1–30 karakter) dengan aturan `bail` dan pencegahan TypeError sebelum `strtolower(trim($value))`; `name` (2–100 karakter); `condition` (`good`, `broken`, `repairing`); `location_type` (`room`, `shared`); `room_id` wajib ada & valid jika `room`, dilarang jika `shared`, dan menolak kamar yang sedang diarsipkan; `area_name` wajib jika `shared`, dilarang jika `room`. Input tipe array atau invalid ditolak secara terkendali dengan respons 422 tanpa memicu HTTP 500.
   - `validated()` membersihkan field yang tidak relevan sesuai tipe lokasi dan membuang `archived_at`.
2. **`UpdateFacilityRequest`**:
   - `authorize()` mengevaluasi `Gate::allows('update', $facility)`.
   - Normalisasi perbandingan lokasi: nilai string `"1"` dianggap identik dengan integer `1`.
   - Penanganan elemen disabled: browser tidak mengirim input yang berstatus `disabled` pada fasilitas berhistori keluhan, sehingga request secara eksplisit mengambil nilai kanonikal dari database. Payload lokasi yang dikirim divalidasi ketat (menggunakan `bail` dan tipe data integer/string). Input invalid atau array tidak diubah/dikonversi menjadi nilai kanonikal lalu dilaporkan berhasil, melainkan ditolak dengan error validasi 422.
   - Validasi kode unik case-insensitive dengan mengabaikan ID fasilitas itu sendiri.

### E. Controller Master Fasilitas (`app/Http/Controllers/FacilityController.php`)
- Melindungi seluruh endpoint dengan `Gate::authorize()`.
- Validasi parameter query string (`search`, `tab`, `condition`, `location_type`, `room_id`, `per_page`) via `Validator::make` untuk mencegah serangan array injection atau input ilegal yang berpotensi memicu HTTP 500.
- Eager loading anti-N+1: `with(['room'])` dan `withExists(['complaints as has_complaints', 'complaints as has_active_complaints'])`.
- Penanganan `DomainException` dengan pesan ramah bahasa Indonesia.

### F. Routing & Navigasi
- Mendaftarkan resource route `facilities` serta endpoint `facilities.archive` dan `facilities.unarchive` di dalam middleware grup `['auth', 'active', 'password.not_temp']` pada `routes/web.php`.
- Mengaktifkan menu "Fasilitas" untuk Admin dan "Data Fasilitas" untuk Pemilik pada `resources/views/layouts/partials/sidebar-nav.blade.php`.

### G. Tampilan Antarmuka Blade Bootstrap 5 Responsif (`resources/views/facilities/`)
1. **`index.blade.php`**: Tab status arsip, filter kondisi dan lokasi, pencarian kode/nama/lokasi, paginasi 10/25/50, badge kondisi, modal konfirmasi arsip/hapus, serta **teks alasan tindakan tidak tersedia yang terbaca langsung di layar pada desktop maupun mobile** (bukan hanya tooltip hover).
2. **`create.blade.php`**: Form pendaftaran inventaris dengan toggle interaktif JavaScript vanilla (Kamar vs Area Bersama), teks helper, dan penanda error validasi inline dengan atribut aksesibilitas (`aria-invalid`, `aria-describedby`).
3. **`edit.blade.php`**: Form perubahan inventaris dengan alert edukasi jika lokasi terkunci akibat histori keluhan, dan dropdown kamar yang menyertakan penanda `[Kamar Diarsipkan]` jika fasilitas saat ini berada di kamar arsip.
4. **`show.blade.php`**: Informasi lengkap fasilitas, kartu tabel riwayat keluhan terkait (ID, Subjek, Pelapor, Kamar, Status, Tanggal Lapor), panel aksi master Admin, dan kartu panduan kebijakan integritas data kos.

---

## 2. Hasil Eksekusi Test Suite & Uji Regresi

Seluruh pengujian dijalankan pada database MySQL `sim_kos_test` yang dilindungi oleh safety guard:

### A. Pengujian Unit/Fitur Master Fasilitas (`tests/Feature/FacilityTest.php`)
Perintah eksekusi:
```sh
php vendor/bin/phpunit --testdox tests/Feature/FacilityTest.php
```
Hasil: **40 tests, 277 assertions — SEMUA LULUS (100% PASS)**

Rincian 40 skenario uji:
1. `Admin can access all facility endpoints` — LULUS
2. `Owner can access facility index and show only without mutation actions` — LULUS
3. `Owner gets 403 on facility mutation endpoints` — LULUS
4. `Resident gets 403 on all master facility endpoints` — LULUS
5. `Guest is redirected to login for facility endpoints` — LULUS
6. `Non admin submitting invalid payload gets 403 before validation` — LULUS
7. `Facility code is required and unique including archived facilities` — LULUS
8. `Facility code is unique case insensitively including archived facilities` — LULUS
9. `Facility name and condition validation` — LULUS
10. `Room facility requires valid room id and null area name` — LULUS
11. `Shared facility requires area name and null room id` — LULUS
12. `Archived at cannot be injected via create or update payload` — LULUS
13. `Query filters handle array inputs gracefully without 500 error` — LULUS
14. `Cannot assign facility to archived room on create` — LULUS
15. `Cannot relocate facility to archived room on update` — LULUS
16. `Facility in archived room can update non location info without relocating` — LULUS
17. `Facility with complaints cannot change location type or location details` — LULUS
18. `Facility with complaints can update non location fields` — LULUS
19. `Facility without complaints can relocate freely` — LULUS
20. `Facility placement does not alter room occupancy status` — LULUS
21. `Facility without complaints can be physically deleted` — LULUS
22. `Facility with complaint history cannot be deleted` — LULUS
23. `Facility with open or in progress complaints cannot be archived` — LULUS
24. `Facility with resolved or closed complaints can be archived` — LULUS
25. `Facility can be unarchived` — LULUS
26. `Repeated archive and unarchive actions are idempotent without duplicate audits` — LULUS
27. `Rejected mutations do not persist data or produce success audit logs` — LULUS
28. `Audit failure rolls back facility creation` — LULUS
29. `Audit failure rolls back facility update` — LULUS
30. `Audit failure rolls back facility deletion` — LULUS
31. `Audit failure rolls back facility archive` — LULUS
32. `Audit failure rolls back facility unarchive` — LULUS
33. `Facility search by code and name with grouped conditions` — LULUS
34. `Facility filters by condition and location type` — LULUS
35. `Facility pagination preserves query parameters` — LULUS
36. `Facility show displays complaint history with submitter and placement` — LULUS
37. `Index rendering does not trigger n plus one queries` — LULUS
38. `Create facility rejects array inputs and prevents 500 without audit or data mutation` — LULUS
39. `Update facility without complaints rejects array inputs without audit or data mutation` — LULUS
40. `Update facility with complaints rejects array inputs and does not convert to canonical` — LULUS

### B. Uji Regresi Seluruh Suite Aplikasi
Perintah eksekusi:
```sh
php artisan test
```
Hasil:
```text
Tests: 167 passed (943 assertions)
Duration: 14.37s
Status: OK (0 failure, 0 error)
```
- Baseline T07: 127 tests, 666 assertions.
- Capaian T08: **167 tests, 943 assertions** (+40 tests baru, +277 assertions baru, nol regresi).

### C. Kompilasi Aset Frontend
Perintah eksekusi:
```sh
npm run build
```
Hasil:
- Vite v8.3.0 selesai dalam 184ms.
- Bundle CSS: `public/build/assets/app-CVq8h3i9.css` (236.46 kB).
- Bundle JS: `public/build/assets/app-6x2dcqrK.js` (79.03 kB).
- `Test-Path public/hot`: `False` (menggunakan build bundle lokal tanpa dependensi Vite dev server).

### D. Kompilasi Template Blade
Perintah eksekusi:
```sh
php artisan view:cache
```
Hasil:
- `Blade templates cached successfully.` (semua sintaks Blade valid).

---

## 3. Hasil Pemeriksaan Visual Browser & Bukti Nyata

Pemeriksaan antarmuka dilakukan menggunakan browser subagent pada server lokal `http://127.0.0.1:8000` dengan resolusi Desktop (1366×768) dan Mobile (390×844):

| No | File Screenshot | Tautan Bukti Relatif | Deskripsi Verifikasi |
|:--:|---|---|---|
| 1 | `01-admin-facilities-index-desktop.png` | [t08/01-admin-facilities-index-desktop.png](t08/01-admin-facilities-index-desktop.png) | Halaman daftar master fasilitas Admin desktop (1366×768), menu aktif, tab filter, dan tombol tambah fasilitas. |
| 2 | `02-admin-facilities-create-validation.png` | [t08/02-admin-facilities-create-validation.png](t08/02-admin-facilities-create-validation.png) | Form tambah fasilitas menampilkan pesan validasi server bahasa Indonesia inline saat disubmit kosong. |
| 3 | `03-admin-facility-create-success.png` | [t08/03-admin-facility-create-success.png](t08/03-admin-facility-create-success.png) | Berhasil menambahkan fasilitas baru dengan notifikasi flash alert hijau di halaman daftar fasilitas. |
| 4 | `04-admin-facility-show-desktop.png` | [t08/04-admin-facility-show-desktop.png](t08/04-admin-facility-show-desktop.png) | Halaman detail fasilitas Admin: kartu data spesifikasi, lokasi kamar/area, kondisi, dan kartu riwayat keluhan terkait. |
| 5 | `05-admin-facility-edit-desktop.png` | [t08/05-admin-facility-edit-desktop.png](t08/05-admin-facility-edit-desktop.png) | Form edit fasilitas Admin dengan input data awal terisi dan opsi toggle lokasi. |
| 6 | `06-admin-facility-archive-modal.png` | [t08/06-admin-facility-archive-modal.png](t08/06-admin-facility-archive-modal.png) | Modal interaktif Bootstrap 5 konfirmasi pengarsipan fasilitas dengan penjelasan dampak pengarsipan. |
| 7 | `07-admin-facilities-archived-tab.png` | [t08/07-admin-facilities-archived-tab.png](t08/07-admin-facilities-archived-tab.png) | Tab "Fasilitas Diarsipkan" menampilkan fasilitas berstatus arsip dengan badge abu-abu dan tombol "Aktifkan". |
| 8 | `08-admin-facilities-mobile-390px.png` | [t08/08-admin-facilities-mobile-390px.png](t08/08-admin-facilities-mobile-390px.png) | Tampilan daftar fasilitas responsif pada viewport mobile 390px, filter vertikal rapi, teks alasan tindakan terbaca. |
| 9 | `09-owner-facilities-index-desktop.png` | [t08/09-owner-facilities-index-desktop.png](t08/09-owner-facilities-index-desktop.png) | Tampilan daftar fasilitas Pemilik (Owner): label "Data Fasilitas", tanpa tombol Tambah, Edit, Arsip, atau Hapus (read-only). |
| 10 | `10-owner-facility-show-desktop.png` | [t08/10-owner-facility-show-desktop.png](t08/10-owner-facility-show-desktop.png) | Tampilan detail fasilitas Pemilik: data lengkap dan riwayat keluhan dalam mode baca-saja tanpa tombol aksi mutasi. |

---

## 4. Bagian yang Belum Selesai atau Belum Diuji

1. **Modul Keluhan Mandiri Penghuni (Fase 4: T16–T17)**:
   - Pengujian relasi keluhan pada T08 menggunakan data fixture keluhan nyata pada database uji `sim_kos_test`. Modul keluhan mandiri di portal penghuni dan alur penanganan keluhan operasional dijadwalkan pada Fase 4.
2. **Modul Billing & Penempatan Kamar (T09, T10, T11)**:
   - **T09: BillingService**: Pembentukan periode tagihan bulan kalender dari `started_on` hingga bulan berjalan/akhir sewa, bulan pertama dan terakhir dikenai tarif penuh TANPA prorata, tarif invoice berasal dari snapshot `agreed_monthly_rate` penempatan bukan tarif kamar terbaru, preview tagihan, deteksi kelengkapan periode, serta sinkronisasi tagihan idempoten (TC-12, TC-13, TC-33 sesuai `docs/plan/03-proses-dan-informasi.md`).
   - **T10: Mulai Penempatan**: `PlacementService`, penempatan kamar aktif, penolakan kamar terisi/arsip, snapshot awal tagihan (TC-10, TC-11).
   - **T11: Akhiri Penempatan**: Pengosongan kamar, pemenuhan tagihan akhir sewa, verifikasi status.

---

## 5. Ringkasan Status

Task **T08: Master Fasilitas** dinyatakan **SELESAI** secara penuh dan memenuhi seluruh kriteria penerimaan termasuk pengetatan validasi tipe input dan pencegahan HTTP 500. Baseline pengujian meningkat menjadi **167 passed, 943 assertions**. Langkah berikutnya adalah mempersiapkan implementasi **T09: BillingService** (periode bulan kalender, tarif penuh tanpa prorata, snapshot `agreed_monthly_rate` penempatan, deteksi kelengkapan, dan sinkronisasi tagihan idempoten; dilanjutkan T10: Mulai Penempatan dan T11: Akhiri Penempatan sesuai `docs/plan/03-proses-dan-informasi.md`).
