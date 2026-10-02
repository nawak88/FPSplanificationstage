<?php

namespace Modules\FPSplanificationstage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\FPSplanificationstage\Models\Marin;

class SessionStage extends Model
{
    protected $table = 'session_stages';

    protected $fillable = [
        'code_session',
        'stage_id',
        'salle_id',
        'debut',
        'fin',
        'capacite_min',
        'capacite_max',
        'statut',
        'salle_forcee',
        'source',
        'commentaire',
    ];

    protected $casts = [
        'debut' => 'datetime',
        'fin' => 'datetime',
        'capacite_min' => 'integer',
        'capacite_max' => 'integer',
        'salle_forcee' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::created(
            function (SessionStage $session): void {
                if (! $session->code_session) {
                    $session
                        ->forceFill([
                            'code_session' => sprintf(
                                'SES-%06d',
                                $session->id
                            ),
                        ])
                        ->saveQuietly();
                }
            }
        );
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(
            Stage::class
        );
    }

    public function salle(): BelongsTo
    {
        return $this->belongsTo(
            Salle::class
        );
    }

    public function instructeurs(): BelongsToMany
    {
        return $this->belongsToMany(
            Marin::class,
            'instructeur_session_stage',
            'session_stage_id',
            'instructeur_id'
        )
            ->withPivot('role')
            ->withTimestamps();
    }

    public function besoinFormation(): HasOne
    {
        return $this->hasOne(
            BesoinFormation::class,
            'session_stage_id'
        );
    }

    /* RELATION_BESOINS_MULTI_SESSION */
    public function besoins(): BelongsToMany
    {
        return $this->belongsToMany(
            BesoinFormation::class,
            'besoin_session_stage',
            'session_stage_id',
            'besoin_formation_id'
        )
            ->withPivot('effectif_prevu')
            ->withTimestamps();
    }

    public function inscriptions(): HasMany
    {
        return $this->hasMany(
            Inscription::class,
            'session_stage_id'
        );
    }

    public function getPlacesReserveesAttribute(): int
    {
        return $this
            ->inscriptions()
            ->whereIn(
                'statut',
                [
                    'attente_nemo',
                    'confirmee',
                    'attente_derogation',
                ]
            )
            ->count();
    }

    public function getPlacesRestantesAttribute(): ?int
    {
        if ($this->capacite_max === null) {
            return null;
        }

        return max(
            0,
            $this->capacite_max
            - $this->places_reservees
        );
    }

    public function estComplete(): bool
    {
        if ($this->capacite_max === null) {
            return false;
        }

        return $this->places_reservees
            >= $this->capacite_max;
    }
}
