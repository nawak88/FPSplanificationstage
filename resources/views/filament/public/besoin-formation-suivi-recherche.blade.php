<x-filament-panels::page>
    <div style="margin-bottom:1rem;">
        <x-filament::button tag="a" href="{{ $planningUrl }}" color="gray">← Retour au portail</x-filament::button>
    </div>

    <x-filament::section>
        <x-slot name="heading">Suivre une expression de besoin</x-slot>
        <div>Renseignez la référence du besoin et l'adresse e-mail utilisée lors de la demande.</div>
    </x-filament::section>

    @include('fpsplanificationstage::filament.public._errors')

    <form method="POST" action="{{ route('fpsplanificationstage.public.besoin.suivi.rechercher', [], false) }}">
        @csrf
        <x-filament::section>
            <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem;">
                <div>
                    <label style="display:block;font-weight:700;margin-bottom:.35rem;">Référence du besoin *</label>
                    <x-filament::input.wrapper>
                        <x-filament::input name="code_besoin" value="{{ old('code_besoin') }}" placeholder="BES-000001" />
                    </x-filament::input.wrapper>
                </div>
                <div>
                    <label style="display:block;font-weight:700;margin-bottom:.35rem;">E-mail *</label>
                    <x-filament::input.wrapper>
                        <x-filament::input type="email" name="contact_email" value="{{ old('contact_email') }}" />
                    </x-filament::input.wrapper>
                </div>
            </div>
        </x-filament::section>
        <div style="margin-top:1rem;">
            <x-filament::button type="submit">Afficher le suivi</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
