# Panduan pengerjaan dengan Antigravity

## Pemakaian

Buka folder `C:\SEMESTER 5\SISTEM INFORMASI PRAKTIKUM\TA` di Antigravity. Paket `docs/plan` sudah berada di workspace dan dapat dibaca agent. Jalankan fase 1 dahulu; jangan menyalin semua prompt fase menjadi satu permintaan besar. Setiap hasil diperiksa terhadap acceptance criteria dan bukti tes. Planning tetap memberi konteks; kelulusan task ditentukan implementasi nyata.

## Prompt pembuka

```text
Kamu membantu saya mengimplementasikan TA Sistem Informasi Manajemen Kos.
Workspace ini memiliki rencana lengkap dalam docs/plan. Baca README.md,
01-produk-dan-fitur.md, 02-arsitektur-database.md, 03-proses-dan-informasi.md,
04-roadmap.md, 05-pengujian-dan-cakupan.md, dan 08-progres.md terlebih dahulu.

Periksa file/kode/environment yang sudah ada. Jangan menimpa dokumen atau
mengulang scaffold jika proyek telah dibuat. Gunakan Laravel, Blade, MySQL,
Bootstrap dan Chart.js sesuai rencana; jangan beralih stack tanpa menjelaskan
alasan dan mendapatkan keputusan pengguna.

Kerjakan Fase 1, T01–T05, berurutan. Buat implementation plan dengan task,
dependensi, file terdampak, acceptance criteria, dan verifikasi sebelum coding.
Sesuaikan langkah instalasi dengan versi environment yang benar-benar ditemukan.
Jangan menghapus/reset database atau data pengguna tanpa persetujuan eksplisit.

Setelah implementasi, jalankan tes relevan, build aset, dan cek UI jika tersedia.
Catat yang lulus/gagal/belum diuji beserta bukti pada docs/plan/08-progres.md
dan docs/evidence. Jangan mengarang hasil tes atau screenshot. Jangan lanjut
fase berikut jika gate fase ini belum terpenuhi. Jangan membuat/push repository
remote atau deploy tanpa instruksi pengguna.

Berikan walkthrough singkat: apa yang berubah, alur kode, cara menjalankan,
hasil tes, keterbatasan, dan task berikutnya agar saya memahami proyek.
```

## Prompt fase berikutnya

Setiap prompt mewarisi aturan pembuka, tetapi tetap menyebut bacaan/progres agar bisa digunakan pada percakapan baru.

### Fase 2

```text
Baca docs/plan README, dokumen 01–05 dan 08-progres.md, lalu periksa codebase.
Lanjutkan T06–T11 hanya setelah gate Fase 1 terverifikasi. Implementasikan
CRUD/cari/detail/validasi/hapus aman kamar, penghuni, fasilitas, lalu aturan
periode BillingService, penempatan atomik dan penghentian penempatan.
Uji generated unique constraint, FK, snapshot tarif, invoice pertama, tagihan
yang kurang, dan konflik penempatan memakai MySQL. Jangan mengubah asumsi
tanggal/tarif tanpa memperbarui dokumen terkait. Integrasikan policy dan audit.
Jalankan TC relevan pada roadmap; catat bukti dan progres, lalu jelaskan alur
placement–invoice–room. Berhenti pada gate Fase 2 dan laporkan hasilnya.
```

### Fase 3

```text
Baca docs/plan 02–05 dan 08, pastikan Fase 2 selesai. Kerjakan T12–T15:
tagihan dan sinkronisasi idempoten, pemeriksaan jumlah/periode, pembayaran
atomik, nomor bukti unik, void beralasan, cetak, dan portal pembayaran sendiri.
Ikuti keputusan dokumen 03, jangan menambahkan gateway/cicilan. Pastikan
jumlah salah tidak menjadi pendapatan; request ganda/bersamaan hanya membuat
satu payment valid. Uji akses bukti milik penghuni lain dan pembatalan/ulang bayar.
Catat hasil TC-12–20 serta policy terkait, bukti tes, progres, dan walkthrough.
Berhenti setelah gate Fase 3 terpenuhi; jangan membuat angka dashboard statis.
```

### Fase 4

```text
Baca docs/plan 01–05 dan 08; setelah Fase 3 selesai kerjakan T16–T17.
Bangun pengajuan keluhan penghuni/admin atas penempatan aktif, batas fasilitas
kamar/shared, pemeriksaan admin, timeline, tindak lanjut, resolve dan penutupan
tanpa tindakan dengan alasan. Ikuti tabel transisi dokumen 03. Tidak perlu chat
atau upload. Audit dan perubahan status harus atomik. Penghuni hanya melihat
keluhan sendiri. Jalankan TC-21–23 dan ownership tests, simpan hasil nyata,
perbarui progres dan jelaskan dua cabang proses Modul 2.
```

### Fase 5

```text
Baca docs/plan 02–06 dan 08. Setelah fase sebelumnya lulus kerjakan T18–T20:
dashboard empat KPI/dua grafik/satu tabel, dua laporan lengkap dan cetak,
serta halaman audit. Rumus/tanggal/status mengikuti dokumen 03; gunakan
database, bukan nilai statis. Bedakan periode sewa dan tanggal uang diterima.
Total mencakup semua hasil terfilter, bukan satu halaman. Tampilkan kekurangan
tagihan dan blokir cetak status yang belum lengkap. Uji perubahan sesudah bayar
dan void, data kosong, policy, filter/sort/cetak, angka fixture TC-25–28.
Bangun aset lokal, cek visual browser dan print. Catat bukti/progres serta
walkthrough query supaya saya bisa menjelaskan setiap angka.
```

### Fase 6

```text
Baca seluruh docs/plan dan status aktual. Kerjakan T21–T24 setelah gate Fase 5.
Lengkapi DemoSeeder deterministik, jalankan test suite MySQL dan uji UI/print,
uji dua koneksi untuk race condition, lalu rekam hasil TC-01–36 tanpa fabrikasi.
Siapkan README Windows, .env.example, panduan backup/restore dan dump SQL data
sintetis. Uji restore ke database BARU; jangan menghapus database pengguna.
Siapkan dokumentasi sistem sesuai checklist dokumen 06 dengan screenshot nyata.
Periksa rahasia sebelum penyerahan. Siapkan langkah membuat/push GitHub untuk
pengguna; jangan mempublikasikan tanpa instruksi. Jalankan naskah demo, cocokkan
angka, dan buat latihan dua anggota. Laporkan syarat yang masih belum terpenuhi;
jangan tandai proyek selesai bila tes/restore/demo belum berhasil.
```

## Prompt pemulihan sesi atau perbaikan

```text
Baca docs/plan/08-progres.md, roadmap dan dokumen aturan terkait. Periksa kode,
diff, output error/tes yang tersedia. Lanjutkan dari task belum selesai pertama;
jangan mengulang task selesai tanpa alasan. Untuk bug, jelaskan penyebab,
perbaiki pada lapisan yang tepat, tambahkan tes regresi bila menyangkut aturan
bisnis/akses/transaksi, jalankan tes terdampak dan perbarui progres.
Jika ada keputusan baru, catat dampak ke skema, proses, laporan, tes dan demo.
```

## Review manusia setelah setiap fase

Mahasiswa membuka aplikasinya, mencoba satu jalur sukses dan satu jalur gagal, mencocokkan hasil dengan database/informasi, membaca walkthrough, dan mencoba menjelaskan kembali. Jika agent hanya menunjukkan kode atau screenshot, minta menjalankan alur dan tes yang relevan. Jangan memberi status selesai berdasarkan keyakinan agent tanpa bukti.
