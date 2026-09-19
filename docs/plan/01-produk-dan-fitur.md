# Produk dan fitur SIM Kos

## Tujuan dan pengguna

SIM Kos memusatkan data yang sebelumnya diasumsikan tersebar dalam catatan manual dan pesan. Admin dapat memastikan ketersediaan kamar, kewajiban sewa, dan penanganan keluhan. Pemilik memperoleh informasi pendapatan dan kondisi kos untuk menentukan tindakan. Penghuni dapat memastikan pembayaran tercatat dan mengikuti keluhannya.

| Pengguna | Kebutuhan utama | Contoh keberhasilan |
|---|---|---|
| Admin | Menjalankan operasional | Menempatkan penghuni, mencatat pembayaran, dan melihat hasil langsung |
| Pemilik | Pengawasan dan keputusan | Membaca tren pendapatan, kewajiban belum dibayar, dan kondisi fasilitas |
| Penghuni | Informasi layanan sendiri | Melihat riwayat pembayaran dan perkembangan keluhan pribadi |

Ruang lingkup mengikuti Modul 1: kamar, penghuni, penempatan, tarif sewa, pembayaran, fasilitas, keluhan, tindak lanjut, dan informasi pendapatan. Di luar scope laporan: usaha kos pihak lain, jual beli properti, keuangan pribadi, penggajian, layanan eksternal, dan kebutuhan pribadi penghuni.

Batas desain versi pertama: tanpa marketplace, multi-tenant SaaS, booking publik, chat langsung, unggah berkas, gateway pembayaran, WhatsApp, chatbot, aplikasi mobile, atau sistem akuntansi lengkap. Keluhan, fasilitas, audit, dan dokumentasi tetap fitur inti. Semua fase roadmap wajib selesai.

## Katalog fitur

| ID | Modul dan halaman | Fungsi wajib dan hasil |
|---|---|---|
| F01 | Login, profil, akun | Login email/password, logout, ganti password sendiri; admin membuat akun penghuni dan menonaktifkannya |
| F02 | Kamar: daftar, form, detail | Tambah, cari/filter, lihat, edit, hapus aman, detail; nomor, jenis, tarif, fasilitas, status okupansi |
| F03 | Penghuni: daftar, form, detail | Tambah, cari/filter, lihat, edit, hapus aman, detail; identitas, akun, riwayat penempatan/pembayaran |
| F04 | Fasilitas: daftar, form, detail | Tambah, cari/filter, lihat, edit, hapus aman, detail; kamar/area bersama, kondisi, keluhan terkait |
| F05 | Penempatan: form, daftar, detail | Pilih penghuni dan kamar kosong; mulai/akhiri penempatan; riwayat tetap tersimpan |
| F06 | Tagihan: daftar, detail, sinkronisasi | Kewajiban per penempatan/bulan; belum bayar/lunas; tombol membuat tagihan yang belum ada |
| F07 | Pembayaran: form, daftar, detail, cetak | Periksa jumlah/periode, catat pembayaran sah, nomor otomatis, bukti cetak, pembatalan beralasan |
| F08 | Keluhan: daftar, form, detail | Pengajuan, pemeriksaan, tindak lanjut, penyelesaian/penutupan; timeline catatan |
| F09 | Dashboard manajemen | Empat KPI, dua grafik, tabel pembayaran terbaru; tautan ke data sumber |
| F10 | Dua laporan dan halaman cetak | Pendapatan dan status pembayaran; filter, cari, urutkan, rekap, cetak/Save as PDF |
| F11 | Audit trail | Daftar/detail; filter pelaku, waktu, modul, aksi; tidak tersedia edit/hapus |
| F12 | Portal penghuni | Ringkasan penempatan, tagihan/riwayat/bukti sendiri, keluhan dan timeline sendiri |

## Matriks hak akses

“Lihat” mencakup daftar/detail yang relevan. Semua izin diperiksa pada server dan endpoint cetak; manipulasi URL atau ID tidak boleh membuka data orang lain.

