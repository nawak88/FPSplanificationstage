<?php

namespace Modules\FPSplanificationstage\Filament\Resources\Stages\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\FPSplanificationstage\Filament\Resources\Stages\StageResource;

class EditStage extends EditRecord
{
    protected static string $resource =
        StageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('supprimerStage')
                ->label('Supprimer')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(
                    'Supprimer définitivement ce stage ?'
                )
                ->modalDescription(
                    fn (): string =>
                        $this->getDeleteDescription()
                )
                ->modalSubmitActionLabel(
                    'Oui, tout supprimer'
                )
                ->modalCancelActionLabel(
                    'Annuler'
                )
                ->action(function (): void {
                    $stage = $this->getRecord();

                    $result = DB::transaction(
                        function () use ($stage): array {
                            $sessionIds =
                                DB::table('session_stages')
                                    ->where(
                                        'stage_id',
                                        $stage->getKey()
                                    )
                                    ->pluck('id');

                            $inscriptionIds =
                                $sessionIds->isEmpty()
                                    ? collect()
                                    : DB::table('inscriptions')
                                        ->whereIn(
                                            'session_stage_id',
                                            $sessionIds
                                        )
                                        ->pluck('id');

                            $nbSessions =
                                $sessionIds->count();

                            $nbInscriptions =
                                $inscriptionIds->count();

                            /*
                             * On supprime d'abord les réponses
                             * aux prérequis pour ne jamais être
                             * bloqué par une clé étrangère.
                             */
                            if (
                                $inscriptionIds->isNotEmpty()
                                && Schema::hasTable(
                                    'inscription_prerequis'
                                )
                            ) {
                                DB::table(
                                    'inscription_prerequis'
                                )
                                    ->whereIn(
                                        'inscription_id',
                                        $inscriptionIds
                                    )
                                    ->delete();
                            }

                            /*
                             * Puis les inscriptions elles-mêmes.
                             */
                            if ($sessionIds->isNotEmpty()) {
                                DB::table('inscriptions')
                                    ->whereIn(
                                        'session_stage_id',
                                        $sessionIds
                                    )
                                    ->delete();
                            }

                            /*
                             * On détache explicitement les
                             * formateurs des sessions si la
                             * table pivot existe.
                             */
                            if (
                                $sessionIds->isNotEmpty()
                                && Schema::hasTable(
                                    'instructeur_session_stage'
                                )
                            ) {
                                DB::table(
                                    'instructeur_session_stage'
                                )
                                    ->whereIn(
                                        'session_stage_id',
                                        $sessionIds
                                    )
                                    ->delete();
                            }

                            /*
                             * Les besoins liés à une session
                             * utilisent normalement nullOnDelete.
                             * On met tout de même la référence
                             * à NULL explicitement avant de
                             * supprimer les sessions.
                             */
                            if (
                                $sessionIds->isNotEmpty()
                                && Schema::hasTable(
                                    'besoin_formations'
                                )
                            ) {
                                DB::table('besoin_formations')
                                    ->whereIn(
                                        'session_stage_id',
                                        $sessionIds
                                    )
                                    ->update([
                                        'session_stage_id' => null,
                                    ]);
                            }

                            /*
                             * Suppression des sessions.
                             */
                            if ($sessionIds->isNotEmpty()) {
                                DB::table('session_stages')
                                    ->whereIn(
                                        'id',
                                        $sessionIds
                                    )
                                    ->delete();
                            }

                            /*
                             * Suppression finale du stage.
                             * Les relations configurées en
                             * cascade (prérequis, besoins,
                             * modules FIF, pivots, etc.) suivent
                             * alors les règles de la base.
                             */
                            $stage->delete();

                            return [
                                'sessions' =>
                                    $nbSessions,
                                'inscriptions' =>
                                    $nbInscriptions,
                            ];
                        }
                    );

                    Notification::make()
                        ->title('Stage supprimé')
                        ->body(
                            'Le stage, ' .
                            $result['sessions'] .
                            ' session(s) et ' .
                            $result['inscriptions'] .
                            ' inscription(s) ont été supprimés.'
                        )
                        ->success()
                        ->send();

                    $this->redirect(
                        StageResource::getUrl('index')
                    );
                }),
        ];
    }

    private function getDeleteDescription(): string
    {
        $stage = $this->getRecord();

        $sessionIds =
            DB::table('session_stages')
                ->where(
                    'stage_id',
                    $stage->getKey()
                )
                ->pluck('id');

        $nbSessions =
            $sessionIds->count();

        $nbInscriptions =
            $sessionIds->isEmpty()
                ? 0
                : DB::table('inscriptions')
                    ->whereIn(
                        'session_stage_id',
                        $sessionIds
                    )
                    ->count();

        return
            'Ce stage contient actuellement ' .
            $nbSessions .
            ' session(s) et ' .
            $nbInscriptions .
            ' inscription(s). ' .
            'Si vous confirmez, le stage, toutes ses sessions ' .
            'et tous les stagiaires inscrits à ces sessions ' .
            'seront définitivement supprimés. ' .
            'Cette action est irréversible.';
    }
}
