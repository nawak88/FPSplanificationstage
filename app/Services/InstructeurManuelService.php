<?php

namespace Modules\FPSplanificationstage\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\FPSplanificationstage\Models\Stage;

class InstructeurManuelService
{
    public function ajouter(?int $userId, array $stageIds = [], string $role = 'indifferent', ?string $commentaire = null): User
    {
        Gate::authorize('fpsplanificationstage::gerer_le_module');
        Validator::make([
            'user_id' => $userId, 'stage_ids' => $stageIds, 'role' => $role, 'commentaire' => $commentaire,
        ], [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'stage_ids' => ['array'],
            'stage_ids.*' => ['integer', 'distinct', Rule::exists('stages', 'id')],
            'role' => ['required', Rule::in(['principal', 'suppleant', 'indifferent'])],
            'commentaire' => ['nullable', 'string', 'max:5000'],
        ])->validate();

        return DB::transaction(function () use ($userId, $stageIds, $role, $commentaire): User {
            $user = User::findOrFail($userId);
            DB::table('fps_instructeur_users')->insertOrIgnore([
                'user_id' => $user->getKey(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach (Stage::whereKey($stageIds)->get() as $stage) {
                $stage->instructeurs()->syncWithoutDetaching([
                    $user->getKey() => [
                        'role' => $role, 'actif' => true,
                        'commentaire' => filled($commentaire) ? trim($commentaire) : null,
                        'source' => 'manuel', 'dernier_import_at' => null,
                    ],
                ]);
            }
            return $user;
        });
    }
}
