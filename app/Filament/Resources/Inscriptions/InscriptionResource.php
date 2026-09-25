<?php

namespace Modules\FPSplanificationstage\Filament\Resources\Inscriptions;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Modules\FPSplanificationstage\Filament\Concerns\RequiresAuthentication;
use Modules\FPSplanificationstage\Filament\Resources\Inscriptions\Pages\CreateInscription;
use Modules\FPSplanificationstage\Filament\Resources\Inscriptions\Pages\EditInscription;
use Modules\FPSplanificationstage\Filament\Resources\Inscriptions\Pages\ListInscriptions;
use Modules\FPSplanificationstage\Filament\Resources\Inscriptions\Schemas\InscriptionForm;
use Modules\FPSplanificationstage\Filament\Resources\Inscriptions\Tables\InscriptionsTable;
use Modules\FPSplanificationstage\Models\Inscription;

class InscriptionResource extends Resource
{
    use RequiresAuthentication;

    protected static ?string $model =
        Inscription::class;

    protected static ?string $recordTitleAttribute =
        'code_inscription';

    protected static ?string $navigationLabel =
        'Inscriptions';

    protected static ?string $modelLabel =
        'inscription';

    protected static ?string $pluralModelLabel =
        'inscriptions';

    protected static string|\UnitEnum|null $navigationGroup =
        'Inscriptions / Admission';

    protected static ?int $navigationSort =
        10;

    public static function form(
        Schema $schema
    ): Schema {
        return InscriptionForm::configure(
            $schema
        );
    }

    public static function table(
        Table $table
    ): Table {
        return InscriptionsTable::configure(
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
                ListInscriptions::route('/'),

            'create' =>
                CreateInscription::route('/create'),

            'edit' =>
                EditInscription::route('/{record}/edit'),
        ];
    }
}
