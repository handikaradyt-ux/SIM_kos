<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->string('receipt_number', 40)->unique();
            $table->decimal('amount', 12, 0);
            $table->date('paid_on');
            $table->string('method', 20);
            $table->string('reference', 100)->nullable();
            $table->string('status', 10);
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 255)->nullable();

            // Generated column for only one valid payment per invoice
            $table->unsignedBigInteger('valid_invoice_id')
                ->virtualAs("CASE WHEN status = 'valid' THEN invoice_id ELSE NULL END")
                ->nullable();
            $table->unique('valid_invoice_id');

            $table->timestamps();

            $table->index(['paid_on', 'status']);
        });

        DB::statement("ALTER TABLE payments ADD CONSTRAINT chk_payments_amount CHECK (amount > 0)");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT chk_payments_status CHECK (status IN ('valid', 'void'))");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT chk_payments_method CHECK (method IN ('cash', 'transfer'))");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT chk_payments_void_metadata CHECK ((status = 'valid' AND voided_by IS NULL AND voided_at IS NULL AND void_reason IS NULL) OR (status = 'void' AND voided_by IS NOT NULL AND voided_at IS NOT NULL AND void_reason IS NOT NULL))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
