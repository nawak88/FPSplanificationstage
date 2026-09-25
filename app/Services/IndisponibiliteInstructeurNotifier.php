<?php

namespace Modules\FPSplanificationstage\Services;

use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Modules\FPSplanificationstage\Models\IndisponibiliteInstructeur;

class IndisponibiliteInstructeurNotifier
{
    /**
     * @param Collection<int, \Modules\FPSplanificationstage\Models\SessionStage> $conflits
     */
    public function notifier(
        IndisponibiliteInstructeur $indisponibilite,
        Collection $conflits
    ): void {
        $gestionnaires = User::query()
            ->where(function ($query): void {
                $query
                    ->where('admin', true)
                    ->orWhereHas(
                        'permissions',
                        fn ($permissionQuery) =>
                            $permissionQuery->where(
                                'name',
                                'fpsplanificationstage::gerer_le_module'
                            )
                    )
                    ->orWhereHas(
                        'roles.permissions',
                        fn ($permissionQuery) =>
                            $permissionQuery->where(
                                'name',
                                'fpsplanificationstage::gerer_le_module'
                            )
                    );
            })
            ->get();

        if ($gestionnaires->isEmpty()) {
            return;
        }

        $instructeur = $indisponibilite->instructeur;
        $nom = trim(
            mb_strtoupper($instructeur?->nom ?? '')
            . ' '
            . ($instructeur?->prenom ?? '')
        );

        $periode = $indisponibilite->date_debut->format('d/m/Y');

        if (! $indisponibilite->journee_entiere) {
            $periode .= ' à '
                . mb_substr(
                    (string) $indisponibilite->heure_debut,
                    0,
                    5
                );
        }

        if (
            ! $indisponibilite->date_debut->isSameDay(
                $indisponibilite->date_fin
            )
            || ! $indisponibilite->journee_entiere
        ) {
            $periode .= ' – '
                . $indisponibilite->date_fin->format('d/m/Y');

            if (! $indisponibilite->journee_entiere) {
                $periode .= ' à '
                    . mb_substr(
                        (string) $indisponibilite->heure_fin,
                        0,
                        5
                    );
            }
        }

        $details = $conflits->isEmpty()
            ? 'Aucune session planifiée ne chevauche cette période.'
            : 'Conflit avec '
                . $conflits->count()
                . ' session(s) : '
                . $conflits
                    ->map(
                        fn ($session): string =>
                            $session->code_session
                            . ' — '
                            . (
                                $session->stage?->libelle_court
                                ?? 'Stage'
                            )
                    )
                    ->implode(', ')
                . '.';

        Notification::make()
            ->title('Nouvelle indisponibilité instructeur')
            ->body(
                $nom
                . ' a déclaré une indisponibilité du '
                . $periode
                . '. '
                . $details
            )
            ->warning()
            ->sendToDatabase($gestionnaires);
    }
}
