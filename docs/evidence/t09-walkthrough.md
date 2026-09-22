# Walkthrough T09: BillingService (Fondasi Tagihan & Sinkronisasi Idempoten)

Dokumen ini mendokumentasikan implementasi teknis, pengujian, dan verifikasi untuk **Task T09: BillingService** pada Sistem Informasi Manajemen (SIM) Kos beserta penyelesaian koreksi penutupan. Sesuai dengan roadmap proyek dan penegasan arsitektur, T09 berfokus secara murni pada lapisan logika domain (*pure service layer*) tanpa membuka route HTTP, controller, antarmuka Blade, ataupun scheduler otomatis (antarmuka penagihan dijadwalkan pada T12).

---

## 1. Ringkasan Perubahan Kode & Arsitektur

### A. Service Layer: `app/Services/BillingService.php`
Service mandiri yang dibangun untuk menangani seluruh siklus penentuan periode sewa, jatuh tempo, snapshot tagihan, deteksi kelengkapan periode, preview tagihan, serta sinkronisasi transaksional atomik dengan audit trail.

Method yang diimplementasikan:
1. **`parseModelDate(mixed $date): Carbon`**:
   - Mengekstrak representasi tanggal kalender (`Y-m-d`) dari objek tanggal Eloquent (`Carbon` UTC) atau string, kemudian memetakannya secara tepat ke awal hari (`00:00:00`) dalam zona waktu bisnis `Asia/Jakarta`.
   - Mengatasi pergeseran waktu 7 jam akibat perbedaan offset UTC vs Jakarta pada kolom bertipe `DATE` MySQL, tanpa memutasi objek tanggal asli pada model penempatan.

2. **`resolveReferenceDate(?CarbonInterface $asOfDate = null): Carbon`**:
   - Menetapkan tanggal acuan bisnis di `Asia/Jakarta`.
   - Menolak tanggal acuan di masa depan (`asOfDate > now(Asia/Jakarta)`) dengan `InvalidArgumentException`, memastikan tidak ada celah *bypass* penerbitan tagihan sebelum waktunya. Pengujian waktu masa depan wajib membekukan waktu server secara resmi via `Carbon::setTestNow()`.

3. **`parsePeriodMonth(CarbonInterface|string $periodMonth): Carbon`**:
   - Memvalidasi format periode secara eksplisit (`Y-m-01` atau `Y-m-d`). Menolak format string tanggal ambigu atau tidak valid dengan `InvalidArgumentException`.
   - Memastikan hasil selalu dinormalisasi ke tanggal 1 (`YYYY-MM-01`).

4. **`getRequiredPeriods(Placement $placement, ?CarbonInterface $asOfDate = null, ?CarbonInterface $maxPeriodMonth = null): array`**:
   - Menghitung seluruh periode bulan kalender yang wajib ditagih sejak awal sewa hingga tanggal acuan atau tanggal keluar.
   - **Kontrak Tanggal Pasti**: Mengembalikan array kosong `[]` (bukan exception) apabila:
     - Tanggal mulai penempatan (`started_on`) berada setelah tanggal acuan (`asOfDate`), termasuk jika keduanya berada dalam bulan kalender yang sama (misal: acuan 5 September, mulai 10 September $\rightarrow$ belum aktif per tanggal acuan).
     - Batas `maxPeriodMonth` diberikan sebelum bulan mulai penempatan.
   - Batas akhir dihitung dari nilai minimum antara: bulan `ended_on` (jika penempatan sudah selesai), bulan `$asOfDate`, dan bulan `$maxPeriodMonth` (jika ada). Parameter `$maxPeriodMonth` hanya mempersempit rentang dan tidak dapat melampaui bulan berjalan atau bulan keluar.
   - Perulangan bulan kalender dilakukan menggunakan penambahan bulan (`addMonthNoOverflow()`) sehingga aman terhadap variasi hari akhir bulan, pergantian tahun Desember-Januari, dan tahun kabisat Februari.

