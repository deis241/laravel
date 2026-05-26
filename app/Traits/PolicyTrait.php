<?php

namespace App\Traits;

use App\Modules\Auth\Models\User;

/**
 * Helpers d'autorisation pour les policies.
 * Gère le bypass super_admin et l'isolation tenant.
 */
trait PolicyTrait
{
    /**
     * Vérifie la permission + appartenance au tenant (si fourni).
     */
    protected function authorize(User $user, string $permission, ?int $tenantId = null): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if (!$user->hasAccess($permission)) {
            return false;
        }

        if ($tenantId !== null && !$user->belongsToTenant($tenantId)) {
            return false;
        }

        return true;
    }

    protected function authorizeView(User $user, object $model, string $permission): bool
    {
        $tenantId = isset($model->tenant_id) ? (int) $model->tenant_id : null;
        return $this->authorize($user, $permission, $tenantId);
    }

    protected function authorizeCreate(User $user, string $permission, ?int $tenantId = null): bool
    {
        return $this->authorize($user, $permission, $tenantId);
    }

    protected function authorizeUpdate(User $user, object $model, string $permission): bool
    {
        $tenantId = $model->tenant_id ?? null;
        return $this->authorize($user, $permission, $tenantId);
    }

    protected function authorizeDelete(User $user, object $model, string $permission): bool
    {
        $tenantId = $model->tenant_id ?? null;
        return $this->authorize($user, $permission, $tenantId);
    }

    protected function authorizeAction(User $user, object $model, string $permission): bool
    {
        $tenantId = $model->tenant_id ?? null;
        return $this->authorize($user, $permission, $tenantId);
    }
}
