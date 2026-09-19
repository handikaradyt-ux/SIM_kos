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
        Schema::create('facilities', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->string('location_type', 20);
            $table->foreignId('room_id')->nullable()->constrained('rooms')->restrictOnDelete();
            $table->string('area_name', 100)->nullable();
            $table->string('condition', 20);
            $table->text('notes')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE `facilities` ADD CONSTRAINT `chk_facilities_location_type` CHECK (`location_type` IN ('room', 'shared'))");
        DB::statement("ALTER TABLE `facilities` ADD CONSTRAINT `chk_facilities_condition` CHECK (`condition` IN ('good', 'broken', 'repairing'))");
        DB::statement("ALTER TABLE `facilities` ADD CONSTRAINT `chk_facilities_location_rule` CHECK ((`location_type` = 'room' AND `room_id` IS NOT NULL AND `area_name` IS NULL) OR (`location_type` = 'shared' AND `room_id` IS NULL AND `area_name` IS NOT NULL))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facilities');
    }
};
