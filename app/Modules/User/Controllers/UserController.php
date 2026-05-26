<?php

namespace App\Modules\User\Controllers;

use App\Modules\User\Requests\AssignRoleRequest;
use App\Modules\User\Requests\CreateUserRequest;
use App\Modules\User\Requests\UpdateUserRequest;
use App\Modules\User\Resources\RoleResource;
use App\Modules\User\Resources\UserResource;
use App\Modules\User\Services\UserService;
use App\Shared\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UserController extends BaseController
{
    public function __construct(private readonly UserService $userService) {}

    // ── CRUD ──────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        return $this->execute(function () use ($request) {
            ['filters' => $filters, 'per_page' => $perPage] = $this->getPaginationAndFilters(
                $request,
                ['search', 'status', 'role', 'tenant_id', 'sort_by', 'sort_direction', 'date_debut', 'date_fin']
            );

            $users = $this->userService->getAll(null, $filters, $perPage);

            return $this->response(
                UserResource::collection($users),
                Response::HTTP_OK,
                'Liste des utilisateurs.'
            );
        });
    }

    public function store(CreateUserRequest $request): JsonResponse
    {
        return $this->execute(function () use ($request) {
            $user = $this->userService->create($request->validated());

            return $this->responseCreated(
                new UserResource($user->load('roles')),
                Response::HTTP_CREATED,
                'Utilisateur créé avec succès.'
            );
        });
    }

    public function show(int $id): JsonResponse
    {
        return $this->execute(function () use ($id) {
            $user = $this->userService->getUserWithRelations($id);

            return $this->response(
                new UserResource($user),
                Response::HTTP_OK,
                'Détail utilisateur.'
            );
        });
    }

    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        return $this->execute(function () use ($request, $id) {
            $user = $this->userService->update($id, $request->validated());

            return $this->responseUpdated(
                new UserResource($user->load('roles')),
                Response::HTTP_ACCEPTED,
                'Utilisateur mis à jour avec succès.'
            );
        });
    }

    public function destroy(int $id): JsonResponse
    {
        return $this->execute(function () use ($id) {
            $this->userService->delete($id);

            return $this->response(null, Response::HTTP_OK, 'Utilisateur supprimé avec succès.');
        });
    }

    // ── Gestion des rôles ─────────────────────────────────────────────────────

    public function roles(int $id): JsonResponse
    {
        return $this->execute(function () use ($id) {
            $user = $this->userService->getById($id, null, ['roles']);

            return $this->response(
                RoleResource::collection($user->roles),
                Response::HTTP_OK,
                'Rôles de l\'utilisateur.'
            );
        });
    }

    public function assignRole(AssignRoleRequest $request, int $id): JsonResponse
    {
        return $this->execute(function () use ($request, $id) {
            $this->userService->assignRole($id, $request->integer('role_id'));

            $user = $this->userService->getById($id, null, ['roles']);

            return $this->response(
                RoleResource::collection($user->roles),
                Response::HTTP_OK,
                'Rôle attribué avec succès.'
            );
        });
    }

    public function removeRole(int $id, int $roleId): JsonResponse
    {
        return $this->execute(function () use ($id, $roleId) {
            $this->userService->removeRole($id, $roleId);

            return $this->response(null, Response::HTTP_OK, 'Rôle retiré avec succès.');
        });
    }
}
