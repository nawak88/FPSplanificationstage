<?php

namespace Modules\FPSplanificationstage\Filament\Resources\Stagiaires;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Modules\FPSplanificationstage\Filament\Concerns\RequiresAuthentication;
use Modules\FPSplanificationstage\Filament\Resources\Stagiaires\Pages\ListStagiaires;
use Modules\FPSplanificationstage\Filament\Resources\Stagiaires\Pages\ViewStagiaire;
use Modules\FPSplanificationstage\Filament\Resources\Stagiaires\Tables\StagiairesTable;
use Modules\RH\Models\Marin;

class StagiaireResource extends Resource
{
    use RequiresAuthentication;

    protected static ?string $model =
        Marin::class;

    protected static ?string $navigationLabel =
        'Historique';

    protected static string|\UnitEnum|null $navigationGroup =
        'Inscriptions / Admission';

    protected static ?int $navigationSort = 30;

    protected static string|\BackedEnum|null $navigationIcon =
        'heroicon-o-academic-cap';

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::query()
            ->count();
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        return $user !== null
            && (
                $user->can(
                    'fpsplanificationstage::gerer_le_module'
                )
                || $user->can(
                    'rh::marins.index'
                )
            );
    }

    public static function canView(
        Model $record
    ): bool {
        return static::canViewAny();
    }

    public static function form(
        Schema $schema
    ): Schema {
        return $schema;
    }

    public static function table(
        Table $table
    ): Table {
        return StagiairesTable::configure(
            $table
        );
    }

    public static function getModelLabel(): string
    {
        return 'stagiaire';
    }

    public static function getPluralModelLabel(): string
    {
        return 'stagiaires';
    }

    public static function getPages(): array
    {
        return [
            'index' =>
                ListStagiaires::route('/'),

            'view' =>
                ViewStagiaire::route(
                    '/{record}'
                ),
        ];
    }
}
