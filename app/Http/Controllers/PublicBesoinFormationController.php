<?php

namespace Modules\FPSplanificationstage\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\FPSplanificationstage\Filament\Public\Pages\BesoinConfirmation;
use Modules\FPSplanificationstage\Filament\Public\Pages\BesoinSuivi;
use Modules\FPSplanificationstage\Models\BesoinFormation;
use Modules\FPSplanificationstage\Services\BesoinPeriodeService;
use Modules\RH\Models\Unite;

class PublicBesoinFormationController extends Controller
{
    public function store(
        Request $request
    ): RedirectResponse {
        if (
            ! $request->has('besoins')
            && $request->has('stage_id')
        ) {
            $request->merge([
                'besoins' => [[
                    'stage_id' =>
                        $request->input('stage_id'),

                    'type_periode' =>
                        $request->input('type_periode'),

                    'date_debut_souhaitee' =>
                        $request->input('date_debut_souhaitee'),

                    'date_fin_souhaitee' =>
                        $request->input('date_fin_souhaitee'),

                    'nombre_stagiaires' =>
                        $request->input('nombre_stagiaires'),

                    'commentaire' =>
                        $request->input('commentaire'),
                ]],
            ]);
        }

        $validated =
            $request->validate(
                [
                    'demandeur' => [
                        'required',
                        'string',
                        'max:255',
                        Rule::exists(
                            Unite::class,
                            'libelle_long'
                        ),
                    ],

                    'contact_nom' => [
                        'required',
                        'string',
                        'max:255',
                    ],

                    'contact_email' => [
                        'required',
                        'email',
                        'max:255',
                    ],

                    'contact_telephone' => [
                        'nullable',
                        'string',
                        'max:255',
                    ],

                    'besoins' => [
                        'required',
                        'array',
                        'min:1',
                        'max:20',
                    ],

                    'besoins.*.stage_id' => [
                        'required',
                        'integer',

                        Rule::exists(
                            'stages',
                            'id'
                        )->where(
                            fn ($query) =>
                                $query->where(
                                    'actif',
                                    true
                                )
                        ),
                    ],

                    'besoins.*.type_periode' => [
                        'required',
                        Rule::in([
                            'dates_fixes',
                            'plage',
                            'plage_demarrage',
                        ]),
                    ],

                    'besoins.*.date_debut_souhaitee' => [
                        'required',
                        'date',
                        'after_or_equal:today',
                    ],

                    'besoins.*.date_fin_souhaitee' => [
                        'nullable',
                        'date',
                    ],

                    'besoins.*.nombre_stagiaires' => [
                        'required',
                        'integer',
                        'min:1',
                        'max:999',
                    ],

                    'besoins.*.commentaire' => [
                        'nullable',
                        'string',
                        'max:5000',
                    ],
                ]
            );

        foreach (
            $validated['besoins']
            as $index => $besoinData
        ) {
            $periodeError =
                BesoinPeriodeService::validateValues(
                    $besoinData['stage_id'],
                    $besoinData['type_periode'],
                    $besoinData['date_debut_souhaitee'],
                    $besoinData['date_fin_souhaitee'] ?? null
                );

            if ($periodeError !== null) {
                throw ValidationException::withMessages([
                    "besoins.$index.date_fin_souhaitee" =>
                        $periodeError,
                ]);
            }
        }

        $created =
            DB::transaction(
                function () use (
                    $validated
                ): array {
                    $created = [];

                    foreach (
                        $validated['besoins']
                        as $besoinData
                    ) {
                        $besoin =
                            BesoinFormation::create([
                                'stage_id' =>
                                    $besoinData[
                                        'stage_id'
                                    ],

                                'demandeur' =>
                                    $validated[
                                        'demandeur'
                                    ],

                                'contact_nom' =>
                                    $validated[
                                        'contact_nom'
                                    ],

                                'contact_email' =>
                                    $validated[
                                        'contact_email'
                                    ],

                                'contact_telephone' =>
                                    $validated[
                                        'contact_telephone'
                                    ]
                                    ?? null,

                                'type_periode' =>
                                    $besoinData[
                                        'type_periode'
                                    ],

                                'date_debut_souhaitee' =>
                                    $besoinData[
                                        'date_debut_souhaitee'
                                    ],

                                'date_fin_souhaitee' =>
                                    $besoinData[
                                        'date_fin_souhaitee'
                                    ]
                                    ?? null,

                                'priorite' =>
                                    'normale',

                                'nombre_stagiaires' =>
                                    $besoinData[
                                        'nombre_stagiaires'
                                    ],

                                'statut' =>
                                    'a_planifier',

                                'session_stage_id' =>
                                    null,

                                'commentaire' =>
                                    $besoinData[
                                        'commentaire'
                                    ]
                                    ?? null,

                                'source' =>
                                    'portail',

                                'public_token' =>
                                    (string) Str::uuid(),
                            ]);

                        $besoin->load('stage');

                        $created[] = [
                            'code_besoin' =>
                                $besoin
                                    ->code_besoin,

                            'public_token' =>
                                $besoin
                                    ->public_token,

                            'stage' =>
                                $besoin
                                    ->stage
                                    ?->libelle_court
                                ?? 'Stage',

                            'suivi_url' =>
                                BesoinSuivi::getUrl(
                                    [
                                        'token' =>
                                            $besoin
                                                ->public_token,
                                    ],
                                    panel:
                                        'fpsplanificationstage'
                                ),
                        ];
                    }

                    return $created;
                }
            );

        $first =
            $created[0];

        return redirect()
            ->to(
                BesoinConfirmation::getUrl(
                    [
                        'token' =>
                            $first[
                                'public_token'
                            ],
                    ],
                    panel:
                        'fpsplanificationstage'
                )
            )
            ->with(
                'besoins_crees',
                $created
            );
    }

    public function rechercherSuivi(
        Request $request
    ): RedirectResponse {
        $validated =
            $request->validate(
                [
                    'code_besoin' => [
                        'required',
                        'string',
                        'max:255',
                    ],

                    'contact_email' => [
                        'required',
                        'email',
                        'max:255',
                    ],
                ]
            );

        $code =
            mb_strtoupper(
                trim(
                    $validated[
                        'code_besoin'
                    ]
                )
            );

        $email =
            trim(
                $validated[
                    'contact_email'
                ]
            );

        $besoin =
            BesoinFormation::query()
                ->where(
                    'source',
                    'portail'
                )
                ->where(
                    'code_besoin',
                    $code
                )
                ->where(
                    'contact_email',
                    $email
                )
                ->whereNotNull(
                    'public_token'
                )
                ->first();

        if (! $besoin) {
            throw ValidationException::withMessages([
                'code_besoin' =>
                    'La référence ou l’adresse e-mail ne correspond à aucune expression de besoin.',
            ]);
        }

        return redirect()->to(
            BesoinSuivi::getUrl(
                [
                    'token' =>
                        $besoin->public_token,
                ],
                panel:
                    'fpsplanificationstage'
            )
        );
    }
}
