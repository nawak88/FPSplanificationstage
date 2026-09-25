<?php

namespace Modules\FPSplanificationstage\Filament\Resources\Stages;

use Modules\FPSplanificationstage\Models\Stage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\FPSplanificationstage\Filament\Concerns\RequiresAuthentication;
use Modules\FPSplanificationstage\Filament\Resources\Stages\Pages\CreateStage;
use Modules\FPSplanificationstage\Filament\Resources\Stages\Pages\EditStage;
use Modules\FPSplanificationstage\Filament\Resources\Stages\Pages\ListStages;
use Modules\FPSplanificationstage\Filament\Resources\Stages\Pages\ViewStage;
use Modules\FPSplanificationstage\Filament\Resources\Stages\Schemas\StageForm;
use Modules\FPSplanificationstage\Filament\Resources\Stages\Schemas\StageInfolist;
use Modules\FPSplanificationstage\Filament\Resources\Stages\Tables\StagesTable;

class StageResource extends Resource
{
    use RequiresAuthentication;

    protected static ?string $model = Stage::class;

    protected static ?string $navigationLabel = 'Catalogue des stages';

    protected static ?string $modelLabel = 'stage';

    protected static ?string $pluralModelLabel = 'stages';

    protected static string|\UnitEnum|null $navigationGroup = 'Référentiels';

    protected static ?int $navigationSort = 10;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'libelle_court';

    public static function form(Schema $schema): Schema
    {
        return StageForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return StageInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StagesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStages::route('/'),
            'create' => CreateStage::route('/create'),
            'view' => ViewStage::route('/{record}'),
            'edit' => EditStage::route('/{record}/edit'),
        ];
    }
}
