<?php

namespace Modules\FPSplanificationstage\Filament\Resources\BesoinFormations\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Modules\FPSplanificationstage\Filament\Resources\BesoinFormations\BesoinFormationResource;
use Modules\FPSplanificationstage\Filament\Resources\SessionStages\SessionStageResource;
use Modules\FPSplanificationstage\Services\BesoinFormationPlanner;

class EditBesoinFormation extends EditRecord
{
    protected static string $resource =
        BesoinFormationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make(
                'rechercherPlanification'
            )
                ->label(
                    'Rechercher une planification'
                )
                ->icon(
                    'heroicon-o-calendar-days'
                )
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading(
                    'Rechercher une planification'
                )
                ->modalDescription(
                    'L’application va rechercher un créneau compatible avec le stage, les formateurs, leurs indisponibilités, les salles et les sessions déjà planifiées.'
                )
                ->modalSubmitActionLabel(
                    'Rechercher'
                )
                ->disabled(
                    fn (): bool =>
                        $this->record
                            ->session_stage_id
                        !== null
                        || $this->record
                            ->statut
                        === 'annule'
                )
                ->action(
                    function (): void {
                        $result = app(
                            BesoinFormationPlanner::class
                        )->plan(
                            $this->record
                        );

                        if (
                            $result['success']
                        ) {
                            $session =
                                $result['session'];

                            Notification::make()
                                ->title(
                                    'Session planifiée'
                                )
                                ->body(
                                    $result[
                                        'message'
                                    ]
                                )
                                ->success()
                                ->send();

                            $this->redirect(
                                SessionStageResource::getUrl(
                                    'edit',
                                    [
                                        'record' =>
                                            $session
                                                ->getKey(),
                                    ]
                                )
                            );

                            return;
                        }

                        $body = implode(
                            "\n\n",
                            $result[
                                'conflicts'
                            ] ?? []
                        );

                        if (
                            ! empty(
                                $result[
                                    'suggestions'
                                ]
                            )
                        ) {
                            $body .=
                                "\n\nSolutions possibles :\n"
                                . $result[
                                    'suggestions'
                                ];
                        }

                        Notification::make()
                            ->title(
                                'Aucune planification automatique possible'
                            )
                            ->body($body)
                            ->warning()
                            ->persistent()
                            ->send();

                        $this->record->refresh();
                    }
                ),

            ViewAction::make(),

            DeleteAction::make(),
        ];
    }
}