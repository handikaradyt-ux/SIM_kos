# Demo dan penyerahan

## Persiapan Windows

Dokumen ini menjelaskan prosedur yang harus diwujudkan dan diuji saat implementasi; perintah scaffold atau database belum dijalankan pada tahap plan. README aplikasi final harus mencantumkan versi aktual yang lolos T01, bukan menyalin asumsi versi tanpa verifikasi.

1. Pastikan PHP yang dipakai terminal benar, Composer tersedia, ekstensi yang diminta Composer aktif, MySQL berjalan, dan Node sesuai versi Vite.
2. Instal dependency dari lockfile dengan Composer dan npm; jangan mengganti lockfile diam-diam pada mesin demo.
3. Salin .env.example menjadi .env, isi koneksi database lokal, buat application key, set APP_DEBUG hanya untuk lokal. Jangan tampilkan password di dokumentasi/screenshots.
4. Untuk database baru, jalankan migration dan seeder demo. Untuk pemulihan dari .sql, gunakan database kosong terpisah dan jangan menjalankan seeder lagi di atas dump yang sudah berisi data.
5. Build aset lokal, mulai server Laravel lokal dan buka aplikasi. Server pengembangan hanya untuk demo lokal, bukan deployment publik.
6. Tes login semua role, dashboard, bukti cetak, laporan, lalu putus internet untuk memeriksa aset lokal.

Perintah final seperti `composer install`, `npm ci`, `php artisan key:generate`, `php artisan migrate --seed`, `npm run build`, dan `php artisan serve` harus diuji pada proyek nyata sebelum README dinyatakan selesai. Koneksi MySQL dan nama database harus dijelaskan dengan contoh placeholder; jangan memasukkan password asli ke command history atau Git.

## Backup, restore, dan reset

Gunakan mysqldump untuk ekspor skema+data, termasuk constraint generated column, lalu uji impor ke `sim_kos_restore`. Di Windows, gunakan opsi file keluaran tool yang sesuai untuk menghindari perubahan encoding oleh redirection shell. Minta password secara interaktif atau gunakan konfigurasi lokal yang tidak masuk Git. Catat versi tool, nama berkas, waktu, jumlah baris tiap tabel, dan checksum berkas dalam bukti pemulihan.

Setelah restore: arahkan koneksi aplikasi ke database baru, bersihkan cache konfigurasi sesuai kebutuhan, login tiga role, cocokkan KPI, tampilkan relasi/history, dan lakukan satu transaksi uji. Setelah selesai arahkan kembali ke database demo secara eksplisit. Jangan menganggap keberadaan file .sql membuktikan restore berhasil.

Reset demo memakai restore dump dasar ke database demo khusus. Jika memakai migrate:fresh, perintah itu hanya boleh berjalan setelah environment lokal dan nama database demo telah diverifikasi, dengan konfirmasi eksplisit karena menghapus data. Tidak menjalankan reset otomatis pada database yang belum diketahui isinya.

## Dataset demo yang deterministik

DemoSeeder mengambil bulan M = bulan tanggal demo, dengan opsi tanggal acuan eksplisit untuk tes. Tanggal dibekukan hanya di pengujian, bukan diam-diam mengubah tanggal aplikasi presentasi. Akun contoh admin@example.test, owner@example.test, resident.a@example.test dan seterusnya; password demo ditetapkan pada setup lokal, berbeda dari kredensial nyata.

| Data | Kondisi awal |
|---|---|
| Kamar | K01–K06; K01/K02/K03 terisi, K04/K05/K06 kosong |
| Penghuni | A di K01 tarif Rp800.000; B di K02 Rp900.000; C di K03 Rp1.000.000; D belum ditempatkan |
| Penempatan A/B/C | Mulai tanggal 1 bulan M-2; tiga periode tagihan lengkap sampai M |
| Pembayaran M | Hanya A valid Rp800.000, tanggal 1 M |
| Pembayaran M-2 dan M-1 | Masing-masing A+B+C lunas, total Rp2.700.000 per bulan |
| Contoh pembayaran void | Tambahkan satu record void untuk invoice A bulan M-1, selain pembayaran penggantinya yang valid; nominal void tidak menambah total di atas |
| Fasilitas | Minimal fasilitas di K01/K02/K03 dan satu fasilitas area bersama |
| Keluhan | Contoh open, in_progress, resolved, closed_without_action dengan timeline konsisten |

Expected awal bulan M: kamar kosong 3, terisi 3, pendapatan Rp800.000, tagihan belum bayar 2/total Rp1.900.000. Donut 50% kosong dan 50% terisi. Grafik tiga bulan terakhir Rp2.700.000, Rp2.700.000, Rp800.000; tiga bulan sebelumnya nol. Data tambahan untuk pengujian pagination hanya pada database test, tidak mengubah baseline demo ini.

## Naskah demonstrasi 12–15 menit

Durasi adalah saran. Ikuti urutan dosen; aktivitas tambahan dipakai bila waktu tersedia.

