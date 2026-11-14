<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const DUMMY_HASH = '$2y$12$MUXl.yR8DGP2wVVmLhiz0.3et4V.fSmWkRTte2/IUM6VFaw1oShCy';

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        $passwordIsCorrect = Hash::check(
            $request->validated('password'),
            $user?->password ?? self::DUMMY_HASH,
        );

        if (! $user || ! $passwordIsCorrect) {
            throw ValidationException::withMessages([
                'email' => 'Deze gegevens kloppen niet.',
            ]);
        }

        $token = $user->createToken('dashboard')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->safe()->only(['name', 'email', 'password']));

        $token = $user->createToken('dashboard')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ], 201);
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function user(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