5. **`calculateDueDate(Placement $placement, CarbonInterface|string $periodMonth): string`**:
   - Menghitung tanggal jatuh tempo (`due_on`) dengan format `'YYYY-MM-DD'`.
   - **Peran & Batas Fungsi**: Merupakan helper kalkulasi tanggal kalender murni dalam rentang masa sewa kontrak (`startMonth <= period <= endedMonth`). Helper ini tidak mengevaluasi apakah periode berada di masa depan terhadap hari ini karena dapat digunakan untuk proyeksi jadwal kalender.
   - **Aturan Jatuh Tempo Resmi**:
     - Periode pertama sewa: jika `started_on` jatuh pada hari setelah tanggal 5 (`day > 5`), maka `due_on = started_on`. Jika `started_on` pada tanggal 1 s/d 5, maka `due_on` jatuh pada tanggal 5 bulan tersebut (`YYYY-MM-05`).
     - Periode kedua dan seterusnya: selalu jatuh tempo pada tanggal 5 setiap bulan (`YYYY-MM-05`).

6. **`calculateInvoicePayload(Placement $placement, CarbonInterface|string $periodMonth, User $actor, ?CarbonInterface $asOfDate = null): array`**:
   - Menghasilkan payload data siap simpan untuk tabel `invoices` yang berstatus siap terbit (*issuance-ready*).
   - **Perlindungan Langsung & Konsistensi dengan `getRequiredPeriods`**:
     1. Menolak penempatan yang belum mulai per tanggal acuan (`started_on > refDate`), termasuk jika mulai setelah hari ini dalam bulan yang sama.
     2. Menolak periode sebelum tanggal mulai penempatan (`period < startMonth`).
     3. Menolak periode setelah bulan berjalan (`period > refMonth`); tagihan masa depan ditolak secara tegas.
     4. Menolak periode setelah bulan penempatan berakhir (`period > endedMonth`).
   - Memverifikasi actor melalui `ensureActorIsAdmin()`, menggunakan identitas actor terverifikasi untuk kolom `created_by`.
   - Mengambil nominal tagihan dari snapshot `placements.agreed_monthly_rate` (bukan `rooms.monthly_rate` terbaru), menjamin imutabilitas tarif sewa sesuai kontrak awal.
   - Mengambil snapshot nama penghuni (`residents.name`) dan nomor kamar (`rooms.number`).

7. **`ensureActorIsAdmin(User $actor): User`**:
   - Memverifikasi actor telah tersimpan di basis data (`$actor->exists && $actor->id`). Menolak actor yang belum tersimpan.
   - Membaca ulang state terbaru langsung dari database (`User::with('role')->find($actor->id)`), bukan mempercayai properti objek in-memory lama yang mungkin sudah usang.
   - Memastikan `is_active === true` dan `role->code === 'admin'`. Mengembalikan objek actor terverifikasi untuk dipakai pada `created_by` dan audit log.

8. **`previewPlacement(Placement $placement, ?CarbonInterface $asOfDate = null, ?CarbonInterface $maxPeriodMonth = null): array`**:
   - Operasi murni *read-only* untuk melihat proyeksi tagihan yang belum dibuat pada satu penempatan tanpa melakukan mutasi basis data ataupun pembuatan audit log.

9. **`checkCoverage(Placement $placement, ?CarbonInterface $asOfDate = null, ?CarbonInterface $maxPeriodMonth = null): array`**:
   - Operasi murni *read-only* untuk memeriksa kelengkapan invoice pada satu penempatan.
   - Menghitung `required_count`, `existing_count` (hanya dalam rentang cakupan), `missing_count`, dan daftar `missing_periods`.

10. **`checkGlobalCoverage(?CarbonInterface $asOfDate = null): array`**:
    - Operasi murni *read-only* untuk memeriksa kelengkapan tagihan di seluruh penempatan pada sistem.
    - Membekukan satu tanggal acuan tunggal di awal proses untuk seluruh penempatan.
    - Menyertakan seluruh penempatan tanpa membatasi hanya yang aktif: penempatan yang sudah selesai (`ended_on IS NOT NULL`) tetap diperiksa kewajiban historisnya.

