<?php

namespace Modules\FPSplanificationstage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\FPSplanificationstage\Models\Marin;

class IndisponibiliteInstructeur extends Model
{
    protected $table = 'indisponibilite_instructeurs';

    protected $fillable = [
        'instructeur_id',
        'date_debut',
        'heure_debut',
        'date_fin',
        'heure_fin',
        'journee_entiere',
        'motif',
        'commentaire',
        'actif',
        'import_match_key',
        'import_hash',
        'dernier_import_at',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'journee_entiere' => 'boolean',
        'actif' => 'boolean',
        'dernier_import_at' => 'datetime',
    ];

    public function instructeur(): BelongsTo
    {
        return $this->belongsTo(
            Marin::class,
            'instructeur_id'
        );
    }
}
