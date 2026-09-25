<x-filament-panels::page>
    <div style="margin-bottom:1rem;">
        <x-filament::button tag="a" href="{{ $planningUrl }}" color="gray">← Retour au portail</x-filament::button>
    </div>

    <x-filament::section>
        <x-slot name="heading">Suivi de l'expression de besoin</x-slot>
        <div>Référence : <strong>{{ $besoin->code_besoin }}</strong></div>
        <div style="margin-top:.5rem;font-weight:800;">{{ $statutPublic['label'] }}</div>
        <div style="margin-top:.35rem;">{{ $statutPublic['description'] }}</div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Demande</x-slot>
        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem;">
            <div><strong>Stage</strong><br>{{ $besoin->stage?->libelle_court ?? 'Non renseigné' }}</div>
            <div><strong>Demandeur</strong><br>{{ $besoin->demandeur }}</div>
            <div><strong>Début souhaité</strong><br>{{ optional($besoin->date_debut_souhaitee)->format('d/m/Y') ?? $besoin->date_debut_souhaitee }}</div>
            <div><strong>Fin souhaitée</strong><br>{{ optional($besoin->date_fin_souhaitee)->format('d/m/Y') ?? $besoin->date_fin_souhaitee ?? 'Non renseignée' }}</div>
            <div><strong>Effectif</strong><br>{{ $besoin->nombre_stagiaires }}</div>
            <div><strong>Priorité</strong><br>{{ $besoin->priorite }}</div>
        </div>
    </x-filament::section>

    @if ($besoin->sessionStage)
        <x-filament::section>
            <x-slot name="heading">Session planifiée</x-slot>
            <div>{{ $besoin->sessionStage->code_session }}</div>
            <div>{{ $besoin->sessionStage->debut?->format('d/m/Y H:i') }} → {{ $besoin->sessionStage->fin?->format('d/m/Y H:i') }}</div>
            @if ($besoin->sessionStage->salle)
                <div>Salle : {{ $besoin->sessionStage->salle->nom }}</div>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
