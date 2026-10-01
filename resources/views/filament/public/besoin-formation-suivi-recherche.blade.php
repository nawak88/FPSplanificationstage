<x-filament-panels::page>
    <x-filament::button tag="a" href="{{ \Modules\FPSplanificationstage\Filament\Pages\EspaceStagiaire\PlanningFormations::getUrl(panel: 'fpsplanificationstage') }}" color="gray">Retour au planning des formations</x-filament::button>
    <h1>Suivre une expression de besoin</h1>
    <form wire:submit="submit">
        {{ $this->form }}
        @error('data') <p role="alert">{{ $message }}</p> @enderror
        <x-filament::button type="submit">Afficher le suivi</x-filament::button>
    </form>
</x-filament-panels::page>
