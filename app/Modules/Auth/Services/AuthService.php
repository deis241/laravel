<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Auth\Notifications\ResetPasswordNotification;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class AuthService
{
    public function login(string $email, string $password, string $deviceName = 'api'): array
    {
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            return ['error' => 'Les identifiants sont incorrects.'];
        }

        if ($user->status !== 'active') {
            return ['error' => 'Votre compte est désactivé. Contactez l\'administrateur.'];
        }

        // Révoque les anciens tokens du même appareil pour éviter les doublons
        $user->tokens()->where('name', $deviceName)->delete();

        $token = $user->createToken($deviceName)->plainTextToken;

        return [
            'token' => $token,
            'user'  => $user->load('roles'),
        ];
    }

    public function register(array $data, string $deviceName = 'api'): array
    {
        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => $data['password'],
            'status'   => 'active',
        ]);

        $token = $user->createToken($deviceName)->plainTextToken;

        return [
            'token' => $token,
            'user'  => $user,
        ];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    public function me(User $user): User
    {
        return $user->load('roles.permissions');
    }

    public function sendResetLink(string $email): string
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            // Réponse générique pour éviter l'énumération d'emails
            return Password::RESET_LINK_SENT;
        }

        $token = Password::broker()->createToken($user);
        $user->notify(new ResetPasswordNotification($token));

        return Password::RESET_LINK_SENT;
    }

    public function resetPassword(string $email, string $token, string $newPassword): string
    {
        return Password::broker()->reset(
            [
                'email'                 => $email,
                'password'              => $newPassword,
                'password_confirmation' => $newPassword,
                'token'                 => $token,
            ],
            function (User $user, string $password) {
                $user->forceFill(['password' => $password])->save();
                $user->tokens()->delete();
                event(new PasswordReset($user));
            }
        );
    }
}
