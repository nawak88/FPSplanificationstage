<?php

namespace Modules\FPSplanificationstage\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\FPSplanificationstage\Models\Stage;
use Modules\RH\Models\Marin;

class InstructeurManuelService
{
    /**
     * @param  array<int, int|string>  $stageIds
     */
    public function ajouter(
        int $marinId,
        array $stageIds,
        string $role = 'indifferent',
        ?string $commentaire = null
    ): Marin {
        Gate::authorize(
            'fpsplanificationstage::gerer_le_module'
        );

        return DB::transaction(
            function () use (
                $marinId,
                $stageIds,
                $role,
                $commentaire
            ): Marin {
                $instructeur = Marin::withoutGlobalScopes()
                    ->findOrFail($marinId);

                $stageIds = collect($stageIds)
                    ->map(
                        fn (int|string $stageId): int =>
                            (int) $stageId
                    )
                    ->unique()
                    ->values();

                if ($stageIds->isEmpty()) {
                    throw ValidationException::withMessages([
                        'stage_ids' =>
                            'Sélectionnez au moins un stage.',
                    ]);
                }

                if (! in_array(
                    $role,
                    ['principal', 'suppleant', 'indifferent'],
                    true
                )) {
                    throw ValidationException::withMessages([
                        'role' => 'Le rôle sélectionné est invalide.',
                    ]);
                }

                $stages = Stage::query()
                    ->whereKey($stageIds)
                    ->get();

                if ($stages->count() !== $stageIds->count()) {
                    throw ValidationException::withMessages([
                        'stage_ids' =>
                            'Un des stages sélectionnés est introuvable.',
                    ]);
                }

                foreach ($stages as $stage) {
                    $stage->instructeurs()
                        ->syncWithoutDetaching([
                            $instructeur->getKey() => [
                                'role' => $role,
                                'actif' => true,
                                'commentaire' =>
                                    filled($commentaire)
                                        ? trim($commentaire)
                                        : null,
                                'source' => 'manuel',
                                'dernier_import_at' => null,
                            ],
                        ]);
                }

                return $instructeur;
            }
        );
    }
}