| Aksi | Admin | Pemilik | Penghuni |
|---|---|---|---|
| Login/logout, ganti password sendiri | Ya | Ya | Ya |
| Master kamar/penghuni/fasilitas | CRUD, arsip | Lihat | Ringkasan kamar/fasilitas terkait sendiri |
| Membuat/menutup penempatan | Ya | Lihat | Lihat sendiri |
| Membuat tagihan yang kurang | Ya | Tidak | Tidak |
| Tagihan/pembayaran/bukti | Semua | Lihat semua | Lihat sendiri |
| Mencatat/membatalkan pembayaran | Ya | Tidak | Tidak |
| Membuat keluhan | Atas nama penghuni aktif | Tidak | Milik sendiri, saat aktif |
| Menangani/menutup keluhan | Ya | Lihat | Lihat sendiri |
| Dashboard manajemen/laporan | Ya | Ya | Tidak |
| Audit | Lihat semua | Lihat semua | Tidak |
| Akun penghuni | Buat, aktif/nonaktif, reset password sementara | Tidak | Tidak |
| Akun admin/pemilik dan promosi role | Seeder/perintah pemeliharaan terdokumentasi, tanpa UI promosi | Tidak | Tidak |

Pemilik read-only dan pembatasan pengelolaan akun adalah keputusan desain. Admin dapat mengubah password sendiri tetapi tidak mengubah role sendiri. Reset password penghuni memaksa pergantian saat login; log hanya mencatat aksi, bukan password. Akun nonaktif harus kehilangan akses pada request berikutnya. Akun admin/pemilik tidak boleh dihapus melalui UI. Penghuni tanpa penempatan boleh login tetapi mendapat petunjuk menghubungi admin, bukan data penghuni lain.

## Kontrak UI

Admin: Dashboard, Kamar, Penghuni, Fasilitas, Penempatan, Tagihan, Pembayaran, Keluhan, Laporan, Audit, Akun. Pemilik memperoleh menu pemantauan tanpa tombol mutasi. Penghuni: Beranda, Pembayaran Saya, Keluhan Saya, Profil.

Layout memakai sidebar, judul halaman, breadcrumb seperlunya, pencarian/filter, tabel paginasi 10/25/50, dan tombol aksi yang jelas. Form menampilkan label, nilai sebelumnya saat gagal, pesan error per field, serta notifikasi sukses. Konfirmasi hapus menunjukkan nama data dan akibatnya. Status memakai teks serta warna; informasi tidak bergantung warna saja. Fokus keyboard terlihat, label terkait input, dan grafik memiliki ringkasan angka.

Target uji laptop 1366×768, ditambah 390 px untuk memastikan navigasi/tabel dapat digunakan. Rupiah tanpa pecahan pada UI; input menerima nominal bulat positif. Format tanggal Indonesia, waktu Asia/Jakarta. Aset disimpan lokal; tidak memakai font atau script CDN saat demo. Halaman cetak menyembunyikan navigasi dan menampilkan judul, filter, waktu cetak, tabel, total, serta nomor halaman bila browser mendukungnya.

## Validasi umum master

- Kamar: nomor unik 1–20 karakter, jenis 1–50, tarif bulanan Rp1–Rp999.999.999; status terisi dihitung dari penempatan, bukan input bebas.
- Penghuni: nama 2–100, telepon string 8–20 karakter dengan tanda +/spasi/- yang dibatasi, alamat 5–255; tanggal mulai tinggal berasal dari penempatan. Hindari mengumpulkan NIK/foto KTP untuk demo.
- Fasilitas: kode unik 1–30, nama 2–100, kondisi baik/rusak/diperbaiki, lokasi valid; kamar wajib untuk lokasi kamar, nama area wajib untuk area bersama.
- Nama orang boleh sama; email akun dan kode master harus unik tanpa membedakan kapitalisasi. FK harus menunjuk data yang valid dan belum diarsipkan untuk operasi baru.
- Data tanpa referensi bisnis dapat dihapus permanen dengan log snapshot. Data berhistori hanya diarsipkan; kamar/penghuni dengan penempatan aktif tidak boleh diarsipkan. Fasilitas dengan keluhan terbuka juga tidak boleh diarsipkan. Nomor/kode data arsip tetap dicadangkan.

## Keputusan yang perlu diketahui pengguna

Baseline: satu kamar untuk satu penghuni aktif, sewa bulanan penuh, tidak ada cicilan/deposit/denda/prorata. Ketentuan ini belum ditentukan sumber. Detail penagihan dan koreksi tercantum pada dokumen proses. Ambiguitas lane penutupan keluhan di BPMN dicatat di sana. Jika kenyataan kos berbeda, ubah baseline sebelum implementasi transaksi.
