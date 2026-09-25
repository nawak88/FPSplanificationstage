<?php

namespace Modules\FPSplanificationstage\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\FPSplanificationstage\Filament\Pages\EspaceStagiaire\PlanningFormations;
use Modules\FPSplanificationstage\Models\Inscription;
use Modules\FPSplanificationstage\Models\InscriptionPrerequis;
use Modules\FPSplanificationstage\Models\SessionStage;
use Modules\FPSplanificationstage\Services\CandidatureStageDejaEffectueNotifier;
use Modules\FPSplanificationstage\Services\InscriptionPdfService;
use Modules\FPSplanificationstage\Services\PublicInscriptionPageService;
use Modules\FPSplanificationstage\Services\StagiaireResolver;
use Modules\RH\Models\Brevet;
use Modules\RH\Models\Grade;
use Modules\RH\Models\Marin;
use Modules\RH\Models\Specialite;

class PublicInscriptionController extends Controller
{
    public function store(
        Request $request,
        SessionStage $session
    ): RedirectResponse {
        app(
            PublicInscriptionPageService::class
        )->ensureSessionIsRegistrable(
            $session
        );

        /** @var User $user */
        $user = $request->user();

        abort_unless(
            $user,
            403
        );

        $request->merge([
            'nom' =>
                $user->nom,

            'prenom' =>
                $user->prenom,

            'email' =>
                $user->email,
        ]);

        $session->load([
            'stage.prerequis' =>
                fn ($query) =>
                    $query
                        ->where(
                            'actif',
                            true
                        )
                        ->orderBy(
                            'ordre'
                        ),
        ]);

        $validated =
            $request->validate([

                /*
                 * INSCRIPTION_IDENTITE_V1_VALIDATION
                 */
                'matricule' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'nid' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'brevet' => [
                    'nullable',
                    Rule::exists(
                        Brevet::class,
                        'libelle_court'
                    ),
                ],

                'specialite' => [
                    'nullable',
                    'string',
                    'max:255',
                    Rule::exists(
                        Specialite::class,
                        'libelle_court'
                    ),
                ],

                'nom' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'prenom' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'grade' => [
                    'nullable',
                    Rule::exists(
                        Grade::class,
                        'libelle_court'
                    ),
                ],

                'unite' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'email' => [
                    'required',
                    'email',
                    'max:255',
                ],

                'prerequis' => [
                    'nullable',
                    'array',
                ],

                'prerequis.*' => [
                    'nullable',
                ],

                'demande_derogation' => [
                    'nullable',
                    'boolean',
                ],

                'derogation_motif' => [
                    'nullable',
                    'string',
                    'max:3000',
                ],
            ]);

        $stagiaire =
            Marin::fromUser(
                $user
            )
            ?? app(
                StagiaireResolver::class
            )->find(
                $validated
            );

        $dejaInscrit =
            Inscription::query()
                ->where(
                    'session_stage_id',
                    $session->id
                )
                ->duCandidat(
                    $user->getKey(),
                    $validated['email'],
                    $stagiaire?->getKey()
                )
                ->whereNotIn(
                    'statut',
                    [
                        'annulee',
                        'refusee',
                    ]
                )
                ->exists();

        if ($dejaInscrit) {
            throw ValidationException::withMessages([
                'email' =>
                    'Une inscription existe déjà pour cette adresse e-mail sur cette session.',
            ]);
        }

        $reponsesPrerequis =
            $validated['prerequis']
            ?? [];

        $prerequisManquants = [];

        foreach (
            $session->stage->prerequis
            as $prerequis
        ) {
            $respecte =
                isset(
                    $reponsesPrerequis[
                        $prerequis->id
                    ]
                )
                && (bool)
                    $reponsesPrerequis[
                        $prerequis->id
                    ];

            if (
                $prerequis->obligatoire
                && ! $respecte
            ) {
                $prerequisManquants[] =
                    $prerequis;
            }
        }

        $demandeDerogation =
            (bool) (
                $validated[
                    'demande_derogation'
                ]
                ?? false
            );

        if (
            $prerequisManquants !== []
            && ! $demandeDerogation
        ) {
            throw ValidationException::withMessages([
                'demande_derogation' =>
                    'Vous ne remplissez pas tous les prérequis obligatoires. Vous devez demander une dérogation pour poursuivre l’inscription.',
            ]);
        }

        if (
            $demandeDerogation
            && empty(
                trim(
                    $validated[
                        'derogation_motif'
                    ]
                    ?? ''
                )
            )
        ) {
            throw ValidationException::withMessages([
                'derogation_motif' =>
                    'Merci d’indiquer la justification de votre demande de dérogation.',
            ]);
        }

        $inscription =
            DB::transaction(
                function () use (
                    $validated,
                    $session,
                    $stagiaire,
                    $user,
                    $demandeDerogation,
                    $prerequisManquants,
                    $reponsesPrerequis
                ): Inscription {
                    $statut =
                        $prerequisManquants !== []
                            ? 'attente_derogation'
                            : 'attente_nemo';

                    $inscription =
                        Inscription::create([
                            'session_stage_id' =>
                                $session->id,


                            'stagiaire_id' =>
                                $stagiaire?->getKey(),

                            'candidat_user_id' =>
                                $user->getKey(),

                            'candidat_nom' =>
                                $validated['nom'],

                            'candidat_prenom' =>
                                $validated['prenom'],

                            'candidat_email' =>
                                $validated['email'],

                            'candidat_matricule' =>
                                $validated['matricule']
                                ?? null,

                            'candidat_nid' =>
                                $validated['nid']
                                ?? null,

                            'candidat_grade' =>
                                $validated['grade']
                                ?? null,

                            'candidat_brevet' =>
                                $validated['brevet']
                                ?? null,

                            'candidat_specialite' =>
                                $validated['specialite']
                                ?? null,

                            'candidat_unite' =>
                                $validated['unite'],

                            'statut' =>
                                $statut,

                            'nemo_recu' =>
                                false,

                            'derogation_demandee' =>
                                $demandeDerogation,

                            'derogation_statut' =>
                                $demandeDerogation
                                    ? 'en_attente'
                                    : null,

                            'derogation_motif' =>
                                $demandeDerogation
                                    ? $validated[
                                        'derogation_motif'
                                    ]
                                    : null,

                            'source' =>
                                'public',
                        ]);

                    foreach (
                        $session
                            ->stage
                            ->prerequis
                        as $prerequis
                    ) {
                        $respecte =
                            isset(
                                $reponsesPrerequis[
                                    $prerequis->id
                                ]
                            )
                            && (bool)
                                $reponsesPrerequis[
                                    $prerequis->id
                                ];

                        InscriptionPrerequis::create([
                            'inscription_id' =>
                                $inscription->id,

                            'prerequis_stage_id' =>
                                $prerequis->id,

                            'respecte' =>
                                $respecte,
                        ]);
                    }

                    return $inscription;
                }
            );

        /*
         * On revient directement au portail.
         *
         * Le calendrier est donc recalculé
         * immédiatement avec le nouveau nombre
         * de places disponibles.
         */
                /*
         * PDF_CANDIDATURE_SIGNED_URL_V1
         *
         * Le PDF contient des données personnelles :
         * le lien n'est donc valable qu'une heure et sa signature
         * empêche le téléchargement par simple devinette d'un code INS.
         */
        $pdfUrl =
            URL::temporarySignedRoute(
                'fpsplanificationstage.public.inscription.pdf',
                now()->addHour(),
                [
                    'code' =>
                        $inscription
                            ->code_inscription,
                ],
                false
            );
        $redirect =
            redirect()->to(
                PlanningFormations::getUrl(
                    panel:
                        'fpsplanificationstage'
                )
            )
            ->with(
                'inscription_success',
                'Votre candidature a bien été enregistrée.'
            )
            ->with(
                'inscription_code',
                $inscription->code_inscription
            )
            ->with(
                'inscription_pdf_url',
                $pdfUrl
            );

        if (
            $inscription
                ->stage_deja_effectue
        ) {
            app(
                CandidatureStageDejaEffectueNotifier::class
            )->notifier(
                $inscription
            );

            $redirect->with(
                'inscription_warning',
                'Vous avez déjà effectué ce stage. Votre candidature est enregistrée, mais elle ne sera pas prioritaire.'
            );
        }

        return $redirect;
    }

    public function pdf(
        Request $request,
        string $code
    ): Response {
        if (
            ! URL::hasValidSignature(
                $request,
                false
            )
        ) {
            abort(403);
        }

        $inscription =
            app(
                PublicInscriptionPageService::class
            )->findByCode(
                $code
            );

        $pdf =
            app(
                InscriptionPdfService::class
            )->render(
                $inscription
            );

        $filename =
            'candidature-stage-'
            . $inscription
                ->code_inscription
            . '.pdf';

        return response(
            $pdf,
            200,
            [
                'Content-Type' =>
                    'application/pdf',

                'Content-Disposition' =>
                    'attachment; filename="'
                    . $filename
                    . '"',

                'Cache-Control' =>
                    'private, no-store, max-age=0',

                'X-Content-Type-Options' =>
                    'nosniff',
            ]
        );
    }
}
