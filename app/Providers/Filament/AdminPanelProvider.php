<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\DailyComplianceReport;
use App\Filament\Pages\InspectionControl;
use App\Http\Middleware\EnsurePasswordChanged;
use DiogoGPinto\AuthUIEnhancer\AuthUIEnhancerPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Filament\Enums\ThemeMode;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->brandName(config('app.name'))
            ->homeUrl(fn (): string => InspectionControl::getUrl())
            ->defaultThemeMode(ThemeMode::Light)
            ->maxContentWidth(MaxWidth::Full)
            ->sidebarCollapsibleOnDesktop(false)
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->colors([
                'primary' => Color::Orange,
                'gray' => Color::Gray,
            ])
            ->theme(asset('css/filament/admin/theme.css'))
            ->plugins([
                AuthUIEnhancerPlugin::make()
                    ->formPanelPosition('right')
                    ->formPanelWidth('45%')
                    ->showEmptyPanelOnMobile(false)
                    ->formPanelBackgroundColor(Color::Gray, 100)
                    ->emptyPanelBackgroundImageUrl(asset('images/auth/login-panel.jpg'))
                    ->emptyPanelBackgroundImageOpacity('100%'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                InspectionControl::class,
                DailyComplianceReport::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->profile(EditProfile::class)
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsurePasswordChanged::class,
            ]);
    }
}
