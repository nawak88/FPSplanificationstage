<x-filament-panels::page>
    <div style="margin-bottom:1rem;">
        <x-filament::button tag="a" href="{{ $retourUrl }}" color="gray">← {{ $retourLabel }}</x-filament::button>
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
            <x-slot name="heading">Stagiaires inscrits ({{ $stagiaires->count() }})</x-slot>
            <p>Les candidatures refusées ou annulées ne sont pas affichées.</p>
            @forelse ($stagiaires as $inscription)
                <div style="margin-top:.75rem;">
                    <strong>{{ $inscription->nom_complet }}</strong>
                    <div>{{ $inscription->grade ?? 'Grade non renseigné' }} — {{ $inscription->unite ?? 'Unité non renseignée' }}</div>
                    <div style="margin-top:.5rem;">
                        Résultat : {{ app(\Modules\FPSplanificationstage\Services\CompteRenduFinStageService::class)->resultat($inscription) }}
                        @if ($inscription->note_fin_stage !== null)
                            <span> — Note : {{ $inscription->note_fin_stage }}/20</span>
                        @endif
                        @if ($inscription->date_attribution)
                            <span> — Attribué le {{ $inscription->date_attribution->format('d/m/Y') }}</span>
                        @endif
                    </div>
                    @if ($peutSaisirResultats)
                        <x-filament::button size="sm" color="gray"
                            wire:click="mountAction('resultat', { inscription: {{ $inscription->getKey() }} })">
                            {{ $inscription->note_fin_stage === null ? 'Saisir le résultat' : 'Modifier le résultat' }}
                        </x-filament::button>
                    @endif
                </div>
            @empty
                <p>Aucun stagiaire inscrit à cette session.</p>
            @endforelse
        </x-filament::section>

</x-filament-panels::page>
