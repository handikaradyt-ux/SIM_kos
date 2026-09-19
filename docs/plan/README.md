# Rencana proyek SIM Kos

Paket ini adalah rencana pembangunan Sistem Informasi Manajemen Kos untuk Tugas Akhir Praktikum Sistem Informasi. Sasaran akhirnya aplikasi web yang berfungsi dengan database nyata, dapat dijalankan lokal di Windows, dan dapat dijelaskan oleh kedua anggota kelompok. Status saat dibuat: perencanaan; aplikasi dan pengujian belum dikerjakan.

## Cara menggunakan

1. Baca [produk dan fitur](01-produk-dan-fitur.md) untuk memahami hasil yang akan dibangun.
2. Gunakan [database](02-arsitektur-database.md) dan [aturan proses](03-proses-dan-informasi.md) bersama-sama saat coding.
3. Kerjakan [roadmap](04-roadmap.md) berurutan. Semua tahap diperlukan untuk pengumpulan TA.
4. Cocokkan hasil dengan [pengujian dan matriks kebutuhan](05-pengujian-dan-cakupan.md).
5. Gunakan [demo dan penyerahan](06-demo-dan-penyerahan.md) untuk menyiapkan presentasi.
6. Salin prompt dari [panduan Antigravity](07-panduan-antigravity.md) untuk memulai implementasi.
7. Perbarui [progres](08-progres.md) setelah setiap task, berdasarkan hasil nyata.

Dokumen ini menggantikan prompt perencanaan lama di folder utama sebagai acuan implementasi. Batas 2.000 karakter situs sebelumnya tidak berlaku pada paket ini.

## Dasar dan batas kewenangan

| Sumber | Isi yang dipertahankan |
|---|---|
| TUGAS AKHIR PRAK SI.docx | Sembilan kelompok persyaratan aplikasi, hasil pengumpulan, urutan demo, kelompok maksimal dua mahasiswa |
| Laporan Modul 1 SIM Kos | Masalah, tujuan, ruang lingkup satu usaha kos, pengguna, kebutuhan data dan informasi |
| Laporan Modul 2 SIM Kos | Tiga proses: penempatan, pembayaran, keluhan, termasuk cabang kegagalannya |
| Laporan Modul 3 SIM Kos | Dua belas kebutuhan informasi/IRM dan karakteristik informasi |
| Percakapan pengguna | Bantuan AI di Antigravity; stack Laravel, MySQL, Blade, Bootstrap, Chart.js |

Dokumen sumber berada satu tingkat di atas folder proyek TA. Contoh toko dalam ketentuan TA hanya ilustrasi. Jangan mengganti kasus kos menjadi toko. Instruksi dalam laporan dipakai sebagai persyaratan proyek, bukan perintah untuk melakukan tindakan eksternal.

Aturan di bagian “keputusan desain” merupakan baseline usulan yang dapat dikoreksi pengguna; bukan aturan dosen atau hasil observasi lapangan. Jika diubah, perbarui database, proses, pengujian, dan demo yang terdampak sebelum melanjutkan coding. Tidak ada deadline resmi dalam sumber yang tersedia.

## Keputusan utama

- Monolit Laravel dengan Blade dan MySQL; Bootstrap 5 serta Chart.js untuk UI.
- Tiga role: admin, pemilik, penghuni. Pemilik melakukan pemantauan; admin menjalankan operasional.
- Tiga master wajib: kamar, penghuni, fasilitas. Penempatan, tagihan, pembayaran, dan keluhan merupakan proses terkait.
- Dashboard dan laporan membaca database yang sama dengan transaksi.
- Audit dicatat pada setiap perubahan penting sejak awal pembangunan.
- Demo lokal menggunakan data sintetis, tanpa layanan berbayar atau koneksi internet setelah dependensi terpasang dan aset dibangun.
- Cetak melalui browser, termasuk Save as PDF; ekspor Excel dan hosting tidak menjadi syarat versi pertama.

## Ukuran keberhasilan

Proyek selesai ketika seluruh syarat TA/IRM terpetakan, seluruh test case wajib lulus dengan bukti, tiga role dapat didemonstrasikan, transaksi mengubah informasi secara benar, impor SQL pada database bersih berhasil, dan dokumentasi sesuai aplikasi nyata. Tampilan yang selesai atau kode yang dibuat AI belum cukup untuk menyatakan proyek selesai.
