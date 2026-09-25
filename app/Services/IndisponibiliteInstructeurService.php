<?php

namespace Modules\FPSplanificationstage\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\FPSplanificationstage\Models\IndisponibiliteInstructeur;
use Modules\FPSplanificationstage\Models\SessionStage;
use Modules\RH\Models\Marin;

class IndisponibiliteInstructeurService
{
    public function __construct(
        private readonly IndisponibiliteInstructeurNotifier $notifier
    ) {}

    /**
     * @param array<string, mixed> $data
     * @return array{indisponibilite: IndisponibiliteInstructeur, conflits: Collection<int, SessionStage>}
     */
    public function creer(
        Marin $instructeur,
        array $data
    ): array {
        $data = Validator::make(
            $data,
            [
                'date_debut' => ['required', 'date'],
                'date_fin' => [
                    'required',
                    'date',
                    'after_or_equal:date_debut',
                ],
                'heure_debut' => [
                    'nullable',
                    'date_format:H:i',
                ],
                'heure_fin' => [
                    'nullable',
                    'date_format:H:i',
                ],
                'journee_entiere' => ['required', 'boolean'],
                'motif' => [
                    'nullable',
                    'in:conge,mission,formation,service,absence,autre',
                ],
                'commentaire' => ['nullable', 'string', 'max:2000'],
            ],
            [
                'date_fin.after_or_equal' =>
                    'La fin doit être postérieure au début.',
            ]
        )->validate();

        $journeeEntiere = (bool) $data['journee_entiere'];

        if (! $journeeEntiere) {
            $this->validerHoraires($data);
        }

        $indisponibilite = DB::transaction(
            fn (): IndisponibiliteInstructeur =>
                IndisponibiliteInstructeur::query()->create([
                    'instructeur_id' => $instructeur->getKey(),
                    'date_debut' => $data['date_debut'],
                    'heure_debut' => $journeeEntiere
                        ? null
                        : $data['heure_debut'],
                    'date_fin' => $data['date_fin'],
                    'heure_fin' => $journeeEntiere
                        ? null
                        : $data['heure_fin'],
                    'journee_entiere' => $journeeEntiere,
                    'motif' => $data['motif'] ?? null,
                    'commentaire' => $data['commentaire'] ?? null,
                    'actif' => true,
                ])
        );

        $conflits = $this->sessionsEnConflit(
            $indisponibilite
        );

        $this->notifier->notifier(
            $indisponibilite->load('instructeur'),
            $conflits
        );

        return [
            'indisponibilite' => $indisponibilite,
            'conflits' => $conflits,
        ];
    }

    /** @return Collection<int, SessionStage> */
    public function sessionsEnConflit(
        IndisponibiliteInstructeur $indisponibilite
    ): Collection {
        return SessionStage::query()
            ->with('stage')
            ->whereHas(
                'instructeurs',
                fn ($query) => $query
                    ->withoutGlobalScopes()
                    ->where(
                        'rh_marins.id',
                        $indisponibilite->instructeur_id
                    )
            )
            ->where('statut', '<>', 'annulee')
            ->where(
                'debut',
                '<=',
                $this->fin($indisponibilite)
            )
            ->where(
                'fin',
                '>=',
                $this->debut($indisponibilite)
            )
            ->orderBy('debut')
            ->get();
    }

    public function debut(
        IndisponibiliteInstructeur $indisponibilite
    ): Carbon {
        return $indisponibilite->date_debut
            ->copy()
            ->setTimeFromTimeString(
                $indisponibilite->journee_entiere
                    ? '00:00:00'
                    : (string) $indisponibilite->heure_debut
            );
    }

    public function fin(
        IndisponibiliteInstructeur $indisponibilite
    ): Carbon {
        return $indisponibilite->date_fin
            ->copy()
            ->setTimeFromTimeString(
                $indisponibilite->journee_entiere
                    ? '23:59:59'
                    : (string) $indisponibilite->heure_fin
            );
    }

    /** @param array<string, mixed> $data */
    private function validerHoraires(
        array $data
    ): void {
        if (
            blank($data['heure_debut'] ?? null)
            || blank($data['heure_fin'] ?? null)
        ) {
            throw ValidationException::withMessages([
                'heure_debut' =>
                    'Les heures sont obligatoires pour une indisponibilité partielle.',
            ]);
        }

        $debut = Carbon::parse(
            $data['date_debut']
            . ' '
            . $data['heure_debut']
        );

        $fin = Carbon::parse(
            $data['date_fin']
            . ' '
            . $data['heure_fin']
        );

        if ($fin->lessThanOrEqualTo($debut)) {
            throw ValidationException::withMessages([
                'heure_fin' =>
                    'La fin doit être postérieure au début.',
            ]);
        }
    }
}
