<?php

namespace Modules\FPSplanificationstage\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\FPSplanificationstage\Models\Stage;
use Modules\FPSplanificationstage\Models\Marin;

class InstructeurManuelService
{
    /**
     * @param  array<int, int|string>  $stageIds
     */
    public function ajouter(
        ?int $marinId,
        array $stageIds,
        string $role = 'indifferent',
        ?string $commentaire = null,
        ?array $nouveauMarin = null
    ): Marin {
        Gate::authorize(
            'fpsplanificationstage::gerer_le_module'
        );

        return DB::transaction(
            function () use (
                $marinId,
                $stageIds,
                $role,
                $commentaire,
                $nouveauMarin
            ): Marin {
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

                if ($nouveauMarin !== null) {
                    $nouveauMarin = array_map(
                        fn ($value) => is_string($value) ? trim($value) : $value,
                        $nouveauMarin
                    );
                    $nouveauMarin['nid'] = mb_strtoupper($nouveauMarin['nid'] ?? '');

                    $attributes = Validator::make($nouveauMarin, [
                        'nom' => ['required', 'string', 'max:255'],
                        'prenom' => ['required', 'string', 'max:255'],
                        'nid' => ['required', 'string', 'max:15'],
                    ])->validate();

                    if (Marin::withoutGlobalScopes()
                        ->whereRaw('UPPER(TRIM(nid)) = ?', [$attributes['nid']])
                        ->exists()) {
                        throw ValidationException::withMessages([
                            'nid' => 'Ce NID existe déjà dans RH. Sélectionnez le marin existant.',
                        ]);
                    }

                    $instructeur = Marin::create([
                        ...$attributes,
                        'uuid' => (string) Str::uuid(),
                    ]);
                } else {
                    if ($marinId === null) {
                        throw ValidationException::withMessages([
                            'marin_id' => 'Sélectionnez un marin.',
                        ]);
                    }

                    $instructeur = Marin::withoutGlobalScopes()->findOrFail($marinId);
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
