<?php

namespace Modules\FPSplanificationstage\Filament\Resources\Inscriptions\Pages;

use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Modules\FPSplanificationstage\Filament\Resources\Inscriptions\InscriptionResource;
use Modules\FPSplanificationstage\Models\SessionStage;

class CreateInscription extends CreateRecord
{
    protected static string $resource =
        InscriptionResource::class;

    public function mount(): void
    {
        parent::mount();

        $sessionId =
            request()->query(
                'session_stage_id'
            );

        if (! $sessionId) {
            return;
        }

        $session =
            SessionStage::find(
                $sessionId
            );

        if (! $session) {
            return;
        }

        /*
         * Arrivée depuis la fiche
         * d'une session :
         * on présélectionne celle-ci.
         */
        $this->form->fill([
            'session_stage_id' =>
                $session->id,

            'statut' =>
                null,

            'nemo_recu' =>
                false,

            'derogation_demandee' =>
                false,

            'motif_inscription' =>
                'sans_objet',

            'source' =>
                'manuel',
        ]);
    }

    protected function afterCreate(): void
    {
        if (
            $this->record
                ->stage_deja_effectue
        ) {
            Notification::make()
                ->title(
                    'Stage déjà effectué'
                )
                ->body(
                    'Ce marin a déjà participé à ce stage. Cette nouvelle candidature est signalée comme non prioritaire.'
                )
                ->warning()
                ->persistent()
                ->send();
        }

        if (
            $this->record
                ->statut
            === 'liste_attente'
        ) {
            Notification::make()
                ->title(
                    'Session complète'
                )
                ->body(
                    'L’inscription a été enregistrée en liste d’attente car toutes les places sont déjà réservées.'
                )
                ->warning()
                ->persistent()
                ->send();

            return;
        }

        $session =
            $this->record
                ->sessionStage;

        $message =
            $this->record
                ->code_inscription
            . ' a été créée.';

        if ($session) {
            if (
                $session
                    ->capacite_max
                !== null
            ) {
                $message .=
                    ' Il reste '
                    . $session
                        ->places_restantes
                    . ' place(s) sur '
                    . $session
                        ->code_session
                    . '.';
            }
        }

        Notification::make()
            ->title(
                'Inscription enregistrée'
            )
            ->body(
                $message
            )
            ->success()
            ->send();
    }
}
