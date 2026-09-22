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
| **TC-05** | Akun nonaktif pasca-login, penegakan ganti password sementara, validasi password, atomisitas transaksi ganti password, asimetri audit login/logout, serta pencabutan sesi independen via penanda versi sesi khusus `session_version` dan session signature hash `auth_session_hash_{user_id}` (kompatibel `SESSION_DRIVER=file`). | 1. Sesi pengguna aktif langsung dicabut pada request berikutnya setelah status akun diubah menjadi nonaktif di database.<br>2. Pengguna dengan `must_change_password = true` dipaksa ke rute `/password/change` dan dikecualikan dari loop redirect; rute logout tetap dapat diakses.<br>3. Ganti password memvalidasi password lama, panjang minimal 12 karakter, dan konfirmasi cocok.<br>4. Perubahan password, reset flag, dan audit log dibungkus dalam `DB::transaction` yang sama; jika audit gagal, perubahan password dan flag ikut rollback penuh.<br>5. Session diregenerasi setelah ganti password berhasil.<br>6. Penanganan asimetris kegagalan audit: jika audit login gagal, pengguna tetap guest, session dibersihkan, dan CSRF token diregenerasi; jika audit logout gagal, sesi pengguna tetap dipastikan berakhir melalui blok `finally`.<br>7. Sesi lama dari dua sesi independen langsung dicabut saat penonaktifan akun, pengaktifan kembali, atau reset password sementara melalui validasi signature hash sesi tanpa bergantung pada scanning filesystem.<br>8. Sesi tanpa signature ditolak, remember-me sah tetap diverifikasi guard, dan penolakan sesi usang hanya mengakhiri sesi perangkat tersebut via `logoutCurrentDevice()` tanpa menggugurkan sesi baru sah di perangkat lain. | 21 September 2026 | **LULUS** | `tests/Feature/AuthTest.php` & `tests/Feature/ResidentTest.php` |
| **TC-06** | Master Kamar: CRUD kamar lengkap, validasi nomor kamar unik lintas status aktif & arsip, validasi tarif integer positif (Rp 1 - 999.999.999), pemisahan status hunian dinamis (terisi/kosong dari penempatan aktif) dari status arsip, otorisasi server-side Gate per endpoint, proteksi sanitasi create/update terhadap manipulasi status/arsip, atomisitas mutasi & audit rollback, imutabilitas tarif terhadap penempatan berjalan dan tagihan terbit, relasi detail kamar berurutan tanggal descending, validasi parameter filter (penolakan array tanpa 500), serta eliminasi query N+1 pada list kamar. | 1. Admin dapat mengelola kamar (create, read, update, delete, archive, unarchive) dengan audit log lengkap.<br>2. Pemilik memiliki hak baca-saja (*read-only*) dan dilarang mengakses mutasi (HTTP 403).<br>3. Penghuni dilarang penuh mengakses master kamar (HTTP 403).<br>4. Role tanpa izin ditolak HTTP 403 bahkan saat mengirim payload tidak valid via form request authorize.<br>5. Nomor kamar unik lintas kamar aktif dan diarsipkan.<br>6. Status hunian dinamis terhitung dari penempatan aktif dan tidak dapat dimanipulasi melalui payload input.<br>7. Pengubahan tarif bulanan kamar terbukti TIDAK mengubah `agreed_monthly_rate` penempatan aktif maupun `amount` faktur yang telah diterbitkan.<br>8. Seluruh 5 mutasi kamar (create, update, delete, archive, unarchive) terbukti rollback penuh jika pencatatan audit gagal.<br>9. Detail kamar menampilkan relasi aktif, fasilitas, dan riwayat penempatan terurut tanggal sewa descending.<br>10. Input array pada search/tab/occupancy/per_page ditolak secara terkendali via validasi tanpa memicu error 500.<br>11. Kueri daftar kamar tidak memicu N+1 untuk pengecekan referensi historis. | 21 September 2026 | **LULUS** | `php vendor/bin/phpunit --testdox tests/Feature/RoomTest.php` (30 tests, 185 assertions) & 11 screenshots di `docs/evidence/t06/` |
| **TC-07** | Master Penghuni & Akun: CRUD penghuni lengkap, pembuatan akun login ber-role resident secara atomik dalam satu transaksi, penyerahan password sementara kriptografis aman 12 karakter dengan proteksi `Cache-Control: no-store` dan masking tampilan, validasi identitas (email unik lintas aktif/nonaktif, nomor telepon mengandung digit), otorisasi server-side `Gate::authorize()` (Admin kelola, Pemilik baca, Penghuni 403 termasuk detail diri sendiri), aksi idempoten aktivasi/deaktivasi akun, kebijakan hapus fisik (hanya jika tanpa referensi histori) dan penonaktifan akun terkait, pengarsipan profil (ditolak jika penempatan aktif), pembukaan arsip aman, serta kueri efisien bebas N+1. | 1. Admin dapat mengelola seluruh operasi master penghuni dan akun.<br>2. Pemilik memiliki hak baca-saja (*read-only*) dan dilarang mengakses mutasi (HTTP 403).<br>3. Penghuni dilarang penuh mengakses master penghuni termasuk detail dirinya sendiri melalui `/residents/{resident}` (HTTP 403).<br>4. Pembuatan akun dan profil terbukti atomik dalam satu transaksi; jika profil gagal atau audit kedua gagal, seluruh mutasi di-rollback penuh tanpa akun yatim.<br>5. Password sementara 12 karakter alfanumerik dihasilkan secara kriptografis aman (`Str::password(12)`), diserahkan via session flash sekali pakai, tidak dicatat ke log/audit, dan direspon dengan `Cache-Control: no-store`.<br>6. Email unik divalidasi ketat mencakup akun aktif maupun nonaktif.<br>7. Validasi nomor telepon mewajibkan adanya digit (menolak input hanya simbol/spasi).<br>8. Hapus fisik diizinkan hanya jika tanpa riwayat penempatan atau keluhan; profil dihapus fisik dan akun pengguna dinonaktifkan demi integritas audit.<br>9. Penghuni dengan penempatan aktif dilarang keras diarsipkan.<br>10. Aktivasi akun dilarang jika profil berstatus diarsipkan (wajib buka arsip terlebih dahulu).<br>11. Input filter array ditangani aman tanpa error 500, dan kueri tabel bebas N+1. | 21 September 2026 | **LULUS** | `php vendor/bin/phpunit --testdox tests/Feature/ResidentTest.php` (37 tests, 185 assertions) & 9 screenshots di `docs/evidence/t07/` |
| **TC-08** | Master Fasilitas: CRUD fasilitas lengkap, penempatan kamar/area bersama, validasi kode unik case-insensitive (termasuk fasilitas diarsipkan), validasi kondisi (baik/rusak/diperbaiki), integritas CHECK constraints database (`chk_facilities_location_rule`), penolakan kamar arsip untuk fasilitas baru/pindah, toleransi pembaruan non-lokasi pada kamar arsip eksisting, penguncian lokasi fasilitas berhistori keluhan, otorisasi server-side Gate per endpoint, proteksi sanitasi create/update terhadap manipulasi status arsip, atomisitas mutasi & audit rollback (create/update/delete/archive/unarchive), penanganan filter query string aman anti-500, dan eliminasi kueri N+1 pada list fasilitas. | 1. Admin dapat mengelola fasilitas (create, read, update, delete, archive, unarchive) dengan audit log lengkap.<br>2. Pemilik memiliki hak baca-saja (*read-only*) dan dilarang mengakses mutasi (HTTP 403).<br>3. Penghuni dilarang penuh mengakses master fasilitas (HTTP 403).<br>4. Role tanpa izin ditolak HTTP 403 bahkan saat mengirim payload tidak valid.<br>5. Kode fasilitas wajib unik case-insensitive (`LOWER(code)`) termasuk fasilitas diarsipkan.<br>6. Fasilitas tipe kamar wajib memiliki `room_id` valid dan `area_name = null`; fasilitas tipe bersama wajib memiliki `area_name` valid dan `room_id = null`.<br>7. Kamar arsip dilarang dipilih untuk fasilitas baru maupun tujuan perpindahan fasilitas eksisting.<br>8. Fasilitas eksisting pada kamar arsip dapat memperbarui nama/kondisi/catatan tanpa dipaksa pindah kamar.<br>9. Fasilitas yang memiliki riwayat keluhan dikunci lokasinya secara permanen; percobaan perpindahan ditolak oleh validasi server.<br>10. Penempatan fasilitas ke kamar terbukti tidak mengubah status hunian kamar.<br>11. Seluruh 5 mutasi fasilitas (create, update, delete, archive, unarchive) terbukti rollback penuh jika audit log gagal.<br>12. Tindakan arsip dan buka arsip berulang bersifat idempoten tanpa audit ganda.<br>13. Input query string array ditangani aman tanpa error 500.<br>14. Kueri daftar fasilitas bebas N+1 queries. | 21 September 2026 | **LULUS** | `php vendor/bin/phpunit --testdox tests/Feature/FacilityTest.php` (40 tests, 277 assertions) & 10 screenshots di `docs/evidence/t08/` |
| **TC-09** | Integritas referensi foreign key RESTRICT dan proteksi histori kamar serta fasilitas: Penghapusan fisik kamar ditolak jika memiliki riwayat penempatan (aktif/selesai) atau relasi fasilitas (aktif/arsip); penghapusan fisik fasilitas ditolak jika memiliki riwayat keluhan; kamar/fasilitas berhistori diarahkan menggunakan arsip; kamar dengan penempatan aktif atau fasilitas dengan keluhan terbuka/sedang diproses dilarang diarsipkan. | 1. Kamar tanpa relasi apa pun dapat dihapus fisik secara permanen.<br>2. Kamar dengan histori penempatan yang sudah selesai ditolak penghapusannya dan data tetap utuh.<br>3. Kamar dengan fasilitas terkait (termasuk fasilitas berstatus arsip) ditolak penghapusannya dan data tetap utuh.<br>4. Fasilitas tanpa referensi keluhan dapat dihapus fisik secara permanen.<br>5. Fasilitas dengan riwayat keluhan (termasuk keluhan selesai) dilarang keras dihapus fisik demi integritas FK RESTRICT.<br>6. Fasilitas dengan keluhan berstatus aktif (`open` atau `in_progress`) dilarang diarsipkan.<br>7. Fasilitas dengan keluhan berstatus selesai (`resolved` atau `closed_without_action`) berhasil diarsipkan.<br>8. Kamar dengan penempatan aktif dilarang keras diarsipkan demi integritas kontrak sewa berjalan. | 21 September 2026 | **LULUS** | `tests/Feature/RoomTest.php`, `tests/Feature/FacilityTest.php`, & `DatabaseConstraintTest.php` |
| **TC-10** | Mulai Penempatan (Check-in / New Placement): Pendaftaran penempatan kamar baru oleh Administrator dengan mekanisme preview sesi terverifikasi server (`preview_token`), post-lock drift verification pada tarif dan tanggal bisnis, penentuan satu tanggal bisnis konsisten `Asia/Jakarta`, penerbitan otomatis invoice pertama via `BillingService::syncPlacementInvoices`, pencatatan audit log ganda (`placements` dan `invoices`) secara atomik dalam satu transaksi, penolakan kamar/penghuni arsip atau tidak aktif, sanitasi payload manipulatif, pembatalan request preview lama via AbortController/sequence tracking, dan eliminasi query N+1 pada detail penempatan (eager loading `validPayment`). | 1. Admin memulai penempatan valid dengan token preview terverifikasi server: `Placement` tersimpan aktif (`ended_on = null`), `agreed_monthly_rate` disalin otoritatif dari `rooms.monthly_rate`.<br>2. Invoice periode pertama terbit otomatis dengan `period_month = YYYY-MM-01`, nominal penuh, dan snapshot data penghuni serta kamar.<br>3. Dua entri audit log (`placements` dan `invoices`) tercatat lengkap dalam transaksi yang sama.<br>4. Kamar otomatis berubah berstatus terisi (`is_occupied = true`).<br>5. Submit tanpa token preview sah, token kedaluwarsa (>15 menit), dibuat admin lain, atau beda pasangan kamar/penghuni ditolak tanpa mutasi.<br>6. Perubahan tarif kamar atau pergeseran tanggal kalender (melewati tengah malam) pasca-preview ditolak di bawah lock dengan prompt preview ulang.<br>7. Endpoint preview terbukti murni *read-only* (0 placement, 0 invoice, 0 audit) dan mengembalikan JSON 422 terkendali bila tidak memenuhi syarat.<br>8. Penggantian pilihan kamar/penghuni saat preview sedang memuat membatalkan fetch asinkron via AbortController dan menganulir response sequence lama; modal preview tidak terbuka untuk data basi.<br>9. Kegagalan penerbitan invoice maupun audit membatalkan seluruh transaksi secara atomik.<br>10. Detail penempatan memuat status pembayaran invoice bebas N+1 queries. | 21 September 2026 | **LULUS** | `php vendor/bin/phpunit --testdox tests/Feature/PlacementTest.php` (18 tests, 153 assertions) & 7 screenshots di `docs/evidence/t10/` |
| **TC-11** | Pencegahan Penempatan Ganda, Double Submit, & Rollback Atomik Menyeluruh: Penolakan penempatan pada kamar yang sudah terisi penempatan aktif, penolakan penempatan bagi penghuni yang sudah memiliki penempatan aktif lain, penolakan submit ganda (*double submit*), integritas unique index `active_room_id` dan `active_resident_id`, serta pengujian rollback atomik terpisah untuk kegagalan BillingService, kegagalan audit invoice, dan kegagalan audit placement tahap akhir dengan BillingService nyata. | 1. Kamar yang sedang memiliki penempatan aktif ditolak dengan pesan bisnis yang jelas ("Kamar sedang terisi oleh penempatan aktif").<br>2. Penghuni yang sedang memiliki penempatan aktif ditolak ("Penghuni sedang memiliki penempatan aktif").<br>3. Submit ulang request yang sama setelah penempatan pertama berhasil ditolak secara terkendali tanpa menghasilkan placement duplikat atau orphan invoice.<br>4. Database unique constraint `active_room_id` dan `active_resident_id` menggagalkan duplikasi aktif dan hanya error MySQL 1062 terkait yang diterjemahkan menjadi pesan bisnis.<br>5. Skenario rollback BillingService: kegagalan sync invoice membatalkan placement, invoice, dan audit secara bersih.<br>6. Skenario rollback audit invoice: kegagalan audit log invoice membatalkan seluruh mutasi.<br>7. Skenario rollback tahap akhir: dengan BillingService nyata, invoice pertama dan audit invoice terbukti tersimpan dalam transaksi; saat audit placement tahap akhir gagal, seluruh mutasi di-rollback penuh ke baseline tanpa meninggalkan data parsial dan kamar/penghuni tetap bebas penempatan aktif. | 21 September 2026 | **LULUS** | `tests/Feature/PlacementTest.php` (tests spesifik TC-11) |
| **TC-12** | Logika Jatuh Tempo & Perhitungan Tarif Penuh Tagihan Pertama: Penempatan mulai tanggal 1, 5, dan 10; invoice periode pertama jatuh tempo tgl 10 jika mulai setelah tgl 5, dan tgl 5 jika mulai tgl 1 s/d 5. Tarif bulanan penuh tanpa prorata harian untuk bulan pertama dan bulan keluar. Sinkronisasi berulang terbukti idempoten tanpa duplikasi invoice atau audit. | 1. Penempatan mulai tanggal 1 dan 5 menghasilkan jatuh tempo tanggal 5 bulan berjalan (`YYYY-MM-05`).<br>2. Penempatan mulai tanggal 10 menghasilkan jatuh tempo tepat pada tanggal mulai (`YYYY-MM-10`).<br>3. Seluruh periode awal dan akhir sewa dikenai nominal penuh dari `agreed_monthly_rate` tanpa prorata harian, diskon, atau deposit.<br>4. Penempatan yang selesai di pertengahan bulan (misal: 15 Mei) tetap ditagih penuh untuk bulan Mei, sedangkan bulan setelahnya (Juni dst.) tidak diterbitkan.<br>5. Sinkronisasi berulang pada penempatan yang sama terbukti idempoten dan tidak menggandakan baris invoice atau entri audit log. | 21 September 2026 | **LULUS** | `php vendor/bin/phpunit --testdox tests/Feature/BillingServiceTest.php` (tests spesifik TC-12) |
| **TC-13** | Fondasi Deteksi Celah Invoice & Sinkronisasi Tagihan Bulanan: Deteksi invoice yang hilang di tengah masa sewa penempatan aktif maupun penempatan yang sudah selesai, perhitungan cakupan tagihan (*coverage check*), operasi baca-saja (*preview* & *coverage*) bebas efek samping, serta pelengkapan otomatis periode hilang via sinkronisasi transaksional atomik. | 1. Celah invoice pada periode di tengah rentang sewa (contoh: Oktober hilang di antara September dan November) terdeteksi secara otomatis.<br>2. Metode `checkCoverage` dan `checkGlobalCoverage` menghitung secara akurat jumlah tagihan wajib, eksisting dalam cakupan, dan daftar periode yang belum dibuat.<br>3. Operasi preview dan coverage terbukti murni *read-only* (0 insert, 0 update, 0 delete, 0 audit).<br>4. Sinkronisasi batch maupun single-placement melengkapi celah tagihan yang hilang secara atomik dan terintegrasi dengan audit trail.<br>5. Invoice yang telah berstatus lunas (`Payment` valid) tetap utuh dan tidak termutasi saat proses sinkronisasi berjalan. | 21 September 2026 | **LULUS (Fondasi Service)** | `php vendor/bin/phpunit --testdox tests/Feature/BillingServiceTest.php` (tests spesifik TC-13) |
| **TC-30** | Integritas Data Akun & Transaksional: Sinkronisasi pembaruan nama pengguna dan profil penghuni tanpa mengubah snapshot nama historis pada `invoices.resident_name_snapshot`, penolakan mutasi role via payload (`role_id`, `role`, `password`, dll), penolakan manipulasi akun Admin/Pemilik melalui `ResidentService`, serta atomisitas transaksi rollback penuh bila pembuatan profil atau pencatatan audit kedua gagal. | 1. Pembaruan nama pada profil penghuni menyinkronkan nama akun `users.name`, namun terbukti TIDAK mengubah `resident_name_snapshot` pada faktur historis.<br>2. Injeksi field sensitif (`role_id`, `role`, `is_active`, `archived_at`, `password`, `must_change_password`) melalui request biasa ditolak/dibersihkan secara otomatis.<br>3. Percobaan manipulasi akun non-resident (Admin/Pemilik) melalui service penghuni ditolak dengan `InvalidArgumentException`.<br>4. Seluruh mutasi dibungkus dalam `DB::transaction()` dengan penguncian baris `lockForUpdate` konsisten (Resident lalu User).<br>5. Seluruh 8 skenario mutasi bisnis (create, update, delete, archive, unarchive, activate, deactivate, reset-password) terbukti rollback penuh jika audit log gagal. | 21 September 2026 | **LULUS** | `tests/Feature/ResidentTest.php` (tests spesifik TC-30) |
| **TC-33** | Imutabilitas Snapshot Tarif & Kontrak Sewa: Perubahan tarif bulanan kamar fisik di kemudian hari tidak mengubah `agreed_monthly_rate` penempatan aktif maupun nominal invoice lama/baru dalam kontrak tersebut; kontrak penempatan baru menggunakan tarif kamar fisik yang berlaku saat kontrak dibuat; pengujian deterministik batas akhir bulan, tahun kabisat Februari, dan pergantian tahun. | 1. Pembaruan harga kamar fisik pada tabel `rooms` terbukti TIDAK mengubah nominal invoice yang diterbitkan untuk kontrak penempatan berjalan (nominal konsisten mengikuti `agreed_monthly_rate`).<br>2. Pembaruan nama penghuni atau nomor kamar di master data tidak mengubah nilai historis pada snapshot `resident_name_snapshot` dan `room_number_snapshot`.<br>3. Perhitungan periode sewa menangani batas hari akhir bulan (31 Januari $\rightarrow$ Februari), tahun kabisat (29 Februari 2024/2028), tahun biasa (28 Februari), serta pergantian tahun kalender (Desember $\rightarrow$ Januari) secara deterministik dan presisi.<br>4. Integritas basis data diperkuat oleh constraint `UNIQUE(placement_id, period_month)` yang mencegah inkonsistensi data pada batas konkurensi. | 21 September 2026 | **LULUS** | `php vendor/bin/phpunit --testdox tests/Feature/BillingServiceTest.php` (tests spesifik TC-33) |

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

   PASS  Tests\Feature\FacilityTest
  ✓ admin can access all facility endpoints
  ✓ owner can access facility index and show only without mutation actions
  ✓ owner gets 403 on facility mutation endpoints
  ✓ resident gets 403 on all master facility endpoints
  ✓ guest is redirected to login for facility endpoints
  ✓ non admin submitting invalid payload gets 403 before validation
  ✓ facility code is required and unique including archived facilities
  ✓ facility code is unique case insensitively including archived facilities
  ✓ facility name and condition validation
  ✓ room facility requires valid room id and null area name
  ✓ shared facility requires area name and null room id
  ✓ archived at cannot be injected via create or update payload
  ✓ query filters handle array inputs gracefully without 500 error
  ✓ cannot assign facility to archived room on create
  ✓ cannot relocate facility to archived room on update
  ✓ facility in archived room can update non location info without relocating
  ✓ facility with complaints cannot change location type or location details
  ✓ facility with complaints can update non location fields
  ✓ facility without complaints can relocate freely
  ✓ facility placement does not alter room occupancy status
  ✓ facility without complaints can be physically deleted
  ✓ facility with complaint history cannot be deleted
  ✓ facility with open or in progress complaints cannot be archived
  ✓ facility with resolved or closed complaints can be archived
  ✓ facility can be unarchived
  ✓ repeated archive and unarchive actions are idempotent without duplicate audits
  ✓ rejected mutations do not persist data or produce success audit logs
  ✓ audit failure rolls back facility creation
  ✓ audit failure rolls back facility update
  ✓ audit failure rolls back facility deletion
  ✓ audit failure rolls back facility archive
  ✓ audit failure rolls back facility unarchive
  ✓ facility search by code and name with grouped conditions
  ✓ facility filters by condition and location type
  ✓ facility pagination preserves query parameters
  ✓ facility show displays complaint history with submitter and placement
  ✓ index rendering does not trigger n plus one queries
  ✓ create facility rejects array inputs and prevents 500 without audit or data mutation
  ✓ update facility without complaints rejects array inputs without audit or data mutation
  ✓ update facility with complaints rejects array inputs and does not convert to canonical

   PASS  Tests\Feature\BillingServiceTest
  ✓ due date and full amount for started on 1st 5th and 10th
  ✓ end of month february leap and december january crossover
  ✓ placement started and ended in same month produces single period
  ✓ month of departure is billed full and subsequent months are not created
  ✓ placement starting after reference date in same month returns empty array
  ✓ future months beyond as of date are never created and cannot be bypassed
  ✓ max period month narrows range and handles boundaries
  ✓ calculate due date and payload reject out of range or invalid periods
  ✓ room rate change does not affect existing placement agreed rate
  ✓ changing resident name or room number preserves existing invoice snapshots
  ✓ repeated sync is idempotent and does not duplicate invoices or audits
  ✓ missing invoice in middle of range is detected and synced
  ✓ paid invoice remains intact and unaltered during sync
  ✓ preview and coverage methods are read only and do not alter database
  ✓ non admin or inactive actor is rejected on sync operations
  ✓ audit failure on second invoice rolls back entire placement changes
  ✓ batch sync isolates partial failure and allows safe retry
  ✓ parent transaction rollback reverts synced invoices and audits
  ✓ database unique constraint enforces idempotency and concurrency boundaries

  Tests:    186 passed (1093 assertions)
  Duration: 11.21s
```

---

## Log Rinci Eksekusi TC-12, TC-13, & TC-33 (`BillingServiceTest`)

```text
PHPUnit 12.5.35 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.3.32
Configuration: C:\SEMESTER 5\SISTEM INFORMASI PRAKTIKUM\TA\phpunit.xml

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

---

## Log Rinci Eksekusi TC-10 & TC-11 (`PlacementTest`)

```text
PHPUnit 12.5.35 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.3.32
Configuration: C:\SEMESTER 5\SISTEM INFORMASI PRAKTIKUM\TA\phpunit.xml

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


