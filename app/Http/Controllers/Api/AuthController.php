<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly UserRepository $users,
    ) {}

    public function register(Request $request): JsonResponse
    {
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:30', 'unique:users,phone'],
            'address' => ['nullable', 'string', 'max:2000'],
        ]);
        unset($attributes['password_confirmation']);

        $result = $this->auth->register($attributes);

        return response()->json([
            'data' => [
                'user' => new UserResource($result['user']),
                'token' => $result['token'],
                'token_type' => 'Bearer',
            ],
        ], JsonResponse::HTTP_CREATED);
    }

    public function login(Request $request): JsonResponse
    {
        $attributes = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
        $result = $this->auth->login($attributes['email'], $attributes['password']);

        return response()->json([
            'data' => [
                'user' => new UserResource($result['user']),
                'token' => $result['token'],
                'token_type' => 'Bearer',
            ],
        ]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function updateProfile(Request $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();
        $attributes = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30', Rule::unique('users', 'phone')->ignore($user)],
            'address' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        return new UserResource($this->users->update($user, $attributes));
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->auth->logout($user);

        return response()->json(status: JsonResponse::HTTP_NO_CONTENT);
    }
}
