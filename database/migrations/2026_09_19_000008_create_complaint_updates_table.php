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
        Schema::create('complaint_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained('complaints')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('from_status', 30);
            $table->string('to_status', 30);
            $table->text('note');
            $table->timestamp('occurred_at');
            $table->timestamps();
        });

        DB::statement("ALTER TABLE complaint_updates ADD CONSTRAINT chk_complaint_updates_from_status CHECK (from_status IN ('open', 'in_progress', 'resolved', 'closed_without_action'))");
        DB::statement("ALTER TABLE complaint_updates ADD CONSTRAINT chk_complaint_updates_to_status CHECK (to_status IN ('open', 'in_progress', 'resolved', 'closed_without_action'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('complaint_updates');
    }
};
