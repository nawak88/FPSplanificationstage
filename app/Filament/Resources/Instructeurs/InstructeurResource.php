<?php

namespace Modules\FPSplanificationstage\Filament\Resources\Instructeurs;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\FPSplanificationstage\Filament\Concerns\RequiresAuthentication;
use Modules\FPSplanificationstage\Filament\Resources\Instructeurs\Pages\ListInstructeurs;
use Modules\FPSplanificationstage\Filament\Resources\Instructeurs\Pages\ViewInstructeur;
use Modules\FPSplanificationstage\Filament\Resources\Instructeurs\Schemas\InstructeurInfolist;
use Modules\FPSplanificationstage\Filament\Resources\Instructeurs\Tables\InstructeursTable;
use Modules\RH\Models\Marin;

class InstructeurResource extends Resource
{
    use RequiresAuthentication;

    protected static ?string $model =
        Marin::class;

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
        return parent::getEloquentQuery()
            ->where(
                fn (Builder $query): Builder => $query
                    ->whereIn(
                        'rh_marins.id',
                        DB::table('instructeur_stage')
                            ->select('instructeur_id')
                    )
                    ->orWhereIn(
                        'rh_marins.id',
                        DB::table('instructeur_session_stage')
                            ->select('instructeur_id')
                    )
                    ->orWhereIn(
                        'rh_marins.id',
                        DB::table('indisponibilite_instructeurs')
                            ->select('instructeur_id')
                    )
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
