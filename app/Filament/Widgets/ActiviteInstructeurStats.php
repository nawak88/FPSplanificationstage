<?php

namespace Modules\FPSplanificationstage\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\FPSplanificationstage\Models\IndisponibiliteInstructeur;
use Modules\FPSplanificationstage\Models\Inscription;
use Modules\FPSplanificationstage\Models\SessionStage;
use Modules\FPSplanificationstage\Services\InstructeurConnecteService;

class ActiviteInstructeurStats extends StatsOverviewWidget
{
    protected ?string $heading = 'Mon activité à venir';

    protected function getStats(): array
    {
        $instructeur = app(
            InstructeurConnecteService::class
        )->resolve();

        if (! $instructeur) {
            return [];
        }

        $sessions = SessionStage::query()
            ->whereHas(
                'instructeurs',
                fn ($query) => $query
                    ->withoutGlobalScopes()
                    ->where(
                        'rh_marins.id',
                        $instructeur->getKey()
                    )
            )
            ->whereIn(
                'statut',
                ['planifiee', 'confirmee']
            )
            ->where('fin', '>=', now());

        $stagiaires = Inscription::query()
            ->whereHas(
                'sessionStage',
                fn ($query) => $query
                    ->whereIn(
                        'statut',
                        ['planifiee', 'confirmee']
                    )
                    ->where('fin', '>=', now())
                    ->whereHas(
                        'instructeurs',
                        fn ($instructeurQuery) =>
                            $instructeurQuery
                                ->withoutGlobalScopes()
                                ->where(
                                    'rh_marins.id',
                                    $instructeur->getKey()
                                )
                    )
            )
            ->whereNotIn(
                'statut',
                ['refusee', 'annulee']
            )
            ->count();

        $indisponibilites =
            IndisponibiliteInstructeur::query()
                ->where(
                    'instructeur_id',
                    $instructeur->getKey()
                )
                ->where('actif', true)
                ->whereDate(
                    'date_fin',
                    '>=',
                    today()
                )
                ->count();

        return [
            Stat::make(
                'Sessions à venir',
                (clone $sessions)->count()
            )
                ->icon('heroicon-o-calendar-days')
                ->color('primary'),

            Stat::make(
                'Stagiaires inscrits',
                $stagiaires
            )
                ->icon('heroicon-o-user-group')
                ->color('success'),

            Stat::make(
                'Indisponibilités à venir',
                $indisponibilites
            )
                ->icon('heroicon-o-no-symbol')
                ->color(
                    $indisponibilites > 0
                        ? 'warning'
                        : 'gray'
                ),
        ];
    }
}
