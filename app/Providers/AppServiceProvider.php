<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Reject revoked or changed roles even when a long-lived guard retains an old actor.
        Gate::before(function (User $user): ?bool {
            $current = User::find($user->id);

            return $current !== null && $current->role === $user->role ? null : false;
        });

        Gate::define('manage-workshop', fn (User $user): bool => $user->role->managesWorkshop());
        Gate::define('work-services', fn (User $user): bool => $user->role->isStaff());
        Gate::define('customer-portal', fn (User $user): bool => $user->role === Role::Customer);
        Gate::define('manage-users', fn (User $user): bool => $user->role === Role::Owner);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
