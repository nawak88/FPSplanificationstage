<?php

namespace Modules\FPSplanificationstage\Filament\Resources\BesoinFormations;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Modules\FPSplanificationstage\Filament\Concerns\RequiresAuthentication;
use Modules\FPSplanificationstage\Filament\Resources\BesoinFormations\Pages\CreateBesoinFormation;
use Modules\FPSplanificationstage\Filament\Resources\BesoinFormations\Pages\EditBesoinFormation;
use Modules\FPSplanificationstage\Filament\Resources\BesoinFormations\Pages\ListBesoinFormations;
use Modules\FPSplanificationstage\Filament\Resources\BesoinFormations\Pages\ViewBesoinFormation;
use Modules\FPSplanificationstage\Filament\Resources\BesoinFormations\Schemas\BesoinFormationForm;
use Modules\FPSplanificationstage\Filament\Resources\BesoinFormations\Schemas\BesoinFormationInfolist;
use Modules\FPSplanificationstage\Filament\Resources\BesoinFormations\Tables\BesoinFormationsTable;
use Modules\FPSplanificationstage\Models\BesoinFormation;

class BesoinFormationResource extends Resource
{
    use RequiresAuthentication;

    protected static ?string $model =
        BesoinFormation::class;

    protected static ?string $navigationLabel =
        'Besoins de formation';

    protected static ?string $modelLabel =
        'besoin de formation';

    protected static ?string $pluralModelLabel =
        'besoins de formation';

    protected static string|\UnitEnum|null $navigationGroup =
        'Planification';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return BesoinFormationForm::configure(
            $schema
        );
    }

    public static function infolist(Schema $schema): Schema
    {
        return BesoinFormationInfolist::configure(
            $schema
        );
    }

    public static function table(Table $table): Table
    {
        return BesoinFormationsTable::configure(
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
                ListBesoinFormations::route('/'),

            'create' =>
                CreateBesoinFormation::route('/create'),

            'view' =>
                ViewBesoinFormation::route('/{record}'),

            'edit' =>
                EditBesoinFormation::route('/{record}/edit'),
        ];
    }
}
