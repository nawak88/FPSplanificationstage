<x-filament-panels::page>
    <div style="margin-bottom:1rem;">
        <x-filament::button tag="a" href="{{ $retourUrl }}" color="gray">← Retour au planning des formations</x-filament::button>
    </div>

    <x-filament::section>
        <div style="font-weight:800;color:#2563eb;">{{ $stage->code_stage ?? '' }}</div>
        <div style="font-size:2rem;font-weight:900;margin-top:.35rem;">{{ $stage->libelle_court }}</div>
        @if ($stage->libelle_long)
            <div style="margin-top:1rem;">{{ $stage->libelle_long }}</div>
        @endif
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Session</x-slot>
        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem;">
            <div><strong>Début</strong><br>{{ $session->debut?->format('d/m/Y H:i') }}</div>
            <div><strong>Fin</strong><br>{{ $session->fin?->format('d/m/Y H:i') }}</div>
            <div><strong>Lieu</strong><br>{{ $session->salle?->nom ?? $stage->lieux_formation ?? 'Non renseigné' }}</div>
            <div><strong>Places restantes</strong><br>{{ $session->places_restantes ?? 'Non renseigné' }}</div>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Description de la formation</x-slot>
        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem;">
            <div><strong>Objectif</strong><br>{{ $stage->objectif ?: 'Non renseigné' }}</div>
            <div><strong>Fonctions visées</strong><br>{{ $stage->fonctions_visees ?: 'Non renseignées' }}</div>
            <div><strong>Service émetteur</strong><br>{{ $stage->service_emetteur ?: 'Non renseigné' }}</div>
            <div><strong>Durée</strong><br>{{ $stage->duree_jours ?? 'Non renseignée' }}{{ $stage->duree_jours !== null ? ' jour' : '' }}</div>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Pré-requis</x-slot>
        @forelse ($stage->prerequis as $prerequis)
            <div style="margin-bottom:.75rem;">
                <strong>{{ $prerequis->libelle ?? $prerequis->nom ?? 'Pré-requis' }}</strong>
                @if ($prerequis->obligatoire)
                    <span style="color:#b91c1c;"> — obligatoire</span>
                @endif
            </div>
        @empty
            <div>Aucun pré-requis renseigné.</div>
        @endforelse
    </x-filament::section>

    @if ($inscriptionPossible)
        <x-filament::section>
            <x-filament::button tag="a" href="{{ $inscriptionUrl }}">
                S'inscrire à cette session
            </x-filament::button>
        </x-filament::section>
    @endif
</x-filament-panels::page>
