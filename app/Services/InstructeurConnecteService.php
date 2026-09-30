<?php

namespace Modules\FPSplanificationstage\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
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
        $instructeur = $this->resolve($user);

        if (! $instructeur instanceof Marin) {
            return false;
        }

        return $this->seulementInstructeurs(
            Marin::withoutGlobalScopes()
        )
            ->whereKey(
                $instructeur->getKey()
            )
            ->exists();
    }

    public function seulementInstructeurs(
        Builder $query
    ): Builder {
        return $query->where(
            fn (Builder $query): Builder => $query
                ->whereIn(
                    'rh_marins.id',
                    DB::table(
                        'instructeur_stage'
                    )->select(
                        'instructeur_id'
                    )
                )
                ->orWhereIn(
                    'rh_marins.id',
                    DB::table(
                        'instructeur_session_stage'
                    )->select(
                        'instructeur_id'
                    )
                )
                ->orWhereIn(
                    'rh_marins.id',
                    DB::table(
                        'indisponibilite_instructeurs'
                    )->select(
                        'instructeur_id'
                    )
                )
        );
    }
}
