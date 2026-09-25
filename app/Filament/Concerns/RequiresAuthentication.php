<?php

namespace Modules\FPSplanificationstage\Filament\Concerns;

use Illuminate\Support\Facades\Auth;

trait RequiresAuthentication
{
    public static function canAccess(): bool
    {
        return Auth::check()
            && parent::canAccess();
    }
}
