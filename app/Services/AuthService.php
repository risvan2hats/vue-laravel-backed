<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function __construct(private readonly UserRepository $users) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{user: User, token: string}
     */
    public function register(array $attributes): array
    {
        $user = $this->users->create($attributes);

        return [
            'user' => $user,
            'token' => $user->createToken('vue-shopping-cart')->plainTextToken,
        ];
    }

    /**
     * @return array{user: User, token: string}
     */
    public function login(string $email, string $password): array
    {
        $user = $this->users->findByEmail($email);

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw new AuthenticationException('The provided credentials are incorrect.');
        }

        return [
            'user' => $user,
            'token' => $user->createToken('vue-shopping-cart')->plainTextToken,
        ];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }
}
