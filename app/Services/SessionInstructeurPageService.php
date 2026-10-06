<?php

namespace Modules\FPSplanificationstage\Services;

use Modules\FPSplanificationstage\Filament\Pages\EspaceInstructeur;
use Modules\FPSplanificationstage\Models\SessionStage;

class SessionInstructeurPageService
{
    public function session(int|string $sessionId): SessionStage
    {
        $instructeur = app(InstructeurConnecteService::class)->resolve();
        abort_unless($instructeur, 403);

        $session = SessionStage::query()->with(['stage', 'salle'])->findOrFail($sessionId);
        abort_unless(
            $session->instructeurs()->withoutGlobalScopes()
                ->where('rh_marins.id', $instructeur->getKey())->exists(),
            403
        );
        abort_unless($session->stage, 404);

        return $session;
    }

    public function detail(int|string $sessionId): array
    {
        $session = $this->session($sessionId);

        return [
            'session' => $session,
            'stage' => $session->stage,
            'stagiaires' => $session->inscriptions()
                ->with(['stagiaire.grade', 'stagiaire.unite'])
                ->whereNotIn('statut', ['refusee', 'annulee'])
                ->orderBy('candidat_nom')
                ->orderBy('candidat_prenom')
                ->get(),
            'retourUrl' => EspaceInstructeur::getUrl(panel: 'fpsplanificationstage'),
            'retourLabel' => 'Retour à mon espace formateur',
        ];
    }
}