11. **`syncPlacementInvoices(Placement $placement, User $actor, ?CarbonInterface $asOfDate = null, ?CarbonInterface $maxPeriodMonth = null): array`**:
    - Dijalankan di dalam `DB::transaction()`. Melakukan `lockForUpdate()` pada penempatan untuk mencegah *race condition*.
    - Menggunakan verified actor dari `ensureActorIsAdmin`.
    - Menerbitkan invoice yang belum ada dan mencatat log audit (`AuditService::log()`) secara atomik.
    - **Melempar kembali setiap exception ke lapisan pemanggil** agar transaksi induk (misalnya transaksi mulai penempatan T10 atau selesai penempatan T11) dapat melakukan rollback penuh.

12. **`syncAllPlacements(User $actor, ?CarbonInterface $asOfDate = null, ?CarbonInterface $maxPeriodMonth = null): array`**:
    - Memverifikasi actor adalah Admin aktif terverifikasi di awal batch.
    - Menetapkan satu tanggal acuan seragam di awal batch.
    - Memproses seluruh penempatan bertahap (chunking 50).
    - **Batas Transaksi Batch**: Setiap penempatan diproses mandiri melalui `syncPlacementInvoices`. Isolasi commit mandiri per penempatan HANYA berlaku saat `syncAllPlacements` dipanggil tanpa transaksi luar yang membungkus batch. Apabila pemanggil membungkus batch ini dalam transaksi luar, rollback pada transaksi luar tetap membatalkan seluruh perubahan.
    - Menangkap kegagalan per penempatan dan mencatatnya ke `failed_placements` dengan pesan kesalahan aman tanpa kebocoran raw SQL, kredensial, atau stack trace.

---

### B. Penyesuaian Model: `app/Models/Placement.php`
- `scopeActive(Builder $query): Builder`: Menyaring penempatan aktif (`ended_on IS NULL`).
- `scopeEnded(Builder $query): Builder`: Menyaring penempatan selesai (`ended_on IS NOT NULL`).
- `isActive(): bool`: Helper boolean untuk memeriksa status aktif penempatan.

---

## 2. Test Suite: `tests/Feature/BillingServiceTest.php`

Dibangun test suite komprehensif sebanyak 19 metode pengujian (150 assertions) yang dijalankan pada database MySQL `sim_kos_test` dengan proteksi guard ketat:

