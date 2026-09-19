# Panduan dan Dokumentasi AuditService SIM Kos

Dokumen ini menjelaskan arsitektur, kontrak kerja, tanggung jawab pemanggil (*caller*), dan contoh implementasi [AuditService](file:///c:/SEMESTER%205/SISTEM%20INFORMASI%20PRAKTIKUM/TA/app/Services/AuditService.php) untuk seluruh service bisnis Sistem Informasi Manajemen Kos.

---

## 1. Arsitektur dan Prinsip Kerja

`AuditService` bertindak sebagai pintu tunggal pencatatan riwayat aktivitas bisnis ke tabel `activity_logs`. Karakteristik utama:

1. **Sinkron dalam Transaksi Database**:
   - Pencatatan audit dieksekusi secara langsung (*synchronous* via Eloquent / PDO) pada koneksi database yang sama dengan mutasi bisnis.
   - Tidak menggunakan *background queue* atau *afterCommit* callback, karena jika pencatatan audit gagal, seluruh transaksi bisnis **wajib dibatalkan (rollback)**.
2. **Tanpa Penangkap Error (No Error Swallowing)**:
   - `AuditService` sengaja membiarkan setiap exception database (seperti constraint error atau query failure) terlempar ke pemanggil (*caller*).
   - Hal ini memastikan blok `DB::transaction()` menangkap exception tersebut dan membatalkan mutasi bisnis secara atomik.
3. **Identitas Pelaku Terpercaya dari Server Context**:
   - Metode `log()` hanya menerima instans `User` dari konteks server (misal `auth()->user()`), bukan dari payload atau parameter request browser.
   - Snapshot nama pelaku (`actor_name`) disimpan terpisah sehingga jika akun pengguna diubah namanya di masa mendatang, rekam jejak historis tetap utuh dan akurat.
4. **Dukungan Aktor Sistem**:
   - Aksi latar belakang (seperti cron scheduler atau batch job) dicatat melalui metode eksplisit `logSystem()` yang mencatat `actor_id = null` dan `actor_name = 'Sistem'`.
5. **Waktu Disimpan UTC, Ditampilkan Asia/Jakarta**:
   - Kolom `occurred_at` ditentukan oleh server menggunakan waktu UTC (`now('UTC')`).
   - Konversi ke zona waktu lokal `Asia/Jakarta` (WIB) dilakukan saat data ditampilkan, melalui method [occurredAtJakarta()](file:///c:/SEMESTER%205/SISTEM%20INFORMASI%20PRAKTIKUM/TA/app/Models/ActivityLog.php#L40-L45) pada model `ActivityLog`.

---

## 2. Tanggung Jawab Caller (PENTING)

> [!WARNING]
> **AuditService tidak dapat menjamin atomisitas secara mandiri jika caller tidak membuka transaksi.**
> `AuditService` hanya melakukan query `INSERT` ke tabel `activity_logs`. Tanggung jawab membuka, mengelola, dan meng-commit transaksi database berada sepenuhnya pada **Service Bisnis / Caller**.

Setiap service bisnis yang melakukan mutasi data (F02–F08, F10–F14) **WAJIB** membungkus mutasi dan panggilan `AuditService` di dalam `DB::transaction()`:

```php
use Illuminate\Support\Facades\DB;

// BENAR: Atomik, jika audit gagal mutasi ikut rollback
DB::transaction(function () use ($data, $actor) {
    $entity = Entity::create([...]);
    $this->auditService->log(...);
    return $entity;
});

// SALAH: Tanpa transaksi, jika audit gagal data mutasi sudah terlanjur tersimpan di database!
$entity = Entity::create([...]);
$this->auditService->log(...); // JANGAN LAKUKAN INI DI LUAR TRANSAKSI
```

---

## 3. Kontrak Data `changes` dan Keamanan Allowlist

Kontrak data untuk kolom `changes` menggunakan format JSON terstruktur:

```json
{
  "before": {
    "field_name": "nilai_lama"
  },
  "after": {
    "field_name": "nilai_baru"
  }
}
```

### Aturan Keamanan dan Sanitasi:
1. **Allowlist Per Modul**: Field yang boleh masuk dicatat dalam konstanta `AuditService::ALLOWED_FIELDS[$module]`. Field yang tidak terdaftar akan otomatis dibuang.
2. **Denylist Kata Kunci Sensitif**: Field yang mengandung kata kunci seperti `password`, `token`, `secret`, `cookie`, atau `credential` akan langsung ditolak tanpa kecuali.
3. **Pencegahan Nested Obfuscation**: Nilai field skalar tidak boleh berupa array atau objek. Jika dikirim array/objek pada field skalar, nilai tersebut dibuang agar tidak menyembunyikan payload rahasia di dalam array bertingkat.
4. **Deskripsi dan Label Terpilih**: Kolom `summary` dan `entity_label` harus disusun dari data spesifik yang sudah divalidasi (misal `"Kamar K101"` atau `"Tagihan periode September 2026"`), bukan mencetak mentah string `request()->all()`.

---

## 4. Contoh Pemakaian Nyata pada Service Bisnis

### A. Operasi Pembuatan Data Baru (Create)

```php
namespace App\Services;

use App\Models\Room;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;

class RoomService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    public function createRoom(array $validatedData, User $actor): Room
    {
        return DB::transaction(function () use ($validatedData, $actor) {
            // 1. Mutasi bisnis
            $room = Room::create([
                'number' => $validatedData['number'],
                'type' => $validatedData['type'],
                'monthly_rate' => $validatedData['monthly_rate'],
                'notes' => $validatedData['notes'] ?? null,
            ]);

            // 2. Catat audit dalam transaksi yang sama
            $this->auditService->log(
                action: 'create',
                module: 'rooms',
                summary: "Menambahkan kamar nomor {$room->number} dengan tarif Rp " . number_format($room->monthly_rate, 0, ',', '.'),
                actor: $actor,
                entityType: 'Room',
                entityId: $room->id,
                entityLabel: "Kamar {$room->number}",
                changes: [
                    'after' => [
                        'number' => $room->number,
                        'type' => $room->type,
                        'monthly_rate' => $room->monthly_rate,
                        'notes' => $room->notes,
                    ],
                ]
            );

            return $room;
        });
    }
}
```

### B. Operasi Pembaruan Data (Update) dengan `before` dan `after`

```php
public function updateRoom(Room $room, array $validatedData, User $actor): Room
{
    return DB::transaction(function () use ($room, $validatedData, $actor) {
        $before = [
            'type' => $room->type,
            'monthly_rate' => $room->monthly_rate,
            'notes' => $room->notes,
        ];

        $room->update($validatedData);

        $after = [
            'type' => $room->type,
            'monthly_rate' => $room->monthly_rate,
            'notes' => $room->notes,
        ];

        $this->auditService->log(
            action: 'update',
            module: 'rooms',
            summary: "Mengubah data kamar {$room->number}",
            actor: $actor,
            entityType: 'Room',
            entityId: $room->id,
            entityLabel: "Kamar {$room->number}",
            changes: [
                'before' => $before,
                'after' => $after,
            ]
        );

        return $room;
    });
}
```

### C. Operasi Aksi Sistem (Automated Scheduler / Background Job)

```php
public function autoArchiveEndedPlacements(): int
{
    return DB::transaction(function () {
        $count = Placement::whereNotNull('ended_on')->count();

        // Operasi sistem tanpa User
        $this->auditService->logSystem(
            action: 'archive_batch',
            module: 'placements',
            summary: "Sistem memproses arsip otomatis untuk penempatan selesai",
            systemName: 'Sistem: Placement Cleaner'
        );

        return $count;
    });
}
```

---

## 5. Menampilkan Waktu Audit pada Blade / API

Kolom `occurred_at` tersimpan dalam format UTC. Untuk menampilkan waktu lokal Indonesia (WIB):

```blade
<!-- Menggunakan accessor model ActivityLog -->
<span>Waktu: {{ $log->occurredAtJakarta()->format('d/m/Y H:i:s') }} WIB</span>
```

---

## 6. Status Pengujian Terverifikasi

Pengujian fondasi pada [tests/Feature/AuditServiceTest.php](file:///c:/SEMESTER%205/SISTEM%20INFORMASI%20PRAKTIKUM/TA/tests/Feature/AuditServiceTest.php) (10 tests, 45 assertions, 0 failure) telah membuktikan:
1. **TC-24 (Tingkat Fondasi)**: Identitas aktor, snapshot nama independen, waktu server UTC/Asia/Jakarta, format changes contract, dan penyaringan field sensitif/unknown/nested telah terbukti bekerja akurat.
2. **TC-32 (Tingkat Fondasi)**: Atomisitas rollback terbukti nyata pada tingkat database engine (bukan sekadar mock); kegagalan audit membatalkan mutasi bisnis, dan kegagalan operasi lanjutan membatalkan audit yang baru tersimpan.

*Catatan: Integrasi per modul bisnis akan diuji secara bertahap saat setiap modul bisnis dibangun pada fase berikutnya (T06–T17).*
