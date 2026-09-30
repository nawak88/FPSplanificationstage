<?php

namespace Modules\FPSplanificationstage\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\FPSplanificationstage\Services\StageDejaEffectueDetector;
use Modules\RH\Models\Marin;

class Inscription extends Model
{
    
protected $table = 'inscriptions';

    protected $fillable = [
        'presence',
        'stage_deja_effectue',
        'motif_inscription',
        'stagiaire_id',
        'candidat_user_id',
        'candidat_nom',
        'candidat_prenom',
        'candidat_email',
        'candidat_matricule',
        'candidat_nid',
        'candidat_grade',
        'candidat_brevet',
        'candidat_specialite',
        'candidat_unite',
        'candidat_telephone',
        'code_inscription',
        'session_stage_id',

        'statut',
        'nemo_recu',
        'nemo_recu_at',
        'derogation_demandee',
        'derogation_statut',
        'derogation_motif',
        'derogation_document',
        'commentaire',
        'source',
    ];

    protected $casts = [
        'nemo_recu' =>
            'boolean',

        'nemo_recu_at' =>
            'datetime',

        'derogation_demandee' =>
            'boolean',

        'stage_deja_effectue' =>
            'boolean',
    ];

    public static function motifInscriptionOptions(): array
    {
        return [
            'cursus_specialite' =>
                'Cursus de spécialité',

            'depart_outre_mer' =>
                'Départ outre-mer',

            'par_unite_deficitaire' =>
                'PAR de l’unité déficitaire',

            'preparation_prochain_pam' =>
                'Préparation prochain PAM',

            'prerequis_bs_csup' =>
                'Prérequis BS ou CSUP',

            'autre' =>
                'Autre',

            'sans_objet' =>
                'Sans objet',
        ];
    }

    public function getMotifInscriptionLabelAttribute(): string
    {
        return static::motifInscriptionOptions()[
            $this->motif_inscription
        ]
            ?? $this->motif_inscription
            ?? 'Sans objet';
    }

    protected static function booted(): void
    {
        static::saving(
            function (
                Inscription $inscription
            ): void {
                if (
                    ! $inscription->exists
                    || $inscription->isDirty([
                        'stagiaire_id',
                        'session_stage_id',
                    ])
                ) {
                    $inscription
                        ->stage_deja_effectue =
                        app(
                            StageDejaEffectueDetector::class
                        )->detecte(
                            $inscription
                                ->stagiaire_id,
                            $inscription
                                ->session_stage_id,
                            $inscription->exists
                                ? $inscription
                                    ->getKey()
                                : null
                        );
                }

                if (
                    $inscription->nemo_recu
                    && ! $inscription->nemo_recu_at
                ) {
                    $inscription->nemo_recu_at =
                        now();
                }

                if (
                    ! $inscription->nemo_recu
                ) {
                    $inscription->nemo_recu_at =
                        null;
                }

                $derogationTerminee =
                    $inscription->statut
                    === 'attente_derogation'
                    && in_array(
                        $inscription
                            ->derogation_statut,
                        [
                            'acceptee',
                            'refusee',
                        ],
                        true
                    );

                if (
                    $derogationTerminee
                    && $inscription
                        ->derogation_statut
                    === 'refusee'
                ) {
                    $inscription->statut =
                        'refusee';
                } elseif (
                    blank($inscription->statut)
                    || $derogationTerminee
                    || in_array(
                        $inscription->statut,
                        [
                            'attente_nemo',
                            'confirmee',
                        ],
                        true
                    )
                ) {
                    $inscription->statut =
                        $inscription->nemo_recu
                            ? 'confirmee'
                            : 'attente_nemo';
                }

                $statutsReservantUnePlace = [
                    'attente_nemo',
                    'confirmee',
                    'attente_derogation',
                ];

                if (
                    ! in_array(
                        $inscription->statut,
                        $statutsReservantUnePlace,
                        true
                    )
                ) {
                    return;
                }

                if (
                    ! $inscription->session_stage_id
                ) {
                    return;
                }

                $session =
                    SessionStage::find(
                        $inscription
                            ->session_stage_id
                    );

                if (
                    ! $session
                    || $session->capacite_max
                    === null
                ) {
                    return;
                }

                $query =
                    static::query()
                        ->where(
                            'session_stage_id',
                            $session->id
                        )
                        ->whereIn(
                            'statut',
                            $statutsReservantUnePlace
                        );

                if (
                    $inscription->exists
                    && $inscription->getKey()
                ) {
                    $query->whereKeyNot(
                        $inscription->getKey()
                    );
                }

                $placesOccupees =
                    $query->count();

                if (
                    $placesOccupees
                    >= $session->capacite_max
                ) {
                    $inscription->statut =
                        'liste_attente';
                }
            }
        );

        static::created(
            function (
                Inscription $inscription
            ): void {
                if (
                    ! $inscription
                        ->code_inscription
                ) {
                    $inscription
                        ->forceFill([
                            'code_inscription' =>
                                sprintf(
                                    'INS-%06d',
                                    $inscription->id
                                ),
                        ])
                        ->saveQuietly();
                }
            }
        );

    }

