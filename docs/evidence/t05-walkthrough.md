# Walkthrough Bukti Implementasi & Verifikasi T05: Layout Blade, Navigasi Role, & Komponen Bersama

Dokumen ini mencatat bukti verifikasi lengkap untuk Task **T05** (Layout Blade, Bootstrap 5 lokal, Navigasi per Role, dan Komponen Antarmuka Bersama) pada Sistem Informasi Manajemen Kos (SIM Kos).

---

## 1. Perubahan dan File Utama

### A. Dependensi & Konfigurasi Build Aset Lokal
- **`package.json` & `package-lock.json`**:
  - Menghapus dependensi Tailwind CSS bawaan (`@tailwindcss/vite`, `tailwindcss`).
  - Memasang `bootstrap@^5.3.8` dan `@popperjs/core@^2.11.8`.
- **`vite.config.js`**:
  - Dikonfigurasi bersih hanya memuat `laravel-vite-plugin` untuk `resources/css/app.css` dan `resources/js/app.js`.
  - Tidak ada plugin Tailwind atau Google/Bunny fonts eksternal.
- **`resources/css/app.css` & `resources/css/print.css`**:
  - Seluruh `@import` (`bootstrap/dist/css/bootstrap.min.css` dan `./print.css`) ditempatkan sebelum aturan CSS biasa.
  - Mengatur token tema SIM Kos: sidebar navy (`#0f172a`), latar belakang bersih (`#f8fafc`), aksen teal (`#0d9488`), ring fokus keyboard yang tegas (`a:focus-visible`, `button:focus-visible`, `input:focus-visible`).
  - Menyesuaikan aturan cetak (`print.css`): menyembunyikan navigasi dan sidebar saat pencetakan, memaksimalkan area konten.
- **`resources/js/app.js`**:
  - Memuat Bootstrap bundle JavaScript satu kali (`import 'bootstrap/dist/js/bootstrap.bundle.min.js'`).
- **Verifikasi Ketersediaan Offline**:
  - Build produksi lokal tersimpan di `public/build/` (`manifest.json`, file CSS dan JS).
  - Berkas `public/hot` dipastikan **tidak ada** sehingga aplikasi 100% menggunakan aset lokal tanpa bergantung pada Vite dev server.

### B. Komponen Antarmuka Bersama (`resources/views/components/`)
1. **`x-input` (`components/input.blade.php`)**:
   - Menghubungkan label, helper, dan pesan error menggunakan atribut `id` dan `aria-describedby`.
   - Mengaktifkan `aria-invalid="true"` saat terjadi kegagalan validasi.
   - Kolom kata sandi (`password`, `current_password`, `password_confirmation`, `new_password`) secara ketat **tidak diberi nilai** (`value=""`) dan tidak pernah diisi dari `old()`.
   - Seluruh teks dan atribut di-escape secara aman melalui Blade `{{ ... }}`.
2. **`x-alert` (`components/alert.blade.php`)**:
   - Menampilkan notifikasi flash session (`status`, `success`, `error`, `warning`, `info`) dengan styling Bootstrap dan role `alert`.
3. **`x-badge` (`components/badge.blade.php`)**:
   - Badge visual untuk role (`admin`, `owner`, `resident`) dengan prefix teks yang ramah screen-reader.
4. **`x-button` (`components/button.blade.php`)**:
   - Tombol konsisten untuk aksi form dan link dengan varian teal (`btn-teal`, `btn-outline-teal`).
5. **`x-empty-state` (`components/empty-state.blade.php`)**:
   - Placeholder informatif yang netral ("Informasi Belum Tersedia"). Tidak membuat klaim kosong palsu ("belum ada data/tagihan") tanpa pembuktian query database sebelum modulnya diimplementasikan.
6. **`x-table-card` (`components/table-card.blade.php`)**:
   - Wadah tabel responsif dengan header kartu yang rapi.

