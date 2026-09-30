<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'inscriptions',
            function (Blueprint $table): void {
                $table
                    ->string('motif_inscription', 50)
                    ->default('sans_objet')
                    ->after('stage_deja_effectue')
                    ->index();
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'inscriptions',
            function (Blueprint $table): void {
                $table->dropIndex([
                    'motif_inscription',
                ]);

                $table->dropColumn(
                    'motif_inscription'
                );
            }
        );
    }
};
