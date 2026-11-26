<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Domains\Identity\Actions\UpdateProfile;
use App\Domains\Identity\Data\ProfileChanges;
use App\Domains\Identity\Enums\UserLocale;
use App\Domains\Identity\Models\User;
use App\Filament\Navigation\AdminNavigationGroup;
use App\Filament\Pages\Dashboard;
use App\Http\Middleware\Admin\AuthenticateAdmin;
use App\Http\Middleware\Admin\SetAdminLocale;
use App\Support\AdminArea;
use App\Support\AdminNumbers;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Actions\Action;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Facades\Filament;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Vite;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Livewire\Component;

/**
 * The admin panel, as docs/design/ADMIN_PANEL.md describes it: Arabic and RTL by default, the
 * brand's colours and font, two-factor authentication required, roles through Filament Shield.
 */
final class AdminPanelServiceProvider extends PanelProvider
{
    public const string ID = 'admin';

    public function register(): void
    {
        parent::register();

        // Admin permissions count only inside this panel (DECISIONS 86).
        AdminArea::detectUsing(static fn (): bool => Filament::getCurrentPanel()?->getId() === self::ID);

        // Tables, forms and infolists format numbers in Western digits (DECISIONS 97), read per
        // request since the language is the admin's.
        Table::configureUsing(fn (Table $table): Table => $table->defaultNumberLocale(fn (): string => AdminNumbers::localeFor(app()->getLocale())));
        Schema::configureUsing(fn (Schema $schema): Schema => $schema->defaultNumberLocale(fn (): string => AdminNumbers::localeFor(app()->getLocale())));
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id(self::ID)
            ->path('admin')
            ->login()
            ->brandName(fn (): string => (string) __('admin.brand'))
            ->colors([
                'primary' => Color::hex('#0B6B37'),
                'warning' => Color::hex('#FFC93C'),
                'danger' => Color::hex('#C2410C'),
                'gray' => Color::hex('#5C6B63'),
                'info' => Color::hex('#3A4A8C'),
            ])
            // Served from the theme build, never from a font CDN (DECISIONS 96): the local provider
            // prints nothing, and the head hook below prints the build's font faces.
            ->font('Readex Pro', provider: LocalFontProvider::class)
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): Htmlable => Vite::fonts('readex-pro'))
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->darkMode(false)
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups($this->navigationGroups())
            ->databaseNotifications()
            ->multiFactorAuthentication([
                AppAuthentication::make()->recoverable()->brandName('Awez Eh'),
            ], isRequired: true)
            // A policy or a policy method missing is an error, never a silent "allowed".
            ->strictAuthorization()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([Dashboard::class])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->plugin(
                FilamentShieldPlugin::make()
                    ->navigationGroup(AdminNavigationGroup::System)
                    ->navigationSort(2),
            )
            ->userMenuItems([$this->languageSwitch()])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            // Only these two run again on Livewire's requests. Making the whole list persistent
            // would start the session and decrypt the cookies a second time inside them, which
            // replaces the session and signs the admin out (DECISIONS 95).
            ->middleware([SetAdminLocale::class], isPersistent: true)
            // Filament's authentication, signing out an admin suspended meanwhile.
            ->authMiddleware([AuthenticateAdmin::class], isPersistent: true);
    }

    /**
     * The groups in their order, keyed as Filament keys an enum's cases. The labels are read when
     * the sidebar is drawn, in the admin's language: Filament's own `navigationGroups(Enum::class)`
     * reads them once, at boot, before the admin's language is known.
     *
     * @return array<string, NavigationGroup>
     */
    private function navigationGroups(): array
    {
        $groups = [];

        foreach (AdminNavigationGroup::cases() as $group) {
            $groups[$group->name] = NavigationGroup::make()->label(fn (): string => $group->getLabel());
        }

        return $groups;
    }

    /**
     * Arabic or English, stored on the admin's account (`users.locale`).
     */
    private function languageSwitch(): Action
    {
        return Action::make('switchLanguage')
            ->label(fn (): string => (string) __('admin.language.switch'))
            ->icon(Heroicon::OutlinedLanguage)
            ->action(function (UpdateProfile $updateProfile, Component $livewire): void {
                $admin = Filament::auth()->user();

                if (! $admin instanceof User) {
                    return;
                }

                $other = $admin->locale === UserLocale::Arabic ? UserLocale::English : UserLocale::Arabic;
                $updateProfile->handle($admin, ProfileChanges::from(['locale' => $other]));

                $livewire->redirect((string) request()->header('Referer', Dashboard::getUrl()));
            });
    }
}
