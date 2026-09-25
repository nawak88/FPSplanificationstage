<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Candidature enregistrée</x-slot>
        <div>Référence : <strong>{{ $inscription->code_inscription }}</strong></div>
        <div style="margin-top:.5rem;">Statut : {{ $inscription->statut }}</div>
        @if ($inscription->sessionStage?->stage)
            <div style="margin-top:.5rem;">Stage : {{ $inscription->sessionStage->stage->libelle_court }}</div>
        @endif
        @if ($inscription->stage_deja_effectue)
            <div style="margin-top:1rem;padding:1rem;border:1px solid #fbbf24;border-radius:.75rem;background:#fffbeb;color:#92400e;">
                <strong>Candidature non prioritaire :</strong>
                vous avez déjà effectué ce stage.
            </div>
        @endif
        <div style="margin-top:1rem;">
            <x-filament::button tag="a" href="{{ $planningUrl }}">Retour au portail</x-filament::button>
        </div>
    </x-filament::section>
</x-filament-panels::page>
