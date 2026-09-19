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
        Schema::create('placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resident_id')->constrained('residents')->restrictOnDelete();
            $table->foreignId('room_id')->constrained('rooms')->restrictOnDelete();
            $table->date('started_on');
            $table->date('ended_on')->nullable();
            $table->decimal('agreed_monthly_rate', 12, 0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('ended_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('end_reason', 255)->nullable();

            // Generated columns for active room and active resident constraints
            $table->unsignedBigInteger('active_room_id')
                ->virtualAs("CASE WHEN ended_on IS NULL THEN room_id ELSE NULL END")
                ->nullable();
            $table->unique('active_room_id');

            $table->unsignedBigInteger('active_resident_id')
                ->virtualAs("CASE WHEN ended_on IS NULL THEN resident_id ELSE NULL END")
                ->nullable();
            $table->unique('active_resident_id');

            $table->timestamps();

            $table->index(['resident_id', 'started_on']);
        });

        DB::statement("ALTER TABLE placements ADD CONSTRAINT chk_placements_agreed_rate CHECK (agreed_monthly_rate > 0)");
        DB::statement("ALTER TABLE placements ADD CONSTRAINT chk_placements_date_range CHECK (ended_on IS NULL OR ended_on >= started_on)");
        DB::statement("ALTER TABLE placements ADD CONSTRAINT chk_placements_end_metadata CHECK ((ended_on IS NULL AND ended_by IS NULL AND end_reason IS NULL) OR (ended_on IS NOT NULL AND ended_by IS NOT NULL AND end_reason IS NOT NULL))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('placements');
    }
};
