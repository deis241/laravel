<?php

namespace App\Modules\Auth\Models;

use App\Modules\User\Models\Role;
use App\Traits\HasStates;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements CanResetPasswordContract
{
    use HasApiTokens, HasFactory, Notifiable, CanResetPassword, HasStates;

    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'tenant_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')->withTimestamps();
    }

    public function isSuperAdmin(): bool
    {
        return $this->roles()->where('code', 'super_admin')->exists();
    }

    public function hasRole(string $code): bool
    {
        return $this->roles()->where('code', $code)->exists();
    }

    public function hasPermission(string $permissionCode): bool
    {
        return $this->roles()
            ->whereHas('permissions', fn ($q) => $q->where('code', $permissionCode))
            ->exists();
    }

    /**
     * Super admin bypass : toujours vrai.
     */
    public function hasAccess(string $permissionCode): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->hasPermission($permissionCode);
    }

    /**
     * Vérifie que l'utilisateur appartient au tenant donné.
     * À adapter selon la structure de votre projet (pivot table, colonne directe, etc.)
     */
    public function belongsToTenant(int $tenantId): bool
    {
        return (int) $this->tenant_id === $tenantId;
    }
}