### C. Layout & Partisi Bersama (`resources/views/layouts/`)
1. **`layouts/app.blade.php`**: Layout utama autentikasi dengan container responsif, header, sidebar, dan footer.
2. **`layouts/guest.blade.php`**: Layout tamu untuk halaman login.
3. **`layouts/partials/sidebar.blade.php`**: Sidebar desktop (tampil pada `>= 992px`) dan drawer offcanvas mobile (`#simkosOffcanvasSidebar` pada `< 992px`).
4. **`layouts/partials/sidebar-nav.blade.php`**:
   - Navigasi berdasarkan hak akses: **Admin**, **Pemilik**, dan **Penghuni**.
   - Fitur mendatang ditampilkan sebagai teks non-interaktif berlabel **"Belum tersedia"**, tanpa tautan dummy (`href="#"`), tanpa rute dummy, dan tanpa badge fase pengembangan.
   - **Mode Navigasi Terbatas**: Khusus pengguna dengan `must_change_password = true`, menu dibatasi hanya menampilkan peringatan kata sandi sementara, tautan **Ganti Password**, dan tombol **Logout** agar tidak terjadi loop redirect.
5. **`layouts/partials/header.blade.php`**:
   - Header sticky dengan tombol hamburger mobile, judul halaman, identitas pengguna, badge role, dan dropdown akun (ganti password dan logout POST).
   - Tidak menampilkan link profil palsu/dummy karena rute profil pengguna belum ada.

### D. Tampilan yang Diperbarui
- **`resources/views/auth/login.blade.php`**: Menggunakan `layouts.guest`, `x-input`, dan `x-button`.
- **`resources/views/auth/change-password.blade.php`**: Menggunakan `layouts.app`, form pemisahan tombol logout HTML5 valid, dan `x-input`.
- **`resources/views/admin/dashboard.blade.php`**: Menggunakan `layouts.app`, kartu identitas Admin, dan empty state ringkasan operasional netral.
- **`resources/views/owner/dashboard.blade.php`**: Menggunakan `layouts.app`, kartu identitas Pemilik (Read-Only), dan empty state pemantauan netral.
- **`resources/views/resident/portal.blade.php`**: Menggunakan `layouts.app`, kartu identitas Penghuni, dan empty state layanan mandiri netral.

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

✓ built in 216ms
```
- **Aset CSS**: `public/build/assets/app-CVq8h3i9.css` (Bootstrap 5 + Custom SIM Kos + Print CSS).
- **Aset JS**: `public/build/assets/app-6x2dcqrK.js` (Bootstrap bundle JS + Popper).
- **Status Offline**: Tidak ada `public/hot`, seluruh pemanggilan via `@vite` mengarah langsung ke `public/build/manifest.json`.

### B. Hasil Eksekusi Test Suite (`php artisan test`)
```text
   PASS  Tests\Unit\ExampleTest
  ✓ that true is true

   PASS  Tests\Feature\ExampleTest
  ✓ the application returns a successful response

   PASS  Tests\Feature\AuditServiceTest
  ✓ 10 audit service foundation & transactional rollback tests passed

   PASS  Tests\Feature\DatabaseConstraintTest
  ✓ 18 schema constraint, foreign key, unique & check tests passed

   PASS  Tests\Feature\AuthTest
  ✓ 24 auth, session invalidation, policy & password change tests passed

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

---

## 3. Hasil Pemeriksaan UI & Lokasi Screenshot Nyata

Semua pengujian UI dijalankan menggunakan browser otomatis pada resolusi nyata:
- **Desktop**: 1366 × 768 piksel
- **Mobile**: 390 × 844 piksel
- Seluruh kredensial diverifikasi dari konfigurasi lokal; tidak ada password yang bocor atau ditampilkan pada screenshot.

Tabel artefak screenshot tersimpan di `docs/evidence/t05/`:

