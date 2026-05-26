<?php

namespace App\Modules\Auth\Controllers;

use App\Modules\Auth\Requests\ForgotPasswordRequest;
use App\Modules\Auth\Requests\LoginRequest;
use App\Modules\Auth\Requests\RegisterRequest;
use App\Modules\Auth\Requests\ResetPasswordRequest;
use App\Modules\Auth\Resources\UserAuthResource;
use App\Modules\Auth\Services\AuthService;
use App\Shared\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Password;

class AuthController extends BaseController
{
    public function __construct(private readonly AuthService $authService) {}

    public function login(LoginRequest $request): JsonResponse
    {
        return $this->execute(function () use ($request) {
            $result = $this->authService->login(
                $request->email,
                $request->password,
                $request->userAgent() ?? 'api'
            );

            if (isset($result['error'])) {
                return response()->json(['message' => $result['error']], Response::HTTP_UNAUTHORIZED);
            }

            return $this->response([
                'token' => $result['token'],
                'user'  => new UserAuthResource($result['user']),
            ], Response::HTTP_OK, 'Connexion réussie.');
        });
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        return $this->execute(function () use ($request) {
            $result = $this->authService->register(
                $request->validated(),
                $request->userAgent() ?? 'api'
            );

            return $this->responseCreated([
                'token' => $result['token'],
                'user'  => new UserAuthResource($result['user']),
            ], Response::HTTP_CREATED, 'Inscription réussie.');
        });
    }

    public function logout(Request $request): JsonResponse
    {
        return $this->execute(function () use ($request) {
            $this->authService->logout($request->user());

            return $this->response(null, Response::HTTP_OK, 'Déconnexion réussie.');
        });
    }

    public function me(Request $request): JsonResponse
    {
        return $this->execute(function () use ($request) {
            $user = $this->authService->me($request->user());

            return $this->response(new UserAuthResource($user), Response::HTTP_OK, 'Profil utilisateur.');
        });
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        return $this->execute(function () use ($request) {
            $this->authService->sendResetLink($request->email);

            return $this->response(null, Response::HTTP_OK, 'Un lien de réinitialisation a été envoyé à votre adresse email.');
        });
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        return $this->execute(function () use ($request) {
            $status = $this->authService->resetPassword(
                $request->email,
                $request->token,
                $request->password
            );

            if ($status !== Password::PASSWORD_RESET) {
                return response()->json([
                    'message' => 'Le lien de réinitialisation est invalide ou expiré.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return $this->response(null, Response::HTTP_OK, 'Mot de passe réinitialisé avec succès.');
        });
    }
}
