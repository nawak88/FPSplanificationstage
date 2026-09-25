<?php

namespace Modules\FPSplanificationstage\Filament\Resources\IndisponibiliteInstructeurs;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Modules\FPSplanificationstage\Filament\Concerns\RequiresAuthentication;
use Modules\FPSplanificationstage\Filament\Resources\IndisponibiliteInstructeurs\Pages\CreateIndisponibiliteInstructeur;
use Modules\FPSplanificationstage\Filament\Resources\IndisponibiliteInstructeurs\Pages\EditIndisponibiliteInstructeur;
use Modules\FPSplanificationstage\Filament\Resources\IndisponibiliteInstructeurs\Pages\ListIndisponibiliteInstructeurs;
use Modules\FPSplanificationstage\Filament\Resources\IndisponibiliteInstructeurs\Pages\ViewIndisponibiliteInstructeur;
use Modules\FPSplanificationstage\Filament\Resources\IndisponibiliteInstructeurs\Schemas\IndisponibiliteInstructeurForm;
use Modules\FPSplanificationstage\Filament\Resources\IndisponibiliteInstructeurs\Schemas\IndisponibiliteInstructeurInfolist;
use Modules\FPSplanificationstage\Filament\Resources\IndisponibiliteInstructeurs\Tables\IndisponibiliteInstructeursTable;
use Modules\FPSplanificationstage\Models\IndisponibiliteInstructeur;

class IndisponibiliteInstructeurResource extends Resource
{
    use RequiresAuthentication;

    protected static ?string $model =
        IndisponibiliteInstructeur::class;

    protected static ?string $navigationLabel =
        'Indisponibilités';

    protected static ?string $modelLabel =
        'indisponibilité';

    protected static ?string $pluralModelLabel =
        'indisponibilités';

    protected static string|\UnitEnum|null $navigationGroup =
        'Disponibilités';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return IndisponibiliteInstructeurForm::configure(
            $schema
        );
    }

    public static function infolist(Schema $schema): Schema
    {
        return IndisponibiliteInstructeurInfolist::configure(
            $schema
        );
    }

    public static function table(Table $table): Table
    {
        return IndisponibiliteInstructeursTable::configure(
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
                ListIndisponibiliteInstructeurs::route('/'),

            'create' =>
                CreateIndisponibiliteInstructeur::route('/create'),

            'view' =>
                ViewIndisponibiliteInstructeur::route('/{record}'),

            'edit' =>
                EditIndisponibiliteInstructeur::route(
                    '/{record}/edit'
                ),
        ];
    }
}