| No | Nama Test Method | Skenario Bisnis & Verifikasi |
|---|---|---|
| 1 | `test_due_date_and_full_amount_for_started_on_1st_5th_and_10th` | **TC-12**: Tanggal 1 & 5 jatuh tempo tgl 5; tanggal 10 jatuh tempo tgl 10. Tarif sewa penuh tanpa prorata. |
| 2 | `test_end_of_month_february_leap_and_december_january_crossover` | **TC-12**: Tanggal mulai 31 Januari; periode Februari kabisat (29 hari), Maret, pergantian tahun Des-Jan. |
| 3 | `test_placement_started_and_ended_in_same_month_produces_single_period` | **TC-12**: Mulai dan selesai di bulan yang sama menghasilkan tepat 1 periode tarif penuh. |
| 4 | `test_month_of_departure_billed_full_and_subsequent_months_are_not_created` | **TC-12**: Selesai tgl 15 Mei $\rightarrow$ Mei ditagih penuh, Juni dan seterusnya tidak diterbitkan. |
| 5 | `test_placement_starting_after_reference_date_in_same_month_returns_empty_array` | **Kontrak Tanggal**: Mulai 10 Sept, acuan 5 Sept $\rightarrow$ mengembalikan array kosong `[]`. |
| 6 | `test_future_months_beyond_as_of_date_are_never_created_and_cannot_be_bypassed` | **Kontrak Tanggal**: Invoice masa depan tidak dapat diterbitkan; parameter masa depan ditolak jika melampaui server now. |
| 7 | `test_max_period_month_narrows_range_and_handles_boundaries` | **Kontrak Tanggal**: `maxPeriodMonth` sebelum mulai $\rightarrow$ `[]`; melampaui batas akhir $\rightarrow$ di-clamp aman. |
| 8 | `test_calculate_due_date_and_payload_reject_out_of_range_or_invalid_periods` | **Validasi Langsung**: Uji langsung pada `calculateDueDate` dan `calculateInvoicePayload`. Menolak periode sebelum mulai, setelah selesai, masa depan setelah bulan berjalan, penempatan belum mulai per tanggal acuan, format ambigu, serta memastikan payload valid siap terbit. |
| 9 | `test_room_rate_change_does_not_affect_existing_placement_agreed_rate` | **TC-33**: Perubahan tarif fisik kamar tidak mengubah nominal invoice dari `agreed_monthly_rate`. |
| 10 | `test_changing_resident_name_or_room_number_preserves_existing_invoice_snapshots` | **TC-33**: Perubahan nama penghuni/kamar di kemudian hari tidak mengubah snapshot invoice lama. |
| 11 | `test_repeated_sync_is_idempotent_and_does_not_duplicate_invoices_or_audits` | **TC-13 / TC-33**: Sinkronisasi berulang kali tidak menghasilkan duplikasi invoice atau audit log. |
| 12 | `test_missing_invoice_in_middle_of_range_is_detected_and_synced` | **TC-13**: Invoice bulan Oktober yang hilang di antara September dan November terdeteksi dan diterbitkan. |
| 13 | `test_paid_invoice_remains_intact_and_unaltered_during_sync` | **TC-13**: Invoice yang sudah lunas (`Payment` valid) tidak berubah atau ditimpa saat sinkronisasi. |
| 14 | `test_preview_and_coverage_methods_are_read_only_and_do_not_alter_database` | **Read-Only Safety**: Memverifikasi preview/coverage tidak melakukan `insert`, `update`, `delete`, atau audit. |
| 15 | `test_non_admin_or_inactive_actor_is_rejected_on_sync_operations` | **Otorisasi Server-Level**: Menolak actor pemilik, penghuni, admin nonaktif, actor belum tersimpan (unsaved), objek in-memory usang yang dinonaktifkan di DB, serta objek in-memory usang yang diubah role-nya di DB. |
| 16 | `test_audit_failure_on_second_invoice_rolls_back_entire_placement_changes` | **Rollback Atomik**: Kegagalan audit pada invoice ke-2 membatalkan seluruh invoice pada placement tersebut. |
| 17 | `test_batch_sync_isolates_partial_failure_and_allows_safe_retry` | **Isolasi Batch**: Satu placement gagal tidak menggugurkan placement lain; retry berhasil setelah perbaikan. |
| 18 | `test_parent_transaction_rollback_reverts_synced_invoices_and_audits` | **Rollback Induk**: `syncPlacementInvoices` melempar exception sehingga transaksi induk T10/T11 dapat rollback utuh. |
| 19 | `test_database_unique_constraint_enforces_idempotency_and_concurrency_boundaries` | **Constraint Integrity**: Memverifikasi UNIQUE composite `(placement_id, period_month)` sebagai batas pengaman konkurensi. |

---

## 3. Hasil Verifikasi & Eksekusi Uji Aktual

### A. Eksekusi Feature Test BillingService
```
Command: php vendor/bin/phpunit --testdox tests/Feature/BillingServiceTest.php

Runtime:       PHP 8.3.32
Configuration: C:\SEMESTER 5\SISTEM INFORMASI PRAKTIKUM\TA\phpunit.xml

Time: 00:01.540, Memory: 52.00 MB

Billing Service (Tests\Feature\BillingService)
 ✔ Due date and full amount for started on 1st 5th and 10th
 ✔ End of month february leap and december january crossover
 ✔ Placement started and ended in same month produces single period
 ✔ Month of departure is billed full and subsequent months are not created
 ✔ Placement starting after reference date in same month returns empty array
 ✔ Future months beyond as of date are never created and cannot be bypassed
 ✔ Max period month narrows range and handles boundaries
 ✔ Calculate due date and payload reject out of range or invalid periods
 ✔ Room rate change does not affect existing placement agreed rate
 ✔ Changing resident name or room number preserves existing invoice snapshots
 ✔ Repeated sync is idempotent and does not duplicate invoices or audits
 ✔ Missing invoice in middle of range is detected and synced
 ✔ Paid invoice remains intact and unaltered during sync
 ✔ Preview and coverage methods are read only and do not alter database
 ✔ Non admin or inactive actor is rejected on sync operations
 ✔ Audit failure on second invoice rolls back entire placement changes
 ✔ Batch sync isolates partial failure and allows safe retry
 ✔ Parent transaction rollback reverts synced invoices and audits
 ✔ Database unique constraint enforces idempotency and concurrency boundaries

OK (19 tests, 150 assertions)
```

