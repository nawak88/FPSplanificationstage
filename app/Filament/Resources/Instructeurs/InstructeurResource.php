<?php

namespace Modules\FPSplanificationstage\Filament\Resources\Instructeurs;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\FPSplanificationstage\Filament\Concerns\RequiresAuthentication;
use Modules\FPSplanificationstage\Filament\Resources\Instructeurs\Pages\ListInstructeurs;
use Modules\FPSplanificationstage\Filament\Resources\Instructeurs\Pages\ViewInstructeur;
use Modules\FPSplanificationstage\Filament\Resources\Instructeurs\Schemas\InstructeurInfolist;
use Modules\FPSplanificationstage\Filament\Resources\Instructeurs\Tables\InstructeursTable;
use Modules\FPSplanificationstage\Services\InstructeurConnecteService;
use App\Models\User;

class InstructeurResource extends Resource
{
    use RequiresAuthentication;

    protected static ?string $model =
        User::class;

    protected static ?string $navigationLabel =
        'Instructeurs';

    protected static ?string $modelLabel =
        'instructeur';

    protected static ?string $pluralModelLabel =
        'instructeurs';

    protected static string|\UnitEnum|null $navigationGroup =
        'Référentiels';

    protected static ?int $navigationSort = 20;

    public static function getEloquentQuery(): Builder
    {
        return app(
            InstructeurConnecteService::class
        )->seulementInstructeurs(
            parent::getEloquentQuery()
        );
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function infolist(Schema $schema): Schema
    {
        return InstructeurInfolist::configure(
            $schema
        );
    }

    public static function table(Table $table): Table
    {
        return InstructeursTable::configure(
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
                ListInstructeurs::route('/'),

            'view' =>
                ViewInstructeur::route('/{record}'),
        ];
    }
}