| No | Nama File Bukti | Deskripsi Hasil Pemeriksaan | Status |
|:---|:---|:---|:---:|
| 1 | [`01-login-desktop-1366x768.png`](docs/evidence/t05/01-login-desktop-1366x768.png) | Tampilan login desktop 1366×768: kartu terpusat, input email & kata sandi bersih tanpa autofill password mentah, tombol teal, footer hak cipta. | **LULUS** |
| 2 | [`02-login-mobile-390px.png`](docs/evidence/t05/02-login-mobile-390px.png) | Tampilan login mobile 390px: layout responsif, padding proporsional, form mudah dijangkau. | **LULUS** |
| 3 | [`03-login-validation-error.png`](docs/evidence/t05/03-login-validation-error.png) | Validasi login: input ditandai merah, pesan error 'Email wajib diisi' dan 'Password wajib diisi' muncul, `aria-invalid="true"` aktif, input password tetap kosong. | **LULUS** |
| 4 | [`04-admin-dashboard-desktop-1366x768.png`](docs/evidence/t05/04-admin-dashboard-desktop-1366x768.png) | Dashboard Admin desktop: sidebar navy gelap permanen, badge role Administrator, judul halaman, empty state ringkasan operasional netral. | **LULUS** |
| 5 | [`05-admin-dashboard-mobile-390px.png`](docs/evidence/t05/05-admin-dashboard-mobile-390px.png) | Dashboard Admin mobile: sidebar desktop tersembunyi, topbar menampilkan tombol menu hamburger. | **LULUS** |
| 6 | [`06-admin-mobile-offcanvas.png`](docs/evidence/t05/06-admin-mobile-offcanvas.png) | Mobile Offcanvas Sidebar: drawer terbuka mulus dari kiri, menampilkan logo SIM Kos, menu aktif, dan item non-aktif berlabel 'Belum tersedia'. | **LULUS** |
| 7 | [`07-change-password-desktop-1366x768.png`](docs/evidence/t05/07-change-password-desktop-1366x768.png) | Halaman Ganti Password desktop: formulir kartu, label wajib (*), helper text terhubung `aria-describedby`, tombol simpan & batal/logout. | **LULUS** |
| 8 | [`08-logout-success-desktop.png`](docs/evidence/t05/08-logout-success-desktop.png) | Notifikasi Logout: pengguna diarahkan ke login dengan alert hijau 'Anda telah berhasil keluar.', sesi dibersihkan. | **LULUS** |
| 9 | [`09-owner-dashboard-desktop-1366x768.png`](docs/evidence/t05/09-owner-dashboard-desktop-1366x768.png) | Dashboard Pemilik desktop: badge role Pemilik, menu navigasi khusus pemantauan (baca-saja), empty state netral tanpa klaim data palsu. | **LULUS** |
| 10 | [`10-owner-dashboard-mobile-390px.png`](docs/evidence/t05/10-owner-dashboard-mobile-390px.png) | Dashboard Pemilik mobile: tata letak responsif pada lebar 390px dengan navigasi offcanvas. | **LULUS** |
| 11 | [`11-temp-password-restricted-nav.png`](docs/evidence/t05/11-temp-password-restricted-nav.png) | Navigasi Terbatas Akun Password Sementara: banner peringatan aktif, menu bisnis disembunyikan sepenuhnya; hanya tersedia Ganti Password dan Logout. | **LULUS** |
| 12 | [`12-resident-portal-desktop-1366x768.png`](docs/evidence/t05/12-resident-portal-desktop-1366x768.png) | Portal Penghuni desktop: badge role Penghuni, navigasi layanan mandiri, empty state informatif netral. | **LULUS** |
| 13 | [`13-resident-portal-mobile-390px.png`](docs/evidence/t05/13-resident-portal-mobile-390px.png) | Portal Penghuni mobile: tampilan rapi pada 390px tanpa overflow horizontal. | **LULUS** |

### D. Hasil Pengujian Interaksi Keyboard Offcanvas
- **Membuka Menu**: Tombol toggle menu hamburger diakses dan diklik (`data-bs-toggle="offcanvas"`). Drawer `#simkosOffcanvasSidebar` terbuka dengan benar.
- **Menutup Menu via Tombol Escape**: Penekanan tombol keyboard `Escape` dideteksi oleh Bootstrap 5 JS, menutup drawer offcanvas secara mulus.
- **Pengembalian Fokus**: Setelah offcanvas tertutup, fokus keyboard kembali secara tepat ke elemen pemicu (tombol toggle hamburger).

---

## 4. Bagian yang Belum Selesai / Batasan Ruang Lingkup T05

Sesuai instruksi dan roadmap pengujian:
1. **Modul Bisnis CRUD (T06–T14)**: Belum diimplementasikan pada T05. Seluruh menu operasional (Kamar, Penghuni, Fasilitas, Penempatan, Tagihan, Pembayaran, Keluhan) sengaja berlabel non-interaktif "Belum tersedia" tanpa endpoint dummy.
2. **Query Metrik Bisnis & Grafik Dashboard (T18)**: Dashboard awal hanya menampilkan empty state netral dan tidak melakukan query agregasi statistik bisnis sebelum T18.
3. **Laporan & Pencetakan Faktur (T19)**: Berkas `print.css` sudah disiapkan dan diuji import-nya, namun pengujian cetak nota/laporan aktual akan dieksekusi saat modul transaksi dan cetak siap.
