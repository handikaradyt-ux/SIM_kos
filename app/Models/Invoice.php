<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'placement_id',
        'period_month',
        'due_on',
        'amount',
        'resident_name_snapshot',
        'room_number_snapshot',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'period_month' => 'date',
            'due_on' => 'date',
            'amount' => 'integer',
        ];
    }

    public function placement(): BelongsTo
    {
        return $this->belongsTo(Placement::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function validPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->where('status', 'valid');
    }

    /**
     * Check if this invoice has an associated valid payment.
     */
    public function hasValidPayment(): bool
    {
        if ($this->relationLoaded('validPayment')) {
            return $this->validPayment !== null;
        }

        return $this->validPayment()->exists();
    }

    /**
     * Parse any date attribute from model or string into Carbon with Asia/Jakarta timezone.
     * Extracts date string strictly without mutating or altering input Carbon instances.
     */
    public static function parseNormalizedDate(mixed $date): Carbon
    {
        if ($date instanceof CarbonInterface) {
            return Carbon::createFromFormat('Y-m-d', $date->format('Y-m-d'), 'Asia/Jakarta')->startOfDay();
        }

        $str = substr(trim((string) $date), 0, 10);

        return Carbon::createFromFormat('Y-m-d', $str, 'Asia/Jakarta')->startOfDay();
    }

    /**
     * Check if this invoice is fully paid.
     */
    public function isPaid(): bool
    {
        return $this->hasValidPayment();
    }

    /**
     * Check if this invoice is overdue (unpaid and due_on < reference business date).
     * Note: Invoices due today are NOT considered overdue.
     */
    public function isOverdue(?CarbonInterface $asOfDate = null): bool
    {
        if ($this->isPaid()) {
            return false;
        }

        $ref = static::parseNormalizedDate($asOfDate ?? Carbon::now('Asia/Jakarta'));
        $due = static::parseNormalizedDate($this->due_on);

        return $due->lessThan($ref);
    }

    /**
     * Check if this invoice is unpaid and due today.
     */
    public function isDueToday(?CarbonInterface $asOfDate = null): bool
    {
        if ($this->isPaid()) {
            return false;
        }

        $ref = static::parseNormalizedDate($asOfDate ?? Carbon::now('Asia/Jakarta'));
        $due = static::parseNormalizedDate($this->due_on);

        return $due->equalTo($ref);
    }

    /**
     * Check if this invoice is unpaid and due in the future.
     */
    public function isDueFuture(?CarbonInterface $asOfDate = null): bool
    {
        if ($this->isPaid()) {
            return false;
        }

        $ref = static::parseNormalizedDate($asOfDate ?? Carbon::now('Asia/Jakarta'));
        $due = static::parseNormalizedDate($this->due_on);

        return $due->greaterThan($ref);
    }

    /**
     * Get the human-readable status label.
     */
    public function statusLabel(?CarbonInterface $asOfDate = null): string
    {
        if ($this->isPaid()) {
            return 'Lunas';
        }

        if ($this->isOverdue($asOfDate)) {
            return 'Terlambat';
        }

        if ($this->isDueToday($asOfDate)) {
            return 'Jatuh Tempo Hari Ini';
        }

        return 'Belum Jatuh Tempo';
    }

    /**
     * Get the badge CSS class for this invoice's status.
     */
    public function statusBadgeClass(?CarbonInterface $asOfDate = null): string
    {
        if ($this->isPaid()) {
            return 'bg-success';
        }

        if ($this->isOverdue($asOfDate)) {
            return 'bg-danger';
        }

        if ($this->isDueToday($asOfDate)) {
            return 'bg-warning text-dark';
        }

        return 'bg-secondary';
    }

    /**
     * Query scope: search by resident name, room number, or period month.
     */
    public function scopeSearch($query, ?string $keyword)
    {
        if ($keyword !== null && trim($keyword) !== '') {
            $trimmed = trim($keyword);

            return $query->where(function ($q) use ($trimmed) {
                $q->where('resident_name_snapshot', 'like', "%{$trimmed}%")
                    ->orWhere('room_number_snapshot', 'like', "%{$trimmed}%")
                    ->orWhere('period_month', 'like', "%{$trimmed}%");
            });
        }

        return $query;
    }

    /**
     * Query scope: filter by payment status (paid, unpaid, overdue).
     * Unpaid includes all unpaid invoices (overdue, due today, and future).
     * Overdue is a subset of unpaid with due_on < reference business date.
     */
    public function scopeStatus($query, ?string $status, ?CarbonInterface $asOfDate = null)
    {
        if (empty($status) || $status === 'all') {
            return $query;
        }

        $todayDate = static::parseNormalizedDate($asOfDate ?? Carbon::now('Asia/Jakarta'))->toDateString();

        if ($status === 'paid') {
            return $query->whereHas('validPayment');
        }

        if ($status === 'unpaid') {
            return $query->whereDoesntHave('validPayment');
        }

        if ($status === 'overdue') {
            return $query->whereDoesntHave('validPayment')
                ->where('due_on', '<', $todayDate);
        }

        return $query;
    }

    /**
     * Query scope: filter by period month (YYYY-MM).
     */
    public function scopePeriod($query, ?string $period)
    {
        if ($period !== null && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', trim($period))) {
            $trimmed = trim($period) . '-01';

            return $query->where('period_month', $trimmed);
        }

        return $query;
    }

    /**
     * Query scope: filter by placement status (active, ended).
     */
    public function scopePlacementStatus($query, ?string $placementStatus)
    {
        if ($placementStatus === 'active') {
            return $query->whereHas('placement', fn ($q) => $q->whereNull('ended_on'));
        }

        if ($placementStatus === 'ended') {
            return $query->whereHas('placement', fn ($q) => $q->whereNotNull('ended_on'));
        }

        return $query;
    }
}
