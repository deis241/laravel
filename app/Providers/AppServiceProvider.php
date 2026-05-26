<?php

namespace App\Providers;

use App\Modules\Auth\Models\User;
use App\Modules\User\Models\Permission;
use App\Modules\User\Models\Role;
use App\Modules\User\Policies\UserPolicy;
use App\Modules\User\Repositories\Contracts\UserRepositoryInterface;
use App\Modules\User\Repositories\UserRepository;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ── Repositories ──────────────────────────────────────────────────────
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);

        // ── Ajouter ici les bindings de chaque nouveau module ─────────────────
        // $this->app->bind(ArticleRepositoryInterface::class, ArticleRepository::class);
    }

    public function boot(): void
    {
        // ── Policies ──────────────────────────────────────────────────────────
        Gate::policy(User::class, UserPolicy::class);

        // ── Ajouter ici les policies de chaque nouveau module ─────────────────
        // Gate::policy(Article::class, ArticlePolicy::class);
    }
}
