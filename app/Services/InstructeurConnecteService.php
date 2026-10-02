<?php

namespace Modules\FPSplanificationstage\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\FPSplanificationstage\Models\Marin;

class InstructeurConnecteService
{
    public function resolve(
        ?User $user = null
    ): ?Marin {
        $user ??= auth()->user();

        if (! $user instanceof User) {
            return null;
        }

        $marin = Marin::fromUser($user);

        if ($marin) {
            return $marin;
        }

        $marin = app(StagiaireResolver::class)->find([
            'nom' => $user->nom,
            'prenom' => $user->prenom,
            'email' => $user->email,
        ]);

        return $marin ? Marin::withoutGlobalScopes()->find($marin->getKey()) : null;
    }

    public function peutAccederEspaceInstructeur(
        ?User $user = null
    ): bool {
        return Marin::utilisateurEstInstructeur($user ?? auth()->user());
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