    public function sessionStage(): BelongsTo
    {
        return $this->belongsTo(
            SessionStage::class,
            'session_stage_id'
        );
    }

    public function prerequisReponses(): HasMany
    {
        return $this->hasMany(
            InscriptionPrerequis::class,
            'inscription_id'
        );
    }

    public function getNomCompletAttribute(): string
    {
        return trim(
            mb_strtoupper(
                $this->nom ?? ''
            )
            . ' '
            . ($this->prenom ?? '')
        );
    }

    public function getNomAttribute(): ?string
    {
        return $this->stagiaire?->nom
            ?? $this->candidat_nom;
    }

    public function getPrenomAttribute(): ?string
    {
        return $this->stagiaire?->prenom
            ?? $this->candidat_prenom;
    }

    public function getEmailAttribute(): ?string
    {
        return $this->stagiaire?->email
            ?? $this->candidat_email;
    }

    public function getMatriculeAttribute(): ?string
    {
        return $this->stagiaire?->matricule
            ?? $this->candidat_matricule;
    }

    public function getNidAttribute(): ?string
    {
        return $this->stagiaire?->nid
            ?? $this->candidat_nid;
    }

    public function getGradeAttribute(): ?string
    {
        return $this->stagiaire
            ?->grade
            ?->libelle_court
            ?? $this->candidat_grade;
    }

    public function getBrevetAttribute(): ?string
    {
        return $this->stagiaire
            ?->brevet
            ?->libelle_court
            ?? $this->candidat_brevet;
    }

    public function getSpecialiteAttribute(): ?string
    {
        return $this->stagiaire
            ?->specialite
            ?->libelle_court
            ?? $this->candidat_specialite;
    }

    public function getUniteAttribute(): ?string
    {
        return $this->stagiaire
            ?->unite
            ?->libelle_court
            ?? $this->candidat_unite;
    }

    public function reserveUnePlace(): bool
    {
        return in_array(
            $this->statut,
            [
                'attente_nemo',
                'confirmee',
                'attente_derogation',
            ],
            true
        );
    }
    public function stagiaire(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(
            Marin::class,
            'stagiaire_id'
        )->withoutGlobalScopes();
    }

    public function candidatUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'candidat_user_id'
        );
    }

    public function scopeDuCandidat(
        Builder $query,
        ?int $userId,
        ?string $email,
        ?int $stagiaireId = null
    ): Builder {
        return $query->where(
            function (
                Builder $candidateQuery
            ) use (
                $userId,
                $email,
                $stagiaireId
            ): void {
                if ($userId) {
                    $candidateQuery->orWhere(
                        'candidat_user_id',
                        $userId
                    );
                }

                if ($stagiaireId) {
                    $candidateQuery->orWhere(
                        'stagiaire_id',
                        $stagiaireId
                    );
                }

                if ($email) {
                    $candidateQuery
                        ->orWhereRaw(
                            'LOWER(candidat_email) = ?',
                            [
                                mb_strtolower($email),
                            ]
                        )
                        ->orWhereHas(
                            'stagiaire',
                            fn (Builder $marinQuery) =>
                                $marinQuery
                                    ->whereRaw(
                                        'LOWER(email) = ?',
                                        [
                                            mb_strtolower($email),
                                        ]
                                    )
                        );
                }
            }
        );
    }

}
