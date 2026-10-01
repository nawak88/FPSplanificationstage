<x-filament-panels::page>
    <x-filament::button tag="a" href="{{ \Modules\FPSplanificationstage\Filament\Pages\EspaceStagiaire\PlanningFormations::getUrl(panel: 'fpsplanificationstage') }}" color="gray">Retour au planning des formations</x-filament::button>
    <h1>Inscription au stage</h1>
    <x-filament::section>
        <x-slot name="heading">{{ $session->stage?->libelle_court }}</x-slot>
        <div>Session : {{ $session->code_session }}</div>
        <div>Du {{ $session->debut?->format('d/m/Y H:i') }} au {{ $session->fin?->format('d/m/Y H:i') }}</div>
        <x-filament::button tag="a" href="{{ $sessionUrl }}" color="gray">Retour à la session</x-filament::button>
    </x-filament::section>
    <form wire:submit="submit">
        {{ $this->form }}
        @error('data') <p role="alert">{{ $message }}</p> @enderror
        <x-filament::button type="submit">Envoyer ma candidature</x-filament::button>
    </form>
</x-filament-panels::page>
