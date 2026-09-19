# Pengujian dan cakupan kebutuhan

## Cara menjalankan dan merekam

Seluruh kasus di bawah berstatus BELUM DIUJI. Gunakan MySQL `sim_kos_test` untuk integration/constraint test, bukan database demo. Setiap baris merupakan spesifikasi test case; hasil aktual, status lulus/gagal, tanggal, dan bukti ditambahkan dalam `docs/evidence/test-results.md` saat tes benar-benar dilakukan. ID tidak boleh diganti diam-diam karena digunakan matriks cakupan.

Set data acuan: tanggal uji dibekukan pada 10 September 2026 Asia/Jakarta; enam kamar, tiga penempatan aktif; tarif masing-masing Rp800.000, Rp900.000, Rp1.000.000; invoice September ada tiga; pembayaran valid Rp800.000 diterima 3 September. Expected awal: kamar kosong 3, terisi 3, pendapatan September Rp800.000, invoice belum bayar 2 bernilai Rp1.900.000. Nama demo: Penghuni A/B/C, bukan data pribadi nyata. Test terisolasi mengembalikan fixture sebelum kasus berikutnya.

## Test case

| ID | Prasyarat dan langkah uji | Expected result |
|---|---|---|
| TC-01 | Akun aktif setiap role; login email/password benar lalu salah | Benar masuk halaman role; salah ditolak tanpa session autentikasi |
| TC-02 | Sudah login; logout lalu akses URL privat/back dan refresh; lakukan 6 kegagalan login dalam 1 menit | Session lama tidak memberi akses; throttle membatasi sesuai konfigurasi, tidak membocorkan akun |
| TC-03 | Pemilik/penghuni kirim request mutasi master, pembayaran, role melalui URL langsung | 403 atau penolakan setara; tidak ada perubahan database |
| TC-04 | Penghuni A ganti ID URL invoice/payment/cetak/complaint menjadi milik B | Data B tidak terlihat, termasuk PDF/halaman cetak; 404/403 konsisten |
| TC-05 | Admin nonaktifkan akun yang sedang login; lakukan request berikutnya; reset password sementara | Session tidak lagi berwenang; password sementara wajib diganti; log tanpa password |
| TC-06 | Admin tambah kamar, tampilkan, cari, detail, ubah tarif, hapus kamar tanpa referensi; input nomor duplikat/tarif negatif | Semua operasi valid berhasil dan diaudit; input salah ditolak; perubahan tarif tidak mengubah histori |
| TC-07 | Tambah penghuni+akun, daftar/cari/detail/edit/hapus profil tanpa referensi; email duplikat | User+profil atomik, cari benar, user nonaktif saat hapus profil; duplikat tidak meninggalkan profil parsial |
| TC-08 | CRUD/cari/detail fasilitas kamar/shared; kirim shared dengan room_id atau room tanpa kamar | CRUD aman; kombinasi lokasi tidak valid ditolak; fasilitas terkait hanya diarsipkan |
| TC-09 | Hapus/arsip master terkait histori/penempatan aktif/keluhan terbuka | Hapus ditolak dengan alasan; arsip hanya jika aturan terpenuhi; histori utuh |
| TC-10 | Penghuni tanpa kamar dan kamar kosong; mulai penempatan; coba penghuni/kamar aktif lagi | Placement+invoice+audit tersimpan bersama; status terisi; percobaan ganda ditolak |
| TC-11 | Dua koneksi/proses MySQL berlomba menempatkan penghuni berbeda pada kamar sama, lalu satu penghuni pada kamar berbeda | Tepat satu penempatan aktif; request lain konflik; tidak ada invoice/log sukses yatim |
| TC-12 | Mulai 10 September, periode September; sync sampai September dua kali | Invoice pertama jatuh tempo 10 September, nominal penuh; sync tidak menggandakan |
| TC-13 | Aktif Agustus, invoice September hilang; buka dashboard/laporan status lalu sinkronkan | Warning kekurangan, tidak menulis invoice lewat GET, cetak status diblokir; setelah sync lengkap dan warning hilang |
| TC-14 | Penempatan aktif dengan utang, invoice bulan berjalan belum dibuat; akhiri hari ini, klik ulang | Invoice dilengkapi sekali, kamar kosong, utang/riwayat ada, ended_on tidak berubah pada klik ulang |
| TC-15 | Invoice B Rp900.000; submit Rp850.000, Rp950.000, negatif, atau tanggal sebelum periode | Ditolak; tidak ada payment sah, status tetap belum bayar, pendapatan tidak berubah |
| TC-16 | Invoice B belum bayar; submit Rp900.000 tanggal 10 September lalu reload | Nomor unik, satu payment valid/audit; lunas; pendapatan Rp1.700.000, belum bayar 1 |
| TC-17 | Submit ulang dan dua koneksi membayar invoice B bersamaan | Tepat satu payment valid; tidak ada pendapatan ganda; error/redirect konsisten |
| TC-18 | Payment B valid; void tanpa alasan lalu alasan valid; coba void ulang dan bayar ulang | Alasan wajib; setelah void invoice belum bayar, pendapatan turun; metadata/audit utuh; bayar ulang nomor baru |
| TC-19 | Admin/pemilik/penghuni pemilik membuka bukti; cetak valid lalu void | Detail benar, status DIBATALKAN pada void, navigasi tidak tercetak; kepemilikan tetap diperiksa |
| TC-20 | Penghuni tanpa penempatan lalu penghuni keluar yang akunnya aktif | Empty state aman; histori sendiri tetap ada; tidak bisa mengajukan keluhan baru setelah keluar |
| TC-21 | Penghuni A ajukan keluhan fasilitas kamar A/shared lalu coba fasilitas kamar B | Yang sah tercatat dan terlihat admin; fasilitas asing ditolak, actor ditentukan server |
| TC-22 | Keluhan open → in_progress → tambah catatan → resolved | Timeline berurutan, status/closed_at benar, audit lengkap; kondisi fasilitas tidak otomatis berubah |
| TC-23 | Keluhan open → closed_without_action tanpa/dengan alasan; coba transisi terminal | Tanpa alasan ditolak; dengan alasan sukses; penghuni melihatnya, transisi terminal ditolak |
| TC-24 | Lakukan master/placement/payment/complaint/report, cari audit; coba edit/hapus log | Nama, aksi, modul, data, waktu ada; filter tepat; tidak tersedia mutasi log; tidak ada secret |
| TC-25 | Fixture awal; buka dashboard, bayar B, void B, refresh tiap langkah | KPI awal 3/3/800.000/2; setelah bayar 3/3/1.700.000/1; setelah void kembali awal; tabel/grafik sesuai |
| TC-26 | Database bisnis kosong; grafik enam bulan; tidak ada kamar | KPI nol, bulan kosong nol, tabel empty state, persentase tidak divide-by-zero, dua grafik tetap memiliki penjelasan |
| TC-27 | Payment bulan lain, valid/void, >25 record; filter rentang/search/sort/cetak pendapatan | Hanya valid sesuai paid_on; total seluruh hasil bukan halaman; cetak sama, audit view/print ada |
| TC-28 | Invoice lunas/belum bayar/terlambat, termasuk penghuni keluar; laporan status per bulan | Semua kewajiban muncul, status terkini benar; total lunas+belum bayar=total invoice terfilter; filter/cetak konsisten |
| TC-29 | Laptop 1366×768, lebar 390, keyboard; putus internet setelah build | Navigasi/form/tabel dapat digunakan, notifikasi/error terbaca, grafik/aset tetap tampil |
| TC-30 | Paksa gagal pembuatan profil setelah user dibuat; edit nama; coba ubah role lewat payload | Rollback user baru; nama akun/profil sinkron; snapshot invoice tetap; promosi role ditolak |
| TC-31 | Insert FK tidak valid, nomor/email duplikat, status ilegal, dua active_room_id/valid_invoice_id | Constraint database menolak; histori ended/void masih dapat lebih dari satu |
| TC-32 | Paksa AuditService gagal saat pembayaran atau penempatan | Seluruh mutasi rollback; tidak ada transaksi tanpa audit; pesan gagal tampil |
| TC-33 | Ubah harga kamar setelah kontrak; generate bulan baru; uji mulai tgl 1/5/10 dan Februari tahun kabisat | Invoice lama/baru kontrak tetap agreed rate; kontrak baru memakai harga baru; jatuh tempo dan bulan tepat |
| TC-34 | Stop/start aplikasi dan MySQL dengan data demo sudah tersimpan | Data/riwayat tetap, aplikasi tersambung kembali, tidak berubah menjadi data mock |
| TC-35 | Dump DB demo lalu restore DB baru, jalankan aplikasi pada DB baru | Jumlah record, FK, login, KPI dan transaksi contoh setara; source + SQL cukup untuk pemulihan |
| TC-36 | Audit berkas penyerahan dan jalankan urutan demo dokumen 06 | Source, SQL, GitHub, dokumentasi/test results ada; tidak ada .env/password nyata; dua anggota bisa menjelaskan |

