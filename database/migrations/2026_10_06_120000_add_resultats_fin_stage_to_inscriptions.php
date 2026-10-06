<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inscriptions', function (Blueprint $table): void {
            $table->decimal('note_fin_stage', 4, 2)->nullable();
            $table->boolean('stage_valide')->nullable();
            $table->date('date_attribution')->nullable();
            $table->text('observations_fin_stage')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('inscriptions', function (Blueprint $table): void {
            $table->dropColumn(['note_fin_stage', 'stage_valide', 'date_attribution', 'observations_fin_stage']);
        });
    }
};
