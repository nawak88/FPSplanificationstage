<?php

namespace Modules\FPSplanificationstage\Filament\Resources\Inscriptions\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\FPSplanificationstage\Models\Inscription;
use Modules\FPSplanificationstage\Models\SessionStage;
use Modules\RH\Models\Marin;

class InscriptionForm
{
    public static function configure(
        Schema $schema
    ): Schema {
        return $schema
            ->components([

                Section::make(
                    'Session'
                )
                    ->schema([

                        Select::make(
                            'session_stage_id'
                        )
                            ->label(
                                'Session de stage'
                            )
                            ->options(
                                fn (): array =>
                                    SessionStage::query()
                                        ->with('stage')
                                        ->orderBy('debut')
                                        ->get()
                                        ->mapWithKeys(
                                            function (
                                                SessionStage $session
                                            ): array {
                                                $stage =
                                                    $session
                                                        ->stage
                                                        ?->libelle_court
                                                    ?? 'Stage';

                                                $debut =
                                                    $session
                                                        ->debut
                                                        ?->format(
                                                            'd/m/Y'
                                                        )
                                                    ?? '—';

                                                $places =
                                                    $session
                                                        ->capacite_max
                                                    !== null
                                                        ? $session
                                                            ->places_reservees
                                                            . '/'
                                                            . $session
                                                                ->capacite_max
                                                        : 'illimité';

                                                return [
                                                    $session->id =>
                                                        $session
                                                            ->code_session
                                                        . ' — '
                                                        . $stage
                                                        . ' — '
                                                        . $debut
                                                        . ' — '
                                                        . $places
                                                        . ' place(s)',
                                                ];
                                            }
                                        )
                                        ->all()
                            )
                            ->searchable()
                            ->required(),

                        Select::make(
                            'motif_inscription'
                        )
                            ->label('Motif')
                            ->options(
                                Inscription::motifInscriptionOptions()
                            )
                            ->default('sans_objet')
                            ->selectablePlaceholder(false)
                            ->required(),

                    ])
                    ->columns(2),

                Section::make(
                    'Stagiaire'
                )
                    ->schema([

                        TextEntry::make(
                            'fiche_rh_absente'
                        )
                            ->label(
                                'Fiche RH absente'
                            )
                            ->state(
                                fn (
                                    ?Inscription $record
                                ): string =>
                                    trim(
                                        ($record?->candidat_nom ?? '')
                                        . ' '
                                        . ($record?->candidat_prenom ?? '')
                                    )
                                    . ' — utilisez l’action « Créer marin » depuis la liste des inscriptions.'
                            )
                            ->icon(
                                'heroicon-o-exclamation-triangle'
                            )
                            ->color('warning')
                            ->visible(
                                fn (
                                    ?Inscription $record
                                ): bool =>
                                    $record?->source
                                    === 'public'
                                    && $record
                                        ->stagiaire_id
                                    === null
                            )
                            ->columnSpanFull(),


                        Select::make('stagiaire_id')
                            ->label('Stagiaire')
                            ->options(
                                fn (): array => Marin::withoutGlobalScopes()
                                    ->with([
                                        'grade',
                                        'specialite',
                                        'brevet',
                                        'unite',
                                    ])
                                    ->orderBy('nom')
                                    ->orderBy('prenom')
                                    ->get()
                                    ->mapWithKeys(
                                        fn (Marin $marin): array => [
                                            $marin->getKey() => trim(
                                                $marin->nom . ' '
                                                . $marin->prenom
                                            )
                                            . (
                                                $marin->matricule
                                                    ? ' — ' . $marin->matricule
                                                    : ''
                                            )
                                            . (
                                                $marin->nid
                                                    ? ' — NID ' . $marin->nid
                                                    : ''
                                            ),
                                        ]
                                    )
                                    ->all()
                            )
                            ->searchable()
                            ->preload()
                            ->required(
                                fn (
                                    ?Inscription $record
                                ): bool =>
                                    ! $record
                                    || $record->source
                                    !== 'public'
                                    || $record
                                        ->stagiaire_id
                                    !== null
                            )
                            ->live(),
                    ])
                    ->columns(2),

                // PRESENCE_STAGE_V1
                Section::make(
                    'Participation'
                )
                    ->description(
                        'À renseigner après le déroulement du stage.'
                    )
                    ->schema([
                        Select::make(
                            'presence'
                        )
                            ->label(
                                'Présence au stage'
                            )
                            ->options([
                                'non_renseignee' =>
                                    'Non renseignée',

                                'present' =>
                                    'Présent',

                                'absent' =>
                                    'Absent',
                            ])
                            ->default(
                                'non_renseignee'
                            )
                            ->required(),
                    ]),
                Section::make(
                    'Prérequis déclarés'
                )
                    ->description(
                        'Réponses fournies par le stagiaire lors de son inscription.'
                    )
                    ->schema([

                        CheckboxList::make(
                            'prerequis_declares'
                        )
                            ->label(
                                'Prérequis remplis'
                            )
                            ->options(
                                function (
                                    ?Inscription $record
                                ): array {
                                    if (! $record) {
                                        return [];
                                    }

                                    $record->loadMissing(
                                        'sessionStage.stage.prerequis'
                                    );

                                    $prerequis =
                                        $record
                                            ->sessionStage
                                            ?->stage
                                            ?->prerequis;

                                    if (! $prerequis) {
                                        return [];
                                    }

                                    return $prerequis
                                        ->mapWithKeys(
                                            function (
                                                $prerequis
                                            ): array {
                                                $suffixe =
                                                    $prerequis
                                                        ->obligatoire
                                                        ? ' — obligatoire'
                                                        : ' — facultatif';

                                                return [
                                                    $prerequis->id =>
                                                        $prerequis
                                                            ->libelle
                                                        . $suffixe,
                                                ];
                                            }
                                        )
                                        ->all();
                                }
                            )
                            ->afterStateHydrated(
                                function (
                                    CheckboxList $component,
                                    ?Inscription $record
                                ): void {
                                    if (! $record) {
                                        $component->state([]);

                                        return;
                                    }

                                    $ids =
                                        $record
                                            ->prerequisReponses()
                                            ->where(
                                                'respecte',
                                                true
                                            )
                                            ->pluck(
                                                'prerequis_stage_id'
                                            )
                                            ->all();

                                    $component->state(
                                        $ids
                                    );
                                }
                            )
                            ->disabled()
                            ->dehydrated(false)
                            ->columns(1),

                        Textarea::make(
                            'prerequis_manquants'
                        )
                            ->label(
                                'Prérequis obligatoires manquants'
                            )
                            ->afterStateHydrated(
                                function (
                                    Textarea $component,
                                    ?Inscription $record
                                ): void {
                                    if (! $record) {
                                        $component->state(
                                            'Aucune information.'
                                        );

                                        return;
                                    }

                                    $record->loadMissing([
                                        'sessionStage.stage.prerequis',
                                        'prerequisReponses',
                                    ]);

                                    $prerequis =
                                        $record
                                            ->sessionStage
                                            ?->stage
                                            ?->prerequis;

                                    if (! $prerequis) {
                                        $component->state(
                                            'Aucun prérequis défini pour ce stage.'
                                        );

                                        return;
                                    }

                                    $idsRespectes =
                                        $record
                                            ->prerequisReponses
                                            ->where(
                                                'respecte',
                                                true
                                            )
                                            ->pluck(
                                                'prerequis_stage_id'
                                            )
                                            ->all();

                                    $manquants =
                                        $prerequis
                                            ->filter(
                                                fn ($item): bool =>
                                                    (bool) $item
                                                        ->obligatoire
                                                    && ! in_array(
                                                        $item->id,
                                                        $idsRespectes,
                                                        true
                                                    )
                                            )
                                            ->pluck(
                                                'libelle'
                                            )
                                            ->values();

                                    if (
                                        $manquants
                                            ->isEmpty()
                                    ) {
                                        $component->state(
                                            'Aucun prérequis obligatoire manquant.'
                                        );

                                        return;
                                    }

                                    $component->state(
                                        $manquants
                                            ->map(
                                                fn (
                                                    string $libelle
                                                ): string =>
                                                    '• '
                                                    . $libelle
                                            )
                                            ->implode(
                                                PHP_EOL
                                            )
                                    );
                                }
                            )
                            ->rows(4)
                            ->disabled()
                            ->dehydrated(false),

                    ])
                    ->columns(1),

                Section::make(
                    'Suivi de l’inscription'
                )
                    ->description(
                        'Informations internes réservées aux gestionnaires.'
                    )
                    ->schema([

                        TextEntry::make(
                            'stage_deja_effectue_alerte'
                        )
                            ->label(
                                'Alerte de priorité'
                            )
                            ->state(
                                'Ce marin a déjà effectué ce stage. Sa nouvelle candidature ne doit pas être traitée en priorité.'
                            )
                            ->icon(
                                'heroicon-o-exclamation-triangle'
                            )
                            ->color('warning')
                            ->visible(
                                fn (
                                    ?Inscription $record
                                ): bool =>
                                    (bool) $record
                                        ?->stage_deja_effectue
                            )
                            ->columnSpanFull(),

                        Select::make(
                            'statut'
                        )
                            ->label(
                                'Situation particulière'
                            )
                            ->options([
                                'attente_derogation' =>
                                    'Attente dérogation',

                                'liste_attente' =>
                                    'Liste d’attente',

                                'refusee' =>
                                    'Refusée',

                                'annulee' =>
                                    'Annulée',
                            ])
                            ->placeholder(
                                'Aucune — déterminée par le NEMO'
                            )
                            ->afterStateHydrated(
                                function (
                                    Select $component,
                                    ?Inscription $record
                                ): void {
                                    if (
                                        ! $record
                                        || in_array(
                                            $record->statut,
                                            [
                                                'attente_nemo',
                                                'confirmee',
                                            ],
                                            true
                                        )
                                    ) {
                                        $component->state(null);
                                    }
                                }
                            )
                            ->visible(
                                fn ($get): bool =>
                                    ! (bool) $get(
                                        'nemo_recu'
                                    )
                            ),

                        Toggle::make(
                            'nemo_recu'
                        )
                            ->label(
                                'NEMO reçu'
                            )
                            ->helperText(
                                'Décochez : attente NEMO. Cochez : inscription confirmée.'
                            )
                            ->default(false)
                            ->live(),

                        Toggle::make(
                            'derogation_demandee'
                        )
                            ->label(
                                'Dérogation demandée'
                            )
                            ->default(false)
                            ->live(),

                        Select::make(
                            'derogation_statut'
                        )
                            ->label(
                                'Statut dérogation'
                            )
                            ->options([
                                'en_attente' =>
                                    'En attente',

                                'acceptee' =>
                                    'Acceptée',

                                'refusee' =>
                                    'Refusée',
                            ])
                            ->live()
                            ->visible(
                                fn ($get): bool =>
                                    (bool) $get(
                                        'derogation_demandee'
                                    )
                            ),

                        FileUpload::make(
                            'derogation_document'
                        )
                            ->label(
                                'Document de dérogation accepté'
                            )
                            ->helperText(
                                'Formats acceptés : PDF, JPEG ou PNG (10 Mo maximum).'
                            )
                            ->disk('local')
                            ->directory(
                                'inscriptions/derogations'
                            )
                            ->acceptedFileTypes([
                                'application/pdf',
                                'image/jpeg',
                                'image/png',
                            ])
                            ->maxSize(10240)
                            ->previewable(false)
                            ->visible(
                                fn ($get): bool =>
                                    (bool) $get(
                                        'derogation_demandee'
                                    )
                                    && $get(
                                        'derogation_statut'
                                    ) === 'acceptee'
                            )
                            ->columnSpanFull(),

                        Textarea::make(
                            'derogation_motif'
                        )
                            ->label(
                                'Justification du stagiaire'
                            )
                            ->rows(3)
                            ->visible(
                                fn ($get): bool =>
                                    (bool) $get(
                                        'derogation_demandee'
                                    )
                            )
                            ->columnSpanFull(),

                        Textarea::make(
                            'commentaire'
                        )
                            ->label(
                                'Commentaire gestionnaire'
                            )
                            ->rows(4)
                            ->columnSpanFull(),

                        Hidden::make(
                            'source'
                        )
                            ->default(
                                'manuel'
                            ),

                    ])
                    ->columns(2),
            ]);
    }
}
