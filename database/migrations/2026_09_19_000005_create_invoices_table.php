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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('placement_id')->constrained('placements')->restrictOnDelete();
            $table->date('period_month');
            $table->date('due_on');
            $table->decimal('amount', 12, 0);
            $table->string('resident_name_snapshot', 100);
            $table->string('room_number_snapshot', 20);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['placement_id', 'period_month']);
            $table->index(['period_month', 'due_on']);
        });

        DB::statement("ALTER TABLE invoices ADD CONSTRAINT chk_invoices_amount CHECK (amount > 0)");
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT chk_invoices_period_month CHECK (DAY(period_month) = 1)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
