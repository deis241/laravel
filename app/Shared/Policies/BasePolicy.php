<?php

namespace App\Shared\Policies;

use App\Modules\Auth\Models\User;
use App\Traits\PolicyTrait;
use Illuminate\Database\Eloquent\Model;

/**
 * Classe de base pour toutes les policies.
 *
 * Usage:
 * ```php
 * class ArticlePolicy extends BasePolicy
 * {
 *     protected function getPermissionPrefix(): string
 *     {
 *         return 'articles'; // génère view_articles, create_articles, etc.
 *     }
 * }
 * ```
 */
abstract class BasePolicy
{
    use PolicyTrait;

    /**
     * Préfixe des permissions pour cette ressource.
     * Exemple: 'articles' → view_articles, create_articles, update_articles, delete_articles
     */
    abstract protected function getPermissionPrefix(): string;

    public function viewAny(User $user): bool
    {
        return $user->hasAccess("view_{$this->getPermissionPrefix()}");
    }

    public function view(User $user, Model $model): bool
    {
        return $this->authorizeView($user, $model, "view_{$this->getPermissionPrefix()}");
    }

    public function create(User $user, ?int $tenantId = null): bool
    {
        return $this->authorizeCreate($user, "create_{$this->getPermissionPrefix()}", $tenantId);
    }

    public function update(User $user, Model $model): bool
    {
        return $this->authorizeUpdate($user, $model, "update_{$this->getPermissionPrefix()}");
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->authorizeDelete($user, $model, "delete_{$this->getPermissionPrefix()}");
    }
}
