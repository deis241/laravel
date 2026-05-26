<?php

namespace App\Modules\Auth\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserAuthResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'     => $this->id,
            'name'   => $this->name,
            'email'  => $this->email,
            'status' => $this->status,
            'roles'  => $this->whenLoaded('roles', fn () => $this->roles->map(fn ($role) => [
                'id'   => $role->id,
                'code' => $role->code,
                'name' => $role->libelle,
            ])),
            'permissions' => $this->whenLoaded('roles', fn () => $this->roles
                ->flatMap(fn ($role) => $role->permissions ?? collect())
                ->pluck('code')
                ->unique()
                ->values()
            ),
            'created_at' => $this->created_at,
        ];
    }
}
