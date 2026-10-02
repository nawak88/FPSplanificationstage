<?php

namespace Modules\FPSplanificationstage\Filament\Pages;

use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Modules\FPSplanificationstage\Models\AdmissionMessageTemplate;
use Modules\FPSplanificationstage\Models\Inscription;
use Modules\FPSplanificationstage\Models\SessionStage;
use Modules\FPSplanificationstage\Models\Stage;

class Admission extends Page
{

    protected static ?string $navigationLabel = 'Admission';
    protected static string|\UnitEnum|null $navigationGroup = 'Inscriptions / Admission';
    protected static ?int $navigationSort = 20;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope-open';

    public ?int $sessionId = null;
    public ?int $templateId = null;
    public ?int $templateStageId = 0;
    public string $templateName = '';
    public string $subjectTemplate = '';
    public string $bodyTemplate = '';
    public string $admittedFormat = '{grade} {nom} {prenom} — {unite}';
    public string $refusedFormat = '{grade} {nom} {prenom} — {unite}';
    public string $finalSubject = '';
    public string $finalBody = '';
    public string $generationWarning = '';

    /** @var array<int|string, string> */
    public array $candidateDecisions = [];

    public static function canAccess(): bool
    {
        return Auth::check()
            && auth()->user()->can("fpsplanificationstage::gerer_le_module")
            && parent::canAccess();
    }

