<?php

namespace Modules\FPSplanificationstage\Filament\Resources\SessionStages;

use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Modules\FPSplanificationstage\Filament\Concerns\RequiresAuthentication;
use Modules\FPSplanificationstage\Filament\Resources\SessionStages\Pages\CreateSessionStage;
use Modules\FPSplanificationstage\Filament\Resources\SessionStages\Pages\EditSessionStage;
use Modules\FPSplanificationstage\Filament\Resources\SessionStages\Pages\ListSessionStages;
use Modules\FPSplanificationstage\Filament\Resources\SessionStages\Pages\PlanningSessionStages;
use Modules\FPSplanificationstage\Filament\Resources\SessionStages\Pages\ViewSessionStage;
use Modules\FPSplanificationstage\Filament\Resources\SessionStages\RelationManagers\InscriptionsRelationManager;
use Modules\FPSplanificationstage\Filament\Resources\SessionStages\Schemas\SessionStageForm;
use Modules\FPSplanificationstage\Filament\Resources\SessionStages\Schemas\SessionStageInfolist;
use Modules\FPSplanificationstage\Filament\Resources\SessionStages\Tables\SessionStagesTable;
use Modules\FPSplanificationstage\Models\SessionStage;

use function Filament\Support\original_request;

class SessionStageResource extends Resource
{
    use RequiresAuthentication;

    protected static ?string $model =
        SessionStage::class;

    protected static ?string $navigationLabel =
        'Sessions de stages';

    protected static ?string $modelLabel =
        'session de stage';

    protected static ?string $pluralModelLabel =
        'sessions de stages';

    protected static string|\UnitEnum|null $navigationGroup =
        'Planification';

    protected static ?int $navigationSort =
        20;

    public static function form(
        Schema $schema
    ): Schema {
        return SessionStageForm::configure(
            $schema
        );
    }

    public static function infolist(
        Schema $schema
    ): Schema {
        return SessionStageInfolist::configure(
            $schema
        );
    }

    public static function table(
        Table $table
    ): Table {
        return SessionStagesTable::configure(
            $table
        );
    }

    public static function getRelations(): array
    {
        return [
            InscriptionsRelationManager::class,
        ];
    }

    public static function getNavigationItems(): array
    {
        $routeBaseName = static::getRouteBaseName();

        return [
            NavigationItem::make('Sessions de stages')
                ->key(static::class)
                ->group(static::getNavigationGroup())
                ->sort(static::getNavigationSort())
                ->url(static::getUrl())
                ->isActiveWhen(fn (): bool => original_request()->routeIs(
                    $routeBaseName . '.index',
                    $routeBaseName . '.create',
                    $routeBaseName . '.view',
                    $routeBaseName . '.edit',
                )),
            NavigationItem::make('Planning / Calendrier')
                ->key(static::class . '.planning')
                ->group(static::getNavigationGroup())
                ->sort((static::getNavigationSort() ?? 20) + 10)
                ->url(static::getUrl('planning'))
                ->isActiveWhen(
                    fn (): bool => original_request()->routeIs(
                        $routeBaseName . '.planning'
                    )
                ),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' =>
                ListSessionStages::route('/'),

            'create' =>
                CreateSessionStage::route('/create'),

            'planning' =>
                PlanningSessionStages::route('/planning'),

            'view' =>
                ViewSessionStage::route('/{record}'),

            'edit' =>
                EditSessionStage::route('/{record}/edit'),
        ];
    }
}