Tes keamanan lintas modul pada TC-03/04 harus diparameterisasi agar seluruh endpoint mutasi/cetak tercakup, bukan hanya satu contoh. TC-11/17 benar-benar memakai dua koneksi/proses; tes request berurutan tidak membuktikan keamanan konkurensi. Uji print melalui browser nyata dan simpan bukti saat implementasi.

## Matriks ketentuan TA

| Syarat sumber | Implementasi | Task | Bukti/test |
|---|---|---|---|
| C1 Login/logout | F01 | T04 | TC-01/02 |
| C2 Minimal dua role/menu | Tiga role dan ownership | T04,T15 | TC-03/04/05 |
| C3 Tiga master semua operasi | F02/F03/F04 | T06–T08 | TC-06/07/08/09 |
| C4 Transaksi lintas tabel | Pembayaran invoice→placement→resident/room + audit | T09–T14 | TC-10–19 |
| C5 3 KPI, 2 grafik, 1 tabel database | F09 empat KPI, dua grafik, satu tabel | T18 | TC-25/26 |
| C6 Dua laporan, periode/cari/sort/rekap/cetak | F10 | T19 | TC-27/28 |
| C7 Audit dengan pengguna/aksi/modul/data/waktu | F11 dan service lintas fitur | T03,T20 | TC-24/32 |
| C8 PK/FK/relasi/tipe/data contoh | Kamus, ERD, migration, seeder | T02,T21 | TC-31/35 |
| C9 UI konsisten, responsif, notifikasi/validasi | Layout dan FormRequest tiap modul | T05, semua UI | TC-06–08/29 |
| D Source, .sql, GitHub, dokumentasi lengkap | Checklist penyerahan | T22/T23 | TC-35/36 |
| E Demo langsung urut dan database nyata | Naskah demo | T24 | TC-34/36 |
| F Sistem utuh, dua anggota memahami, pembagian merata | Semua fase dan latihan silang | T24 | TC-36 |