    public function getTitle(): string
    {
        return 'Admission';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newTemplate')
                ->label('Nouveau modèle')
                ->icon('heroicon-o-plus')
                ->color('gray')
                ->action(fn () => $this->newTemplate()),
            Action::make('loadTemplate')
                ->label('Recharger')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn (): bool => (bool) $this->templateId)
                ->action(fn () => $this->loadTemplate()),
            Action::make('duplicateTemplate')
                ->label('Dupliquer')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->visible(fn (): bool => (bool) $this->templateId)
                ->action(fn () => $this->duplicateTemplate()),
            Action::make('generateMessage')
                ->label('Générer le message')
                ->icon('heroicon-o-sparkles')
                ->modalHeading('Aperçu du message')
                ->modalDescription(
                    'Cet aperçu utilise la session et les décisions actuellement sélectionnées.'
                )
                ->modalIcon('heroicon-o-sparkles')
                ->modalWidth('7xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fermer')
                ->mountUsing(function (Schema $schema): void {
                    $this->generateMessage(false);

                    $schema->fill([
                        'previewSubject' => $this->finalSubject,
                        'previewBody' => $this->finalBody,
                    ]);
                })
                ->schema($this->messagePreviewSchema()),
            Action::make('saveTemplate')
                ->label(
                    fn (): string => $this->templateId
                        ? 'Enregistrer les modifications'
                        : 'Créer le modèle'
                )
                ->icon('heroicon-o-check')
                ->action(fn () => $this->saveTemplate()),
            Action::make('deleteTemplate')
                ->label('Supprimer le modèle')
                ->color('danger')
                ->visible(fn (): bool => (bool) $this->templateId)
                ->requiresConfirmation()
                ->modalDescription('Supprimer définitivement ce modèle ?')
                ->action(fn () => $this->deleteTemplate()),
            Action::make('automaticFieldsHelp')
                ->label('Aide sur les champs automatiques')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->iconButton()
                ->tooltip('Aide sur les champs automatiques')
                ->modal()
                ->modalHeading('Champs automatiques')
                ->modalDescription(
                    'Insérez les codes entre accolades dans le modèle. Ils seront remplacés lors de la génération du message.'
                )
                ->modalIcon('heroicon-o-question-mark-circle')
                ->modalWidth('5xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fermer')
                ->schema($this->automaticFieldsHelpSchema()),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Sélection')
                ->schema([
                    Grid::make(['lg' => 2])
                        ->schema([
                            Select::make('sessionId')
                                ->label('Session')
                                ->options($this->sessionOptions())
                                ->placeholder('Sélectionner une session')
                                ->searchable()
                                ->live(),
                            Select::make('templateId')
                                ->label('Modèle')
                                ->options($this->templateOptions())
                                ->placeholder('Nouveau modèle non enregistré')
                                ->helperText(
                                    'Tous les modèles sont proposés. Le modèle choisi est chargé automatiquement.'
                                )
                                ->searchable()
                                ->live(),
                        ]),
                ]),
            Section::make('Modèle du message')
                ->description(
                    fn (): string => $this->templateId
                        ? 'Vous modifiez le modèle « ' . $this->templateName . ' ». Utilisez « Dupliquer » pour en créer une variante.'
                        : 'Vous préparez un nouveau modèle. Donnez-lui un nom puis utilisez « Créer le modèle ».'
                )
                ->schema([
                    Grid::make(['lg' => 2])
                        ->schema([
                            TextInput::make('templateName')
                                ->label('Nom du modèle')
                                ->placeholder('Ex. Admission standard BIP1')
                                ->required()
                                ->maxLength(150),
                            Select::make('templateStageId')
                                ->label('Portée du modèle')
                                ->options($this->stageOptions())
                                ->selectablePlaceholder(false),
                        ]),
                    TextInput::make('subjectTemplate')
                        ->label('Objet')
                        ->placeholder('Ex. Admission {stage} — {session}'),
                    Textarea::make('bodyTemplate')
                        ->label('Corps complet du message')
                        ->helperText(
                            'Placez {admis} et {refuses} à l’endroit où les listes doivent apparaître. '
                            . 'Une ligne de champs candidat placée directement sous ALPHA ou BRAVO est également répétée automatiquement.'
                        )
                        ->required()
                        ->rows(14),
                    Grid::make(['lg' => 2])
                        ->schema([
                            Textarea::make('admittedFormat')
                                ->label('Format d’un candidat admis')
                                ->helperText(
                                    'Utilisez ici les champs candidat comme {grade}, {nom}, {prenom}, {matricule} ou {unite}.'
                                )
                                ->required()
                                ->rows(4),
                            Textarea::make('refusedFormat')
                                ->label('Format d’un candidat refusé')
                                ->helperText(
                                    'Ce format est répété pour chaque candidat marqué « Refusé ».'
                                )
                                ->required()
                                ->rows(4),
                        ]),
                ]),
            Section::make('Candidats')
                ->description('La sélection ne modifie pas les statuts administratifs.')
                ->schema($this->candidateSchema()),
            Section::make('Message final')
                ->schema([
                    TextInput::make('finalSubject')
                        ->label('Objet final'),
                    Textarea::make('finalBody')
                        ->label('Message final')
                        ->rows(18),
                ]),
        ]);
    }

    public function mount(): void
    {
        $this->subjectTemplate =
            'Admission {stage} — {session}';

        $this->bodyTemplate =
            $this->defaultBodyTemplate();
    }

    public function updatedSessionId(): void
    {
        $this->candidateDecisions = [];
        $session = $this->selectedSession();

        if (! $session) {
            $this->templateStageId = 0;
            return;
        }

        $this->templateStageId = $session->stage_id;

        foreach ($session->inscriptions as $inscription) {
            $this->candidateDecisions[$inscription->id] = $this->defaultDecision($inscription);
        }
    }

    public function updatedTemplateId(): void
    {
        if ($this->templateId) {
            $this->loadTemplate(false);
        }
    }

    public function newTemplate(): void
    {
        $this->resetTemplateForm();

        Notification::make()->title('Nouveau modèle')->info()->send();
    }

    public function loadTemplate(
        bool $notify = true
    ): void
    {
        if (! $this->templateId) {
            if ($notify) {
                Notification::make()->title('Aucun modèle sélectionné')->warning()->send();
            }

            return;
        }

        $template = AdmissionMessageTemplate::query()->find($this->templateId);

        if (! $template) {
            Notification::make()->title('Modèle introuvable')->danger()->send();
            return;
        }

        $this->templateName = $template->nom;
        $this->templateStageId =
            (int) ($template->stage_id ?? 0);
        $this->subjectTemplate = (string) ($template->objet ?? '');
        $this->bodyTemplate = (string) $template->corps;
        $this->admittedFormat = (string) $template->format_admis;
        $this->refusedFormat = (string) $template->format_refuse;
        $this->clearGeneratedMessage();
        $this->resetValidation();

        if ($notify) {
            Notification::make()->title('Modèle chargé')->success()->send();
        }
    }

    public function duplicateTemplate(): void
    {
        if (! $this->templateId) {
            return;
        }

        $this->templateId = null;
        $this->templateName =
            'Copie de ' . $this->templateName;
        $this->clearGeneratedMessage();

        Notification::make()
            ->title('Copie prête à enregistrer')
            ->body(
                'Modifiez le nom ou le contenu, puis utilisez « Créer le modèle ».'
            )
            ->info()
            ->send();
    }

    public function saveTemplate(): void
    {
        $this->resetValidation();

        $name = trim($this->templateName);

        if ($name === '') {
            $this->addError(
                'templateName',
                'Donnez un nom au modèle pour pouvoir l’enregistrer.'
            );

            Notification::make()->title('Nom du modèle requis')->danger()->send();
            return;
        }

        if (mb_strlen($name) > 150) {
            $this->addError(
                'templateName',
                'Le nom du modèle ne peut pas dépasser 150 caractères.'
            );

            Notification::make()->title('Nom du modèle trop long')->danger()->send();
            return;
        }

        if (trim($this->bodyTemplate) === '') {
            $this->addError(
                'bodyTemplate',
                'Le corps du message est obligatoire.'
            );

            Notification::make()->title('Corps du message requis')->danger()->send();
            return;
        }

        if (trim($this->admittedFormat) === '' || trim($this->refusedFormat) === '') {
            $this->addError(
                'admittedFormat',
                'Les formats admis et refusé sont obligatoires.'
            );

            Notification::make()->title('Format candidat requis')->danger()->send();
            return;
        }

        $isNew = ! $this->templateId;

        $template = $this->templateId
            ? AdmissionMessageTemplate::query()->find($this->templateId)
            : null;

        $template ??= new AdmissionMessageTemplate();

        $template->fill([
            'stage_id' => $this->templateStageId ?: null,
            'nom' => $name,
            'objet' => trim($this->subjectTemplate) !== '' ? $this->subjectTemplate : null,
            'corps' => $this->bodyTemplate,
            'format_admis' => $this->admittedFormat,
            'format_refuse' => $this->refusedFormat,
            'actif' => true,
        ]);

        $template->save();
        $this->templateId = $template->id;

        Notification::make()
            ->title(
                $isNew
                    ? 'Nouveau modèle créé'
                    : 'Modèle mis à jour'
            )
            ->success()
            ->send();
    }

    public function deleteTemplate(): void
    {
        if (! $this->templateId) {
            return;
        }

        AdmissionMessageTemplate::query()->whereKey($this->templateId)->delete();
        $this->resetTemplateForm();

        Notification::make()->title('Modèle supprimé')->success()->send();
    }

    public function generateMessage(bool $notify = true): bool
    {
        $session = $this->selectedSession();

        if (! $session) {
            $this->clearGeneratedMessage();
            Notification::make()->title('Sélectionne une session')->warning()->send();

            return false;
        }

        $admitted = [];
        $refused = [];
        $admittedCandidates = [];
        $refusedCandidates = [];

        foreach ($session->inscriptions as $candidate) {
            $decision = $this->candidateDecisions[$candidate->id] ?? 'ignorer';

            if ($decision === 'admis') {
                $admittedCandidates[] = $candidate;
                $admitted[] = $this->formatCandidate($candidate, $this->admittedFormat);
            } elseif ($decision === 'refuse') {
                $refusedCandidates[] = $candidate;
                $refused[] = $this->formatCandidate($candidate, $this->refusedFormat);
            }
        }

        $variables = $this->sessionVariables($session);
        $variables['{admis}'] = implode("\n", $admitted);
        $variables['{refuses}'] = implode("\n", $refused);

        $this->finalSubject = strtr($this->subjectTemplate, $variables);
        $bodyTemplate = $this->expandCandidateSections(
            $this->bodyTemplate,
            $admittedCandidates,
            $refusedCandidates
        );
        $this->finalBody = strtr($bodyTemplate, $variables);
        $this->generationWarning = $this->buildGenerationWarning();

        if ($notify) {
            $notification = Notification::make()
                ->body(
                    $this->generationWarning !== ''
                        ? $this->generationWarning
                        : count($admitted) . ' admis — ' . count($refused) . ' refusé(s). Le résultat reste entièrement modifiable.'
                );

            if ($this->generationWarning !== '') {
                $notification
                    ->title('Message généré avec des champs non remplacés')
                    ->warning();
            } else {
                $notification
                    ->title('Message généré')
                    ->success();
            }

            $notification->send();
        }

        return true;
    }

    public function sessionOptions(): array
    {
        return SessionStage::query()
            ->with('stage')
            ->whereNotIn('statut', ['annulee'])
            ->orderBy('debut', 'desc')
            ->limit(250)
            ->get()
            ->mapWithKeys(function (SessionStage $session): array {
                $stage = $session->stage?->libelle_court ?? 'Stage';
                $date = $session->debut?->format('d/m/Y') ?? '—';

                return [$session->id => $stage . ' — ' . $session->code_session . ' — ' . $date];
            })
            ->all();
    }

    public function stageOptions(): array
    {
        return [0 => 'Modèle générique']
            + Stage::query()->orderBy('libelle_court')->pluck('libelle_court', 'id')->all();
    }

    public function templateOptions(): array
    {
        $query = AdmissionMessageTemplate::query()
            ->with('stage')
            ->where('actif', true);

        return $query
            ->orderByRaw('stage_id IS NULL DESC')
            ->orderBy('nom')
            ->get()
            ->mapWithKeys(function (AdmissionMessageTemplate $template): array {
                $scope = $template->stage?->libelle_court ?? 'Générique';
                return [$template->id => '[' . $scope . '] ' . $template->nom];
            })
            ->all();
    }

    public function candidates(): Collection
    {
        $session = $this->selectedSession();

        if (! $session) {
            return collect();
        }

        return $session->inscriptions
            ->sortBy(
                fn (
                    Inscription $candidate
                ): string =>
                    sprintf(
                        '%d-%s',
                        $candidate
                            ->stage_deja_effectue
                            ? 1
                            : 0,
                        mb_strtolower(
                            ($candidate->nom ?? '')
                            . ' '
                            . ($candidate->prenom ?? '')
                        )
                    )
            )
            ->values();
    }

    public function sessionFieldsHelpText(): string
    {
        return implode("\n", [
            '- `{stage}` : libellé court du stage',
            '- `{libelle_long}` : libellé complet du stage',
            '- `{session}` : code de la session',
            '- `{date_debut}` : date de début',
            '- `{date_fin}` : date de fin',
            '- `{salle}` : salle affectée',
            '- `{admis}` : liste formatée des candidats admis',
            '- `{refuses}` : liste formatée des candidats refusés',
        ]);
    }

    public function candidateFieldsHelpText(): string
    {
        return implode("\n", [
            '- `{nom}` : nom du candidat',
            '- `{prenom}` : prénom du candidat',
            '- `{grade}` : grade',
            '- `{brevet}` : brevet',
            '- `{specialite}` : spécialité',
            '- `{matricule}` : matricule',
            '- `{nid}` : identifiant NID',
            '- `{unite}` : bâtiment ou unité',
            '- `{email}` : adresse électronique',
            '- `{statut}` : statut de l’inscription',
        ]);
    }

    protected function automaticFieldsHelpSchema(): array
    {
        return [
            TextEntry::make('automaticFieldsUsage')
                ->label('Comment les utiliser ?')
                ->state(
                    'Les champs de session peuvent être utilisés dans l’objet et le corps du message. '
                    . 'Les champs candidat s’utilisent dans les formats « admis » et « refusé ». '
                    . 'Les codes `{admis}` et `{refuses}` placent ensuite les listes générées dans le corps. '
                    . 'Dans un message structuré en paragraphes ALPHA et BRAVO, une ligne contenant directement '
                    . 'les champs candidat est automatiquement répétée dans le bon paragraphe.'
                )
                ->markdown()
                ->prose(),
            Grid::make(['lg' => 2])
                ->schema([
                    TextEntry::make('sessionFieldsHelp')
                        ->label('Champs de la session')
                        ->state(
                            fn (): string =>
                                $this->sessionFieldsHelpText()
                        )
                        ->markdown()
                        ->prose(),
                    TextEntry::make('candidateFieldsHelp')
                        ->label('Champs de chaque candidat')
                        ->state(
                            fn (): string =>
                                $this->candidateFieldsHelpText()
                        )
                        ->markdown()
                        ->prose(),
                ]),
            TextEntry::make('automaticFieldsExample')
                ->label('Exemple à copier')
                ->state(
                    "Objet : Admission {stage} — {session}\n\n"
                    . "Corps : Bonjour, voici les candidats admis :\n{admis}\n\n"
                    . "Format admis : {grade} {nom} {prenom} — {matricule} — {unite}"
                )
                ->copyable()
                ->copyMessage('Exemple copié'),
        ];
    }

    protected function messagePreviewSchema(): array
    {
        return [
            TextEntry::make('generationWarningPreview')
                ->label('Modèle à corriger')
                ->state(fn (): string => $this->generationWarning)
                ->color('warning')
                ->icon('heroicon-o-exclamation-triangle')
                ->visible(fn (): bool => $this->generationWarning !== ''),
            TextInput::make('previewSubject')
                ->label('Objet généré')
                ->disabled()
                ->dehydrated(false),
            Textarea::make('previewBody')
                ->label('Corps généré')
                ->rows(20)
                ->disabled()
                ->dehydrated(false),
        ];
    }

    protected function candidateSchema(): array
    {
        if (! $this->sessionId) {
            return [
                TextInput::make('candidateSelectionHint')
                    ->label('Information')
                    ->default('Sélectionne d’abord une session.')
                    ->disabled(),
            ];
        }

        if ($this->candidates()->isEmpty()) {
            return [
                TextInput::make('candidateSelectionHint')
                    ->label('Information')
                    ->default('Aucun candidat pour cette session.')
                    ->disabled(),
            ];
        }

        return $this->candidates()
            ->map(function (Inscription $candidate): Section {
                $label = trim($candidate->grade . ' ' . $candidate->nom . ' ' . $candidate->prenom);
                $description = trim(($candidate->unite ?: 'Unité non renseignée')
                    . ($candidate->brevet ? ' — ' . $candidate->brevet : '')
                    . ($candidate->specialite ? ' — ' . $candidate->specialite : ''));

                if (
                    $candidate
                        ->stage_deja_effectue
                ) {
                    $description =
                        '⚠ Stage déjà effectué — candidat non prioritaire. '
                        . $description;
                }

                return Section::make($label)
                    ->description($description)
                    ->schema([
                        Select::make("candidateDecisions.{$candidate->id}")
                            ->label('Décision')
                            ->options([
                                'admis' => 'Admis',
                                'refuse' => 'Refusé',
                                'ignorer' => 'Ne pas inclure',
                            ])
                            ->selectablePlaceholder(false)
                            ->default($this->candidateDecisions[$candidate->id] ?? $this->defaultDecision($candidate)),
                    ]);
            })
            ->all();
    }

    private function selectedSession(): ?SessionStage
    {
        if (! $this->sessionId) {
            return null;
        }

        return SessionStage::query()
            ->with(['stage', 'salle', 'inscriptions'])
            ->find($this->sessionId);
    }

    private function defaultDecision(Inscription $candidate): string
    {
        if (
            $candidate
                ->stage_deja_effectue
        ) {
            return 'ignorer';
        }

        return match ($candidate->statut) {
            'confirmee' => 'admis',
            'refusee', 'annulee' => 'refuse',
            default => 'ignorer',
        };
    }

    private function formatCandidate(Inscription $candidate, string $format): string
    {
        return strtr($format, [
            '{nom}' => (string) ($candidate->nom ?? ''),
            '{prenom}' => (string) ($candidate->prenom ?? ''),
            '{grade}' => (string) ($candidate->grade ?? ''),
            '{brevet}' => (string) ($candidate->brevet ?? ''),
            '{specialite}' => (string) ($candidate->specialite ?? ''),
            '{matricule}' => (string) ($candidate->matricule ?? ''),
            '{nid}' => (string) ($candidate->nid ?? ''),
            '{unite}' => (string) ($candidate->unite ?? ''),
            '{email}' => (string) ($candidate->email ?? ''),
            '{statut}' => (string) ($candidate->statut ?? ''),
        ]);
    }

    /**
     * @param  array<int, Inscription>  $admittedCandidates
     * @param  array<int, Inscription>  $refusedCandidates
     */
    private function expandCandidateSections(
        string $body,
        array $admittedCandidates,
        array $refusedCandidates
    ): string {
        if (! str_contains($body, '{admis}')) {
            $body = $this->expandCandidateLineInSection(
                $body,
                'ALPHA',
                'BRAVO',
                $admittedCandidates
            );
        }

        if (! str_contains($body, '{refuses}')) {
            $body = $this->expandCandidateLineInSection(
                $body,
                'BRAVO',
                null,
                $refusedCandidates
            );
        }

        return $body;
    }

    /**
     * @param  array<int, Inscription>  $candidates
     */
    private function expandCandidateLineInSection(
        string $body,
        string $section,
        ?string $nextSection,
        array $candidates
    ): string {
        $headingPattern = '/^[^\r\n]*\b'
            . preg_quote($section, '/')
            . '\b[^\r\n]*(?:\R|$)/imu';

        if (! preg_match($headingPattern, $body, $headingMatch, PREG_OFFSET_CAPTURE)) {
            return $body;
        }

        $sectionStart = $headingMatch[0][1]
            + strlen($headingMatch[0][0]);
        $sectionEnd = strlen($body);

        if (
            $nextSection !== null
            && preg_match(
                '/^[^\r\n]*\b'
                    . preg_quote($nextSection, '/')
                    . '\b[^\r\n]*(?:\R|$)/imu',
                $body,
                $nextHeadingMatch,
                PREG_OFFSET_CAPTURE,
                $sectionStart
            )
        ) {
            $sectionEnd = $nextHeadingMatch[0][1];
        }

        $sectionBody = substr(
            $body,
            $sectionStart,
            $sectionEnd - $sectionStart
        );
        $candidatePlaceholders = [
            '{nom}',
            '{prenom}',
            '{grade}',
            '{brevet}',
            '{specialite}',
            '{matricule}',
            '{nid}',
            '{unite}',
            '{email}',
            '{statut}',
        ];
        $candidateLinePattern = '/^[^\r\n]*(?:'
            . implode(
                '|',
                array_map(
                    fn (string $placeholder): string =>
                        preg_quote($placeholder, '/'),
                    $candidatePlaceholders
                )
            )
            . ')[^\r\n]*$/imu';

        if (
            ! preg_match(
                $candidateLinePattern,
                $sectionBody,
                $candidateLineMatch,
                PREG_OFFSET_CAPTURE
            )
        ) {
            return $body;
        }

        $candidateLine = $candidateLineMatch[0][0];
        $candidateLineOffset = $sectionStart
            + $candidateLineMatch[0][1];
        $lineEnding = str_contains($body, "\r\n")
            ? "\r\n"
            : "\n";
        $renderedCandidates = implode(
            $lineEnding,
            array_map(
                fn (Inscription $candidate): string =>
                    $this->formatCandidate($candidate, $candidateLine),
                $candidates
            )
        );

        return substr_replace(
            $body,
            $renderedCandidates,
            $candidateLineOffset,
            strlen($candidateLine)
        );
    }

    private function sessionVariables(SessionStage $session): array
    {
        return [
            '{stage}' => (string) ($session->stage?->libelle_court ?? ''),
            '{libelle_long}' => (string) ($session->stage?->libelle_long ?? ''),
            '{session}' => (string) ($session->code_session ?? ''),
            '{date_debut}' => $this->formatDate($session->debut),
            '{date_fin}' => $this->formatDate($session->fin),
            '{salle}' => (string) ($session->salle?->nom ?? ''),
        ];
    }

    private function formatDate(mixed $value): string
    {
        if (! $value) {
            return '';
        }

        if ($value instanceof Carbon) {
            return $value->format('d/m/Y');
        }

        return Carbon::parse($value)->format('d/m/Y');
    }

    private function resetTemplateForm(): void
    {
        $this->templateId = null;
        $this->templateName = '';
        $this->templateStageId =
            $this->selectedSession()?->stage_id
            ?? 0;
        $this->subjectTemplate =
            'Admission {stage} — {session}';
        $this->bodyTemplate =
            $this->defaultBodyTemplate();
        $this->admittedFormat =
            '{grade} {nom} {prenom} — {unite}';
        $this->refusedFormat =
            '{grade} {nom} {prenom} — {unite}';
        $this->clearGeneratedMessage();
        $this->resetValidation();
    }

    private function clearGeneratedMessage(): void
    {
        $this->finalSubject = '';
        $this->finalBody = '';
        $this->generationWarning = '';
    }

    private function buildGenerationWarning(): string
    {
        preg_match_all(
            '/\{[^{}\r\n]+\}/u',
            $this->finalSubject . "\n" . $this->finalBody,
            $matches
        );

        $placeholders = array_values(
            array_unique($matches[0] ?? [])
        );

        if ($placeholders === []) {
            return '';
        }

        $candidatePlaceholders = [
            '{nom}',
            '{prenom}',
            '{grade}',
            '{brevet}',
            '{specialite}',
            '{matricule}',
            '{nid}',
            '{unite}',
            '{email}',
            '{statut}',
        ];

        $warning = 'Champs non remplacés : '
            . implode(', ', $placeholders)
            . '.';

        if (array_intersect($placeholders, $candidatePlaceholders) !== []) {
            $warning .= ' Placez les champs candidat dans les formats « admis/refusé », '
                . 'puis utilisez {admis} et {refuses} dans le corps du message.';
        } else {
            $warning .= ' Vérifiez leur orthographe dans l’aide des champs automatiques.';
        }

        return $warning;
    }

    private function defaultBodyTemplate(): string
    {
        return "Bonjour,\n\n"
            . "Veuillez trouver ci-dessous les résultats d'admission pour le stage {stage}, session {session}, prévu du {date_debut} au {date_fin}.\n\n"
            . "CANDIDATS ADMIS\n\n{admis}\n\n"
            . "CANDIDATS REFUSÉS\n\n{refuses}\n\n"
            . 'Cordialement,';
    }
}
