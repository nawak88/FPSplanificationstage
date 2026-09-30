<?php

namespace Modules\FPSplanificationstage\Providers\Filament;

use App\Filament\AvatarProviders\SkeletorAvatarProvider;
use App\Filament\Widgets\PanelSwitcher;
use App\Http\Middleware\InitializeTenancyByPath;
use App\Http\Middleware\ReconfigureSessionDatabaseWhenTenantNotInitialized;
use App\Http\Middleware\SetTenantCookieMiddleware;
use App\Http\Middleware\SetTenantDefaultForRoutesMiddleware;
use App\Providers\Filament\Traits\UsesSkeletorPrefixAndMultitenancyTrait;
use Filament\Facades\Filament;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class FilamentPanelProvider extends PanelProvide
{
    use UsesSkeletorPrefixAndMultitenancyTrait;

    private string $module =
        'FPSplanificationstage';

    public function panel(
        Panel $panel
    ): Panel {
        $moduleNamespace =
            $this->getModuleNamespace();

        return $panel
            ->id(
                'fpsplanificationstage'
            )
            ->path(
                $this->prefix
                . '/fpsplanificationstage'
            )
            ->colors([
                'primary' =>
                    Color::Blue,
            ])
            ->favicon(
                asset(
                    'assets/images/favicon-32x32.png'
                )
            )
            ->defaultAvatarProvider(
                SkeletorAvatarProvider::class
            )
            ->brandName(
                'FPSplanificationstage'
            )
            ->databaseNotifications()
            ->databaseNotificationsPolling(
                '5s'
            )
            ->discoverResources(
                in:
                    module_path(
                        $this->module,
                        'app/Filament/Resources'
                    ),
                for:
                    "$moduleNamespace\\Filament\\Resources"
            )
            ->discoverPages(
                in:
                    module_path(
                        $this->module,
                        'app/Filament/Pages'
                    ),
                for:
                    "$moduleNamespace\\Filament\\Pages"
            )
            ->discoverPages(
                in:
                    module_path(
                        $this->module,
                        'app/Filament/Public/Pages'
                    ),
                for:
                    "$moduleNamespace\\Filament\\Public\\Pages"
            )
            ->discoverWidgets(
                in:
                    module_path(
                        $this->module,
                        'app/Filament/Widgets'
                    ),
                for:
                    "$moduleNamespace\\Filament\\Widgets"
            )
            ->widgets([
                PanelSwitcher::class,
            ])
            ->navigationItems([

                NavigationItem::make(
                    'Login'
                )
                    ->label(
                        'Se connecter'
                    )
                    ->url(
                        fn (): string =>
                            route('login')
                    )
                    ->icon(
                        'heroicon-o-user'
                    )
                    ->hidden(
                        fn (): bool =>
                            Auth::check()
                    ),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                InitializeTenancyByPath::class,
                ReconfigureSessionDatabaseWhenTenantNotInitialized::class,
                SetTenantDefaultForRoutesMiddleware::class,
                SetTenantCookieMiddleware::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                //
            ])
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth(
                Width::Full
            )
            ->userMenuItems([

                'help' =>
                    MenuItem::make()
                        ->label('Aide')
                        ->icon(
                            'heroicon-m-question-mark-circle'
                        )
                        ->url(
                            fn () =>
                                url(
                                    config(
                                        'skeletor.prefixe_instance'
                                    )
                                    . '/docs/'
                                    . Filament::getCurrentOrDefaultPanel()
                                        ->getId()
                                )
                                . '/',
                            shouldOpenInNewTab:
                                true
                        ),

                'apidoc' =>
                    MenuItem::make()
                        ->label('API')
                        ->icon(
                            'heroicon-m-cloud'
                        )
                        ->url(
                            fn () =>
                                url(
                                    route(
                                        'l5-swagger.default.api'
                                    )
                                ),
                            shouldOpenInNewTab:
                                true
                        ),
            ]);
    }

    protected function getModuleNamespace(): string
    {
        return config(
            'modules.namespace'
        )
            . '\\'
            . $this->module;
    }
}
