<?php

namespace Modules\FPSplanificationstage\Services;

use App\Models\User;
use Modules\RH\Models\Marin;

class InstructeurConnecteService
{
    public function resolve(
        ?User $user = null
    ): ?Marin {
        $user ??= auth()->user();

        if (! $user instanceof User) {
            return null;
        }

        return Marin::fromUser($user)
            ?? app(StagiaireResolver::class)->find([
                'nom' => $user->nom,
                'prenom' => $user->prenom,
                'email' => $user->email,
            ]);
    }

    public function peutAccederEspaceInstructeur(
        ?User $user = null
    ): bool {
        return $this->resolve($user) !== null;
    }
}
