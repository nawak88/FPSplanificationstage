<?php

namespace Modules\FPSplanificationstage\Filament\Resources\Instructeurs\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Modules\FPSplanificationstage\Filament\Resources\Instructeurs\InstructeurResource;
use Modules\FPSplanificationstage\Models\Stage;
use Modules\FPSplanificationstage\Services\InstructeurManuelService;
use Modules\FPSplanificationstage\Services\InstructeurStageImporter;
use Modules\RH\Models\Marin;
use Throwable;

class ListInstructeurs extends ListRecords
{
    protected static string $resource = InstructeurResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajouterInstructeur')
                ->label('Ajouter un instructeur')
                ->icon('heroicon-o-user-plus')
                ->color('success')
                ->authorize(
                    fn (): bool =>
                        auth()->user()?->can(
                            'fpsplanificationstage::gerer_le_module'
                        ) ?? false
                )
                ->modalHeading('Ajouter un instructeur manuellement')
                ->modalDescription(
                    'Sélectionnez un marin existant dans RH et les stages qu’il peut encadrer.'
                )
                ->modalSubmitActionLabel('Ajouter')
                ->schema([
                    Select::make('marin_id')
                        ->label('Marin')
                        ->options(
                            fn (): array => Marin::withoutGlobalScopes()
                                ->orderBy('nom')
                                ->orderBy('prenom')
                                ->get()
                                ->mapWithKeys(
                                    fn (Marin $marin): array => [
                                        $marin->getKey() => trim(
                                            mb_strtoupper($marin->nom)
                                            . ' '
                                            . $marin->prenom
                                        )
                                        . ($marin->matricule
                                            ? ' — ' . $marin->matricule
                                            : '')
                                        . ($marin->nid
                                            ? ' — NID ' . $marin->nid
                                            : ''),
                                    ]
                                )
                                ->all()
                        )
                        ->searchable()
                        ->preload()
                        ->required(),
                    Select::make('stage_ids')
                        ->label('Stages enseignés')
                        ->options(
                            fn (): array => Stage::query()
                                ->where('actif', true)
                                ->orderBy('libelle_court')
                                ->get()
                                ->mapWithKeys(
                                    fn (Stage $stage): array => [
                                        $stage->getKey() =>
                                            ($stage->code_stage
                                                ? $stage->code_stage . ' — '
                                                : '')
                                            . $stage->libelle_court,
                                    ]
                                )
                                ->all()
                        )
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->required(),
                    Select::make('role')
                        ->label('Rôle')
                        ->options([
                            'principal' => 'Principal',
                            'suppleant' => 'Suppléant',
                            'indifferent' => 'Indifférent',
                        ])
                        ->default('indifferent')
                        ->required(),
                    Textarea::make('commentaire')
                        ->label('Commentaire')
                        ->rows(3),
                ])
                ->action(function (array $data): void {
                    $instructeur = app(
                        InstructeurManuelService::class
                    )->ajouter(
                        (int) $data['marin_id'],
                        $data['stage_ids'],
                        $data['role'],
                        $data['commentaire'] ?? null
                    );

                    $this->resetTable();

                    Notification::make()
                        ->title('Instructeur ajouté')
                        ->body(
                            trim(
                                mb_strtoupper($instructeur->nom)
                                . ' '
                                . $instructeur->prenom
                            )
                            . ' est maintenant disponible dans l’onglet Instructeurs.'
                        )
                        ->success()
                        ->send();
                }),
            Action::make('importInstructeurs')
                ->label('Importer Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->modalHeading('Importer les instructeurs et leurs stages')
                ->modalDescription(
                    'Le fichier doit contenir les onglets "Instructeurs" et "Stages délivrés".'
                )
                ->modalSubmitActionLabel('Importer')
                ->schema([
                    FileUpload::make('fichier')
                        ->label('Fichier Excel')
                        ->disk('local')
                        ->directory('imports/instructeurs')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                        ])
                        ->maxSize(20480)
                        ->required()
                        ->helperText(
                            'Formats acceptés : .xlsx ou .xls — 20 Mo maximum.'
                        ),
                ])
                ->action(function (array $data): void {
                    $uploadedPath = $data['fichier'] ?? null;

                    // Sécurité au cas où Filament renverrait un tableau.
                    if (is_array($uploadedPath)) {
                        $uploadedPath = $uploadedPath[0] ?? null;
                    }

                    if (! is_string($uploadedPath) || $uploadedPath === '') {
                        Notification::make()
                            ->title('Import impossible')
                            ->body('Aucun fichier valide n’a été sélectionné.')
                            ->danger()
                            ->send();

                        return;
                    }

                    try {
                        $absolutePath = Storage::disk('local')
                            ->path($uploadedPath);

                        $result = app(
                            InstructeurStageImporter::class
                        )->import($absolutePath);

                        $this->resetTable();

                        $resume =
                            "Instructeurs : " .
                            "{$result['instructeurs_crees']} créés • " .
                            "{$result['instructeurs_mis_a_jour']} mis à jour • " .
                            "{$result['instructeurs_inchanges']} inchangés" .
                            "\n" .
                            "Associations stages : " .
                            "{$result['associations_creees']} créées • " .
                            "{$result['associations_mises_a_jour']} mises à jour • " .
                            "{$result['associations_inchangees']} inchangées";

                        if (count($result['erreurs']) > 0) {
                            $details = implode(
                                ' | ',
                                array_slice(
                                    $result['erreurs'],
                                    0,
                                    5
                                )
                            );

                            Notification::make()
                                ->title('Import terminé avec avertissements')
                                ->body(
                                    $resume .
                                    "\n" .
                                    $details
                                )
                                ->warning()
                                ->persistent()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Import terminé')
                            ->body($resume)
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Erreur pendant l’import')
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    } finally {
                        Storage::disk('local')
                            ->delete($uploadedPath);
                    }
                }),
        ];
    }
}
