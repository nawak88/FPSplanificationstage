<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructeur_stage', function (Blueprint $table) {
            $table->id();

            $table->foreignId('instructeur_id')
                ->constrained('instructeurs')
                ->cascadeOnDelete();

            $table->foreignId('stage_id')
                ->constrained('stages')
                ->cascadeOnDelete();

            // principal / suppleant / indifferent
            $table->string('role')
                ->default('indifferent');

            $table->boolean('actif')
                ->default(true);

            $table->text('commentaire')
                ->nullable();

            // Permettra de différencier une association saisie à la main
            // d'une association provenant de l'Excel.
            $table->string('source')
                ->default('manuel')
                ->index();

            $table->timestamp('dernier_import_at')
                ->nullable();

            $table->timestamps();

            // Un formateur ne doit apparaître qu'une fois
            // pour un même stage.
            $table->unique(
                ['instructeur_id', 'stage_id'],
                'instructeur_stage_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructeur_stage');
    }
};