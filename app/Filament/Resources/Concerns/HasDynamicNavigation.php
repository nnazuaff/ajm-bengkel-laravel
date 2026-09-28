<?php

namespace App\Filament\Resources\Concerns;

use Filament\Navigation\NavigationItem;

use function Filament\Support\original_request;

trait HasDynamicNavigation
{
    /**
     * Keep navigation labels lazy so changing the application locale also
     * updates an already-built panel during the current PHP process.
     *
     * @return array<NavigationItem>
     */
    public static function getNavigationItems(): array
    {
        if (! static::hasPage('index')) {
            return [];
        }

        $activeRoutePattern = static::getNavigationItemActiveRoutePattern();

        return [
            NavigationItem::make(fn (): string => static::getNavigationLabel())
                ->key(static::class)
                ->group(static::getNavigationGroup())
                ->parentItem(static::getNavigationParentItem())
                ->icon(static::getNavigationIcon())
                ->activeIcon(static::getActiveNavigationIcon())
                ->isActiveWhen(fn (): bool => original_request()->routeIs($activeRoutePattern))
                ->badge(static::getNavigationBadge(), color: static::getNavigationBadgeColor())
                ->badgeTooltip(static::getNavigationBadgeTooltip())
                ->sort(static::getNavigationSort())
                ->url(static::getNavigationUrl()),
        ];
    }
}
