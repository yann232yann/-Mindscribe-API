<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
        ], [
            'name.required'     => 'Le nom est obligatoire.',
            'name.string'       => 'Le nom doit être une chaîne de caractères.',
            'name.max'          => 'Le nom ne peut pas dépasser 255 caractères.',
            'email.required'    => 'L\'adresse email est obligatoire.',
            'email.email'       => 'L\'adresse email n\'est pas valide.',
            'email.max'         => 'L\'adresse email ne peut pas dépasser 255 caractères.',
            'email.unique'      => 'Cette adresse email est déjà utilisée.',
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.string'   => 'Le mot de passe doit être une chaîne de caractères.',
            'password.min'      => 'Le mot de passe doit contenir au moins 6 caractères.',
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $token = $user->createToken('mindscribe-mobile')->plainTextToken;

        return response()->json([
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required'    => 'L\'adresse email est obligatoire.',
            'email.email'       => 'L\'adresse email n\'est pas valide.',
            'password.required' => 'Le mot de passe est obligatoire.',
        ]);

        $user = User::where('email', $data['email'])->first();

        // Email introuvable en BD
        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['Aucun compte trouvé avec cette adresse email.'],
            ]);
        }

        // Mauvais mot de passe
        if (!Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Mot de passe incorrect.'],
            ]);
        }

        $token = $user->createToken('mindscribe-mobile')->plainTextToken;

        return response()->json([
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'token' => $token,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Déconnexion réussie.',
        ]);
    }
}