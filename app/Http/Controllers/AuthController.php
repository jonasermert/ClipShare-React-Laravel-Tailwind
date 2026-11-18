<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:72', function ($attribute, $value, $fail): void {
                if (is_string($value) && (strlen($value) > 72 || str_contains($value, "\0"))) {
                    $fail('Das Passwort darf höchstens 72 Byte und keine Nullzeichen enthalten.');
                }
            }],
        ]);

        try {
            $user = User::create([
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
                'password_hash' => Hash::make($data['password']),
            ]);
        } catch (UniqueConstraintViolationException) {
            return response()->json(['message' => 'Diese E-Mail-Adresse ist bereits registriert.'], 409);
        }

        return $this->startSession($request, $user, 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'max:4096'],
        ]);
        $user = User::where('email', Str::lower($credentials['email']))->first();
        $password = $credentials['password'];

        // password_verify akzeptiert auch die bcrypt-Hashes aus bcryptjs.
        if (! $user || str_contains($password, "\0") || ! password_verify($password, $user->password_hash)) {
            return response()->json(['message' => 'E-Mail-Adresse oder Passwort ist ungültig.'], 422);
        }

        return $this->startSession($request, $user);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()->only(['id', 'name', 'email'])]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true]);
    }

    private function startSession(Request $request, User $user, int $status = 200): JsonResponse
    {
        Auth::login($user);
        $request->session()->regenerate();

        return response()->json(['user' => $user->only(['id', 'name', 'email'])], $status);
    }
}
