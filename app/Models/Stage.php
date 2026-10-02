<?php

namespace Modules\FPSplanificationstage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;

class Stage extends Model
{
    protected $fillable = [
        'code_stage',
        'numero_externe',

        'date_creation_catalogue',
        'date_maj_catalogue',
        'nature_maj',

        'raf',
        'centre_formation',
        'typologie',

        'libelle_court',
        'appellation_chorus',
        'branche',
        'adc',
        'libelle_long',

        'diplomes_qualifications',
        'unite_certification',

        'cursus_ouvert',
        'sirh',
        'ouverture_licence',

        'date_cdf',
        'ecole_pilote_cdf',

        'duree_jours',
        'nb_sessions_annuelles',

        'capacite_max',
        'capacite_min',

        'salle_preferentielle_id',

        'ouvert_off',
        'ouvert_om',
        'ouvert_qmm_mo',

        'ouverture_etrangers',

        'possibilite_ead',
        'duree_ead_ui',

        'ouverture_vca',
        'ouverture_vae',

        'autres_beneficiaires',
        'observations',

        'actif',

        'catalogue_match_key',
        'catalogue_hash',
        'dernier_import_at',
            'fif_generation',
        'intitule_formation',
        'service_emetteur',
        'si_enregistrement_qualification',
        'echelle_grades',
        'niveau_brevet',
        'lieux_formation',
        'fonctions_visees',
        'objectif_formation',
        'domaines_competences_vises',
        'criteres_certification',
        'evaluation_diagnostique',
        'evaluation_formative',
        'evaluation_certificative',
        'evaluation_format',
        'pedagogie_groupes',
        'pedagogie_visite',
        'pedagogie_video',
        'pedagogie_tableau_interactif',
        'pedagogie_autre',
        'fif_validation',
        'fif_donnees_source',
        'fif_source_fichier',
        'fif_import_hash',
        'fif_imported_at',
];

    protected $casts = [
        'date_creation_catalogue' => 'date',
        'date_maj_catalogue' => 'date',
        'date_cdf' => 'date',

        'duree_jours' => 'decimal:1',
        'duree_ead_ui' => 'decimal:1',

        'nb_sessions_annuelles' => 'integer',
        'capacite_max' => 'integer',
        'capacite_min' => 'integer',

        'ouvert_off' => 'boolean',
        'ouvert_om' => 'boolean',
        'ouvert_qmm_mo' => 'boolean',

        'ouverture_etrangers' => 'boolean',

        'possibilite_ead' => 'boolean',

        'ouverture_vca' => 'boolean',
        'ouverture_vae' => 'boolean',

        'actif' => 'boolean',

        'dernier_import_at' => 'datetime',
            'fif_validation' => 'array',
        'fif_donnees_source' => 'array',
        'fif_imported_at' => 'datetime',
];

    protected static function booted(): void
    {
        static::created(function (Stage $stage): void {
            if (! $stage->code_stage) {
                $stage->forceFill([
                    'code_stage' => sprintf(
                        'STG-%06d',
                        $stage->id
                    ),
                ])->saveQuietly();
            }
        });
    }

    public function prerequis(): HasMany
    {
        return $this->hasMany(
            PrerequisStage::class
        )->orderBy('ordre');
    }

    public function instructeurs(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'instructeur_stage',
            'stage_id',
            'instructeur_id'
        )
            ->withPivot([
                'role',
                'actif',
                'commentaire',
                'source',
                'dernier_import_at',
            ])
            ->withTimestamps();
    }

    public function sallePreferentielle(): BelongsTo
    {
        return $this->belongsTo(
            Salle::class,
            'salle_preferentielle_id'
        );
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(
            SessionStage::class
        );
    }

    public function fifModules(): HasMany
    {
        return $this
            ->hasMany(
                StageFifModule::class
            )
            ->orderBy(
                'ordre'
            );
    }
}
