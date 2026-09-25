<?php

namespace Modules\FPSplanificationstage\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Modules\FPSplanificationstage\Filament\Concerns\RequiresAuthentication;
use Modules\FPSplanificationstage\Filament\Widgets\TableauBordSemaine;

class Dashboard extends BaseDashboard
{
    use RequiresAuthentication;

    protected static ?string $navigationLabel =
        'Tableau de bord';

    protected static ?int $navigationSort =
        -100;

    public function getTitle(): string
    {
        return 'Tableau de bord';
    }

    public function getColumns(): int | array
    {
        return 1;
    }

    public function getWidgets(): array
    {
        return [
            TableauBordSemaine::class,
        ];
    }
}