| Menit | Tindakan | Hasil yang ditunjukkan |
|---|---|---|
| 0–1 | Jelaskan masalah dan tujuan kos | Data tersebar menjadi informasi untuk penempatan, penagihan, dan perbaikan |
| 1–2 | Tunjukkan proses Modul 2 dan ERD | Penempatan, pemeriksaan pembayaran, dan keputusan tindak lanjut |
| 2–3 | Jelaskan tiga role; login pemilik, penghuni B, lalu admin | Menu berbeda dan penghuni hanya melihat miliknya |
| 3–6 | Tambah/cari/detail/edit/hapus data uji tanpa relasi pada ketiga master | Demonstrasi kewajiban CRUD tanpa merusak baseline enam kamar; gunakan kode TEMP dan hapus sesudahnya |
| 6–8 | Buka tagihan B bulan M Rp900.000, coba Rp850.000 lalu bayar benar Rp900.000 hari ini | Salah nominal ditolak; benar menghasilkan nomor bukti, lunas, riwayat dan cetak |
| 8–9 | Buka detail tagihan/pembayaran dan dashboard | Pendapatan menjadi Rp1.700.000; belum bayar tinggal 1/Rp1.000.000; kamar tetap 3/3 |
| 9–11 | Laporan bulan M, cari B, ubah sort, hilangkan pencarian, cetak | Total pendapatan Rp1.700.000; total invoice Rp2.700.000, lunas Rp1.700.000, belum bayar Rp1.000.000 |
| 11–12 | Tampilkan audit master, pembayaran, dan cetak laporan | Pelaku, aksi, modul, data, waktu jelas |
| 12–15 | Demonstrasi tambahan: tempatkan D ke K04; proses keluhan lalu login penghuni terkait | Kamar berubah; tagihan baru terbentuk; timeline terlihat pada akun yang benar |

Tambahan penempatan setelah demonstrasi angka utama sengaja ditempatkan terakhir agar tidak mengubah expected laporan di tengah demo. Jika ingin menunjukkan void, lakukan setelah laporan utama: payment B dibatalkan dengan alasan, pendapatan kembali Rp800.000 dan invoice belum bayar kembali 2; bukti lama tetap ada bertanda DIBATALKAN.

## Checklist pengumpulan

| Hasil | Isi dan pemeriksaan wajib | Status awal |
|---|---|---|
| Source code | Aplikasi, migration, seeder, tests, lockfile, .env.example, README | Belum dibuat |
| Database .sql | Skema+data demo, restore ke DB baru sudah diuji | Belum dibuat |
| Link GitHub | Repository berisi kode final; akses sesuai instruksi kelas; tidak ada secret | Belum dibuat |
| Dokumentasi masalah/tujuan | Mengacu Modul 1, tidak mengarang observasi baru | Bahan sumber tersedia |
| Proses bisnis | Tiga BPMN Modul 2, penjelasan cabang, catatan ambiguitas lane penutupan | Bahan sumber tersedia |
| Kebutuhan pengguna | Role, user stories, IRM dan matriks akses | Rencana tersedia |
| ERD/data dictionary | Disesuaikan skema final yang benar-benar digunakan | Rencana tersedia |
| Penjelasan master/transaksi | Field, aturan, alur sukses/gagal, screenshot nyata | Belum implementasi |
| Dashboard/laporan | Rumus, filter, total, screenshot/cetak nyata | Belum implementasi |
| Audit | Struktur data, kegiatan tercatat, bukti log nyata | Belum implementasi |
| Dokumen pengujian | TC-01–36 dengan hasil aktual, bukti, tanggal, status | Belum diuji |

Dokumentasi sistem final dapat disusun sebagai DOCX/PDF saat aplikasi sudah berjalan. Paket Markdown plan ini merupakan bahan kerja, bukan bukti bahwa implementasi/pengujian selesai. Slide presentasi tambahan boleh dibuat setelah screenshot nyata tersedia; bukan pengganti aplikasi live.

## Latihan pemahaman dua anggota

| Pertanyaan | Poin jawaban yang harus dikuasai |
|---|---|
| Mengapa ini sistem informasi, bukan CRUD saja? | Data master diproses menjadi penempatan/tagihan/pembayaran, lalu dashboard/laporan membantu keputusan |
| Bagaimana mencegah dua penghuni mengambil kamar sama? | Lock saat transaksi dan unique untuk penempatan aktif; bukan sekadar cek UI |
| Mengapa harus ada tagihan selain pembayaran? | Penghuni yang belum pernah membayar tetap mempunyai kewajiban yang bisa ditampilkan |
| Mengapa jumlah pendapatan berbeda dari total tagihan? | Pendapatan memakai payment valid berdasar paid_on; tagihan adalah kewajiban periode sewa |
| Bagaimana tarif lama tetap benar? | Harga kamar disalin ke kontrak/penempatan dan nominal invoice; tidak membaca harga terkini untuk histori |
| Bagaimana penghuni dibatasi ke datanya sendiri? | Policy server mengikuti invoice→placement→resident→user, termasuk URL cetak |
| Apa yang terjadi jika penulisan audit gagal? | Mutasi transaksi rollback supaya tidak ada transaksi sukses tanpa log |
| Mengapa pembayaran salah tidak langsung dihapus? | Void menyimpan identitas, alasan, dan bukti historis; laporan hanya menghitung valid |
| Apa keterbatasan versi pertama? | Tarif bulanan penuh, satu penghuni/kamar, tanpa prorata/gateway/refund, laporan status terkini |
| Bagian apa yang dibantu AI? | Jelaskan task aktual, cara meninjau kode, tes yang dijalankan, dan koreksi yang dilakukan; jangan mengklaim pekerjaan manual yang tidak terjadi |

Masing-masing anggota harus menjalankan seluruh demo sendiri minimal sekali dan menjawab pertanyaan lintas modul, bukan hanya bagian yang dipimpinnya.