## Matriks IRM Modul 3

| IRM | Pengguna, informasi dan waktu | Halaman/data | Bukti/test |
|---|---|---|---|
| IRM-01 | Admin: identitas sebelum menentukan kamar | F03/F05; residents.name/phone, placements.started_on | TC-07/10 |
| IRM-02 | Admin: kamar kosong sebelum penempatan | F02/F05; rooms, placements aktif | TC-10/11 |
| IRM-03 | Admin: kamar tersedia saat memilih | F05; room, resident, status turunan | TC-10/11 |
| IRM-04 | Admin: hasil setelah penempatan | F05 detail; resident, room, started_on, ended_on | TC-10/14 |
| IRM-05 | Admin: kewajiban saat pemeriksaan bayar | F06/F07; invoices.amount/period_month dan resident | TC-12/15/33 |
| IRM-06 | Admin: kesesuaian setelah menerima pembayaran | F07; input amount dibanding invoice.amount/period | TC-15/16 |
| IRM-07 | Admin: status setelah dinyatakan sesuai | F06/F07; invoice dan payment valid | TC-16/18 |
| IRM-08 | Penghuni: hasil setelah dicatat | F12/F07; payment paid_on/amount, invoice period/status | TC-04/19/20 |
| IRM-09 | Admin: keluhan setelah diterima | F08; description, placement/room/resident, facility, created_at | TC-21 |
| IRM-10 | Admin: kondisi untuk keputusan tindak lanjut | F08/F04; isi keluhan dan condition fasilitas | TC-22/23 |
| IRM-11 | Admin: perkembangan setelah tindakan | F08; updates.note/occurred_at/to_status | TC-22/24 |
| IRM-12 | Penghuni: status setelah diperbarui | F12/F08; complaint dan timeline sendiri | TC-04/22/23 |

Tanggal mulai untuk calon penghuni baru ditampilkan sebagai tanggal mulai yang direncanakan hari ini pada form, belum dianggap riwayat faktual sebelum penempatan tersimpan. Informasi pemilik mengikuti Modul 1 meskipun tidak menjadi aktor dua belas baris IRM.
