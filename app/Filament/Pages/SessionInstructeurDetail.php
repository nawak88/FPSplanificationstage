<?php

namespace Modules\FPSplanificationstage\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Validation\ValidationException;
use Modules\FPSplanificationstage\Services\CompteRenduFinStageService;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Livewire\Attributes\Locked;
use Modules\FPSplanificationstage\Services\SessionInstructeurPageService;

class SessionInstructeurDetail extends Page
{
    protected static ?string $slug = 'espace-instructeur/sessions/{session}';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'fpsplanificationstage::filament.session-instructeur-detail';

    #[Locked]
    public int|string $sessionId;

    public static function canAccess(): bool
    {
        return EspaceInstructeur::canAccess();
    }

    public function mount(int|string $session): void
    {
        $this->sessionId = $session;
    }

    public function getTitle(): string
    {
        return 'Stagiaires de ma session';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('compteRendu')
                ->label('Télécharger le compte rendu PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn (): bool => $this->peutSaisirResultats())
                ->action(function (): ?StreamedResponse {
                    try {
                        $pdf = app(CompteRenduFinStageService::class)->render($this->sessionId);
                    } catch (ValidationException $exception) {
                        Notification::make()->warning()
                            ->title('Compte rendu incomplet')
                            ->body($exception->validator->errors()->first())
                            ->send();

                        return null;
                    }

                    return response()->streamDownload(
                        function () use ($pdf): void { echo $pdf; },
                        'compte-rendu-fin-stage-' . $this->sessionId . '.pdf',
                        ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store']
                    );
                }),
        ];
    }

    public function peutSaisirResultats(): bool
    {
        $service = app(CompteRenduFinStageService::class);

        return $service->peutSaisir($service->session($this->sessionId));
    }

    public function resultatAction(): Action
    {
        return Action::make('resultat')
            ->label('Saisir le résultat')
            ->modalHeading('Résultat de fin de stage')
            ->visible(fn (): bool => $this->peutSaisirResultats())
            ->fillForm(function (array $arguments): array {
                $inscription = app(CompteRenduFinStageService::class)->inscription(
                    $this->sessionId, $arguments['inscription']
                );

                return [
                    'note_fin_stage' => $inscription->note_fin_stage,
                    'stage_valide' => $inscription->stage_valide === null
                        ? null : (int) $inscription->stage_valide,
                    'date_attribution' => $inscription->date_attribution?->toDateString()
                        ?? $inscription->sessionStage->fin->toDateString(),
                    'observations_fin_stage' => $inscription->observations_fin_stage,
                ];
            })
            ->schema([
                TextInput::make('note_fin_stage')
                    ->label('Note sur 20')
                    ->numeric()->minValue(0)->maxValue(20)->step(0.01)
                    ->rules(['decimal:0,2'])->required()->live(onBlur: true)
                    ->helperText('À partir de 12/20, le stage est automatiquement validé et la note est masquée dans le PDF.'),
                Select::make('stage_valide')
                    ->label('Validation du stage')
                    ->options([1 => 'Validé', 0 => 'Non validé'])
                    ->visible(fn (Get $get): bool => $get('note_fin_stage') !== null
                        && $get('note_fin_stage') !== ''
                        && (float) $get('note_fin_stage') < CompteRenduFinStageService::SEUIL_VALIDATION)
                    ->required(fn (Get $get): bool => (float) $get('note_fin_stage') < CompteRenduFinStageService::SEUIL_VALIDATION),
                DatePicker::make('date_attribution')
                    ->label('Date d’attribution')->required()
                    ->maxDate(today())->displayFormat('d/m/Y'),
                Textarea::make('observations_fin_stage')
                    ->label('Observations')->maxLength(2000)->rows(3),
            ])
            ->action(function (array $data, array $arguments): void {
                app(CompteRenduFinStageService::class)->enregistrer(
                    $this->sessionId, $arguments['inscription'], $data
                );
                Notification::make()->success()->title('Résultat enregistré')->send();
            });
    }

    protected function getViewData(): array
    {
        $data = app(SessionInstructeurPageService::class)->detail($this->sessionId);
        $data['peutSaisirResultats'] = app(CompteRenduFinStageService::class)->peutSaisir($data['session']);

        return $data;
    }
}
