<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\FPSplanificationstage\Models\Marin;
use Modules\FPSplanificationstage\Models\Stage;
use Modules\FPSplanificationstage\Services\InstructeurConnecteService;

uses(Tests\TestCase::class, RefreshDatabase::class)->group('FPSplanificationstage');

beforeEach(fn () => Queue::fake());

it('utilise la même table que le modèle RH et reconnaît l utilisateur désigné', function () {
    $user = User::factory()->create();
    $rhMarin = \Modules\RH\Models\Marin::factory()->create(['user_id' => $user->id]);
    $marin = Marin::withoutGlobalScopes()->findOrFail($rhMarin->id);

    expect($marin)->toBeInstanceOf(\Modules\RH\Models\Marin::class)
        ->and($marin->getTable())->toBe('rh_marins')
        ->and($marin->estInstructeur())->toBeFalse()
        ->and(Marin::utilisateurCourantEstInstructeur())->toBeFalse();

    $stage = Stage::create(['libelle_court' => 'Stage instructeur', 'actif' => true]);
    $stage->instructeurs()->attach($marin);
    $this->actingAs($user);

    expect($marin->estInstructeur())->toBeTrue()
        ->and(Marin::utilisateurCourantEstInstructeur())->toBeTrue()
        ->and(Marin::utilisateurEstInstructeur($user))->toBeTrue()
        ->and(app(InstructeurConnecteService::class)->resolve())->toBeInstanceOf(Marin::class)
        ->and($stage->instructeurs()->withoutGlobalScopes()->sole())->toBeInstanceOf(Marin::class);

    $stage->instructeurs()->detach($marin);
    expect(Marin::utilisateurCourantEstInstructeur())->toBeFalse();
});

it('ne confond pas les identifiants User et Marin', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $rhMarin = \Modules\RH\Models\Marin::factory()->create(['user_id' => $other->id]);
    $stage = Stage::create(['libelle_court' => 'Autre stage', 'actif' => true]);
    $stage->instructeurs()->attach($rhMarin);
    $this->actingAs($user);

    expect(Marin::utilisateurCourantEstInstructeur())->toBeFalse()
        ->and(Marin::utilisateurEstInstructeur(null))->toBeFalse()
        ->and((new Marin())->estInstructeur())->toBeFalse();
});

it('conserve la résolution par identité pour une fiche RH non liée', function () {
    $user = User::factory()->create();
    $rhMarin = \Modules\RH\Models\Marin::factory()->create([
        'user_id' => null, 'email' => $user->email, 'nom' => $user->nom, 'prenom' => $user->prenom,
    ]);
    $stage = Stage::create(['libelle_court' => 'Stage identité', 'actif' => true]);
    $stage->instructeurs()->attach($rhMarin);

    expect(app(InstructeurConnecteService::class)->resolve($user))->toBeInstanceOf(Marin::class)
        ->and(Marin::utilisateurEstInstructeur($user))->toBeTrue();
});
