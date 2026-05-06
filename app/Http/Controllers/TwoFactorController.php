<?php

namespace App\Http\Controllers;

use App\Models\TwoFactorCode;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class TwoFactorController extends Controller
{
    // ── Envoyer le code ───────────────────────────────────────────
    public function sendCode(Request $request): JsonResponse
    {
        $user = $request->user();

        // Supprime les anciens codes
        TwoFactorCode::where('user_id', $user->id)->delete();

        // Génère un code à 6 chiffres
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Sauvegarde en BDD (expire dans 10 minutes)
        TwoFactorCode::create([
            'user_id'    => $user->id,
            'code'       => $code,
            'expires_at' => now()->addMinutes(10),
        ]);

        // Envoie par email
        Mail::raw(
            "Votre code de vérification MindScribe AI : $code\n\nCe code expire dans 10 minutes.",
            function ($m) use ($user, $code) {
                $m->to($user->email)
                  ->subject("🔐 Code de vérification : $code");
            }
        );

        return response()->json(['message' => 'Code envoyé par email.']);
    }

    // ── Vérifier le code ──────────────────────────────────────────
    public function verifyCode(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string|size:6']);

        $user = $request->user();

        $record = TwoFactorCode::where('user_id', $user->id)
            ->where('code', $request->code)
            ->where('used', false)
            ->where('expires_at', '>', now())
            ->first();

        if (!$record) {
            return response()->json([
                'message' => 'Code invalide ou expiré.'
            ], 422);
        }

        // Marque le code comme utilisé
        $record->update(['used' => true]);

        return response()->json([
            'message' => 'Vérification réussie.',
            'verified' => true,
        ]);
    }
}