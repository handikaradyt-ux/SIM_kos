# SIM Kos - Sistem Informasi Manajemen Kos

Aplikasi Sistem Informasi Manajemen Kos berbasis web monolit Laravel untuk Tugas Akhir Praktikum Sistem Informasi.

## Dokumen Acuan dan Rencana

Seluruh dokumen spesifikasi, arsitektur, proses bisnis, pengujian, dan progres implementasi tersedia di direktori `docs/plan/`:
- [Panduan Proyek](docs/plan/README.md)
- [Produk dan Fitur](docs/plan/01-produk-dan-fitur.md)
- [Arsitektur dan Database](docs/plan/02-arsitektur-database.md)
- [Proses Bisnis dan Informasi](docs/plan/03-proses-dan-informasi.md)
- [Roadmap Implementasi](docs/plan/04-roadmap.md)
- [Pengujian dan Cakupan](docs/plan/05-pengujian-dan-cakupan.md)
- [Demo dan Penyerahan](docs/plan/06-demo-dan-penyerahan.md)
- [Catatan Progres](docs/plan/08-progres.md)

## Stack Teknologi

- **Framework**: Laravel 13.x (PHP ^8.3)
- **Database**: MySQL 8.0.30+ (InnoDB, utf8mb4)
- **Frontend**: Blade, Vite, Tailwind CSS / Bootstrap 5
- **Testing**: PHPUnit 12.x / Artisan Test
- **Manajer Paket**: Composer 2.x & npm 11.x

## Menjalankan di Lingkungan Lokal Windows

1. **Pastikan Layanan MySQL Berjalan**:
   - Buka Laragon atau MySQL Service lokal (port default `3306`).
   - Database utama: `sim_kos`
   - Database pengujian: `sim_kos_test`

2. **Salin Environment & Pasang Dependensi**:
   ```powershell
   copy .env.example .env
   php artisan key:generate
   composer install
   npm install
   ```

3. **Kompilasi Aset Frontend**:
   ```powershell
   npm run build
   ```

4. **Jalankan Server Pengembangan**:
   ```powershell
   php artisan serve
   ```
   Akses melalui browser di [http://127.0.0.1:8000](http://127.0.0.1:8000).

5. **Menjalankan Pengujian**:
   ```powershell
   php artisan test
   ```
