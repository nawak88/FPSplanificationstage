<?php

namespace Modules\FPSplanificationstage\Filament\Pages;

use App\Models\User;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Auth;
use Modules\FPSplanificationstage\Filament\Widgets\ActiviteInstructeurStats;
use Modules\FPSplanificationstage\Filament\Widgets\CalendrierInstructeur;
use Modules\FPSplanificationstage\Filament\Widgets\SessionsInstructeurTable;
use Modules\FPSplanificationstage\Services\InstructeurConnecteService;

class EspaceInstructeur extends BaseDashboard
{
    protected static string $routePath =
        '/espace-instructeur';

    protected static ?string $navigationLabel =
        'Mon activité';

    protected static string|\UnitEnum|null $navigationGroup =
        'Espace instructeur';

    protected static string|\BackedEnum|null $navigationIcon =
        'heroicon-o-presentation-chart-line';

    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        return app(
            InstructeurConnecteService::class
        )->peutAccederEspaceInstructeur(
            $use
        );
    }

    public function getTitle(): string
    {
        return 'Mon espace instructeur';
    }

    public function getColumns(): int|array
    {
        return 1;
    }

    public function getWidgets(): array
    {
        return [
            ActiviteInstructeurStats::class,
            CalendrierInstructeur::class,
            SessionsInstructeurTable::class,
        ];
    }
}
