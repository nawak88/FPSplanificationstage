<x-filament-panels::page>
    @php
        $besoinsCrees =
            session('besoins_crees', []);
    @endphp

    <x-filament::section>
        <x-slot name="heading">
            {{ count($besoinsCrees) > 1
                ? 'Expressions de besoin enregistrées'
                : 'Expression de besoin enregistrée' }}
        </x-slot>

        @if (count($besoinsCrees) > 1)
            <div style="margin-bottom:1rem;">
                {{ count($besoinsCrees) }}
                besoins ont été enregistrés avec succès.
            </div>

            <div style="display:grid;gap:.75rem;">
                @foreach ($besoinsCrees as $item)
                    <div style="border:1px solid #dbe2ea;border-radius:.65rem;padding:.8rem 1rem;">
                        <div style="font-weight:800;">
                            {{ $item['stage'] ?? 'Stage' }}
                        </div>

                        <div style="margin-top:.25rem;">
                            Référence :
                            <strong>{{ $item['code_besoin'] ?? '' }}</strong>
                        </div>

                        @if (! empty($item['public_token']))
                            <div style="margin-top:.6rem;">
                                <x-filament::button
                                    tag="a"
                                    size="sm"
                                    color="gray"
                                    href="{{ $item['suivi_url'] }}"
                                >
                                    Suivre ce besoin
                                </x-filament::button>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div>
                Référence :
                <strong>{{ $besoin->code_besoin }}</strong>
            </div>

            @if ($besoin->stage)
                <div style="margin-top:.5rem;">
                    Stage : {{ $besoin->stage->libelle_court }}
                </div>
            @endif

            <div style="margin-top:1rem;">
                <x-filament::button
                    tag="a"
                    href="{{ $suiviUrl }}"
                >
                    Suivre cette demande
                </x-filament::button>
            </div>
        @endif

        <div style="margin-top:1rem;">
            <x-filament::button tag="a" href="{{ $planningUrl }}" color="gray">
                Retour au planning
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-panels::page>
