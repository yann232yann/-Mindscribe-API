<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class PasswordResetController extends Controller
{
    // ── Envoie le lien de réinitialisation ───────────────────────
    public function sendResetLink(Request $request): JsonResponse
{
    $request->validate(['email' => 'required|email']);

    $user = User::where('email', $request->email)->first();

    if (!$user) {
        return response()->json([
            'message' => 'Si cet email existe, un lien a été envoyé.'
        ]);
    }

    // Génère un token unique
    $token = Str::random(64);

    // Sauvegarde le token en cache (expire dans 30 minutes)
    Cache::put('password_reset_' . $user->email, $token, now()->addMinutes(30));

    // Envoie l'email
    Mail::send([], [], function ($m) use ($user, $token) {
        $m->to($user->email)
          ->subject('🔑 Réinitialisation de votre mot de passe MindScribe AI')
          ->html("
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #00004D;'>Réinitialisation du mot de passe</h2>
                <p>Bonjour <strong>{$user->name}</strong>,</p>
                <p>Votre code de réinitialisation est :</p>
                <div style='background: #f4f5ff; padding: 20px; border-radius: 12px; text-align: center; margin: 20px 0;'>
                    <h1 style='color: #00004D; letter-spacing: 8px; font-size: 32px;'>{$token}</h1>
                </div>
                <p>Ce code expire dans <strong>30 minutes</strong>.</p>
                <p style='color: #888; font-size: 12px;'>MindScribe AI — Usage interne</p>
            </div>
          ");
    });

    return response()->json([
        'message' => 'Si cet email existe, un lien a été envoyé.'
    ]);
}

    // ── Réinitialise le mot de passe ──────────────────────────────
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'token'    => 'required|string',
            'password' => 'required|string|min:6',
        ]);

        $cachedToken = Cache::get('password_reset_' . $request->email);

        if (!$cachedToken || $cachedToken !== $request->token) {
            return response()->json([
                'message' => 'Code invalide ou expiré.'
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Utilisateur introuvable.'], 404);
        }

        $user->update(['password' => Hash::make($request->password)]);

        Cache::forget('password_reset_' . $request->email);

        return response()->json([
            'message' => 'Mot de passe réinitialisé avec succès.'
        ]);
    }
}