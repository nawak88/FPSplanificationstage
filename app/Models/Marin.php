<?php

namespace Modules\FPSplanificationstage\Models;

use App\Models\User;
use Modules\FPSplanificationstage\Services\InstructeurConnecteService;
use Modules\RH\Models\Marin as RhMarin;

class Marin extends RhMarin
{
    public function estInstructeur(): bool
    {
        if (!$this->exists) {
            return false;
        }

        return app(InstructeurConnecteService::class)
            ->seulementInstructeurs(static::withoutGlobalScopes())
            ->whereKey($this->getKey())
            ->exists();
    }

    public static function utilisateurCourantEstInstructeur(): bool
    {
        return static::utilisateurEstInstructeur(auth()->user());
    }

    public static function utilisateurEstInstructeur(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return app(InstructeurConnecteService::class)->resolve($user)?->estInstructeur() ?? false;
    }
}