### B. Eksekusi Rangkaian Regresi Penuh (`php artisan test`)
```
Command: php artisan test

PASS  Tests\Unit\ExampleTest (1 test)
PASS  Tests\Feature\ExampleTest (1 test)
PASS  Tests\Feature\AuditServiceTest (10 tests)
PASS  Tests\Feature\DatabaseConstraintTest (18 tests)
PASS  Tests\Feature\AuthTest (24 tests)
PASS  Tests\Feature\LayoutNavigationTest (6 tests)
PASS  Tests\Feature\RoomTest (30 tests)
PASS  Tests\Feature\ResidentTest (37 tests)
PASS  Tests\Feature\FacilityTest (40 tests)
PASS  Tests\Feature\BillingServiceTest (19 tests)

Tests:    186 passed (1093 assertions)
Duration: 11.21s
Status:   PASSED (100% lulus, 0 failure, 0 error)
```

*Rincian aktual jumlah tes per kelas terverifikasi:*
- `AuditServiceTest`: 10 tests
- `DatabaseConstraintTest`: 18 tests
- `AuthTest`: 24 tests
- `LayoutNavigationTest`: 6 tests
- `RoomTest`: 30 tests
- `ResidentTest`: 37 tests
- `FacilityTest`: 40 tests
- `BillingServiceTest`: 19 tests
- `ExampleTest` (Feature): 1 test
- `ExampleTest` (Unit): 1 test
- **Total: 186 tests, 1093 assertions**

---

## 4. Analisis Batas Bukti Konkurensi & Transaksi Batch

1. **Batas Bukti Konkurensi Pengujian Otomatis**:
   - Rangkaian pengujian PHPUnit dieksekusi secara sekuensial dalam satu proses PHP (*single-process sequential execution*) dengan satu koneksi MySQL.
   - Pengujian ini **tidak membuktikan konkurensi multi-koneksi paralel nyata** (*true parallel execution*).
   - Namun, jaminan di tingkat produksi ditegakkan oleh:
     - **Pessimistic Row Locking (`lockForUpdate`)**: Mengantrekan proses yang mengakses record `Placement` dan `Invoice` yang sama.
     - **MySQL Unique Constraint (`invoices_placement_id_period_month_unique`)**: Mencegah duplikasi pada level storage engine dengan kode error MySQL `1062` jika terjadi *race condition*.

2. **Batas Transaksi Batch vs Transaksi Induk**:
   - `syncPlacementInvoices` sengaja melempar exception agar transaksi induk di modul pemanggil (seperti pembukaan penempatan baru di T10 atau penghentian penempatan di T11) dapat membatalkan seluruh operasi secara atomik.
   - `syncAllPlacements` mengisolasi error per penempatan. Isolasi commit mandiri per penempatan **hanya berlaku saat batch dipanggil tanpa transaksi induk luar**. Apabila batch dipanggil di dalam blok `DB::transaction()`, maka rollback luar akan membatalkan seluruh perubahan batch.
   - Pengujian unit/feature dengan trait `DatabaseTransactions` menguji penanganan error parsial pada logika aplikasi, bukan pembuktian commit mandiri antar-koneksi nyata.

---

## 5. Kesimpulan & Kesiapan Task Berikutnya

- Seluruh koreksi penutupan T09 telah diimplementasikan dan diverifikasi:
  1. `calculateInvoicePayload` memiliki proteksi langsung terhadap penempatan belum mulai, periode masa depan, dan periode di luar kontrak.
  2. `ensureActorIsAdmin` memverifikasi actor tersimpan dan membaca ulang status aktif/peran dari database, kebal terhadap objek in-memory usang.
  3. Batas transaksi batch telah ditegaskan secara transparan.
  4. Rincian jumlah tes per kelas telah diselaraskan dengan hasil eksekusi aktual.
- Database aplikasi `sim_kos` tetap utuh (7 user demo tidak termutasi).
- Siap untuk melanjutkan ke **T10: Mulai Penempatan** (*Check-in / New Placement*).
