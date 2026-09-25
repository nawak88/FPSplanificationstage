<?php

namespace Modules\FPSplanificationstage\Filament\Public\Pages;

use Filament\Pages\Page;

abstract class PublicPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    /**
     * Données préparées par les services de présentation du portail.
     *
     * @var array<string, mixed>
     */
    protected array $pageData = [];

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return $this->pageData;
    }

    public function getHeading(): string
    {
        return '';
    }
}
