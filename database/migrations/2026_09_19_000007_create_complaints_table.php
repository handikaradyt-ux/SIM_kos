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
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('placement_id')->constrained('placements')->restrictOnDelete();
            $table->foreignId('facility_id')->nullable()->constrained('facilities')->restrictOnDelete();
            $table->string('subject', 120);
            $table->text('description');
            $table->string('status', 30);
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        DB::statement("ALTER TABLE complaints ADD CONSTRAINT chk_complaints_status CHECK (status IN ('open', 'in_progress', 'resolved', 'closed_without_action'))");
        DB::statement("ALTER TABLE complaints ADD CONSTRAINT chk_complaints_closed_metadata CHECK ((status IN ('resolved', 'closed_without_action') AND closed_at IS NOT NULL) OR (status IN ('open', 'in_progress') AND closed_at IS NULL))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
