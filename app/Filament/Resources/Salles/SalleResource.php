<?php

namespace Modules\FPSplanificationstage\Filament\Resources\Salles;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Modules\FPSplanificationstage\Filament\Concerns\RequiresAuthentication;
use Modules\FPSplanificationstage\Filament\Resources\Salles\Pages\CreateSalle;
use Modules\FPSplanificationstage\Filament\Resources\Salles\Pages\EditSalle;
use Modules\FPSplanificationstage\Filament\Resources\Salles\Pages\ListSalles;
use Modules\FPSplanificationstage\Filament\Resources\Salles\Pages\ViewSalle;
use Modules\FPSplanificationstage\Filament\Resources\Salles\Schemas\SalleForm;
use Modules\FPSplanificationstage\Filament\Resources\Salles\Schemas\SalleInfolist;
use Modules\FPSplanificationstage\Filament\Resources\Salles\Tables\SallesTable;
use Modules\FPSplanificationstage\Models\Salle;

class SalleResource extends Resource
{
    use RequiresAuthentication;

    protected static ?string $model =
        Salle::class;

    protected static ?string $navigationLabel =
        'Salles';

    protected static ?string $modelLabel =
        'salle';

    protected static ?string $pluralModelLabel =
        'salles';

    protected static string|\UnitEnum|null $navigationGroup =
        'Référentiels';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return SalleForm::configure(
            $schema
        );
    }

    public static function infolist(Schema $schema): Schema
    {
        return SalleInfolist::configure(
            $schema
        );
    }

    public static function table(Table $table): Table
    {
        return SallesTable::configure(
            $table
        );
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' =>
                ListSalles::route('/'),

            'create' =>
                CreateSalle::route('/create'),

            'view' =>
                ViewSalle::route('/{record}'),

            'edit' =>
                EditSalle::route('/{record}/edit'),
        ];
    }
}
