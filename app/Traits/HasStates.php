<?php

namespace App\Traits;

/**
 * Gestion générique des statuts (active/inactive/archived...).
 *
 * Usage dans un modèle Eloquent:
 * ```php
 * use HasStates;
 *
 * protected array $allowedStates = ['active', 'inactive', 'archived'];
 * protected string $stateColumn  = 'status';
 * ```
 */
trait HasStates
{
    protected array $allowedStates = ['active', 'inactive'];
    protected string $stateColumn  = 'status';

    public function isActive(): bool
    {
        return $this->{$this->stateColumn} === 'active';
    }

    public function isInactive(): bool
    {
        return $this->{$this->stateColumn} === 'inactive';
    }

    public function activate(): bool
    {
        return $this->update([$this->stateColumn => 'active']);
    }

    public function deactivate(): bool
    {
        return $this->update([$this->stateColumn => 'inactive']);
    }

    public function transitionTo(string $newState): bool
    {
        if (!in_array($newState, $this->allowedStates)) {
            throw new \InvalidArgumentException("État '{$newState}' non autorisé.");
        }

        return $this->update([$this->stateColumn => $newState]);
    }

    public function scopeActive($query)
    {
        return $query->where($this->stateColumn, 'active');
    }

    public function scopeInactive($query)
    {
        return $query->where($this->stateColumn, 'inactive');
    }
}
