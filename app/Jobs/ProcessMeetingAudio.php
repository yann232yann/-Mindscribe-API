<?php

namespace App\Jobs;

use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessMeetingAudio implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 2;

    public function __construct(public Meeting $meeting) {}

    public function handle(): void
    {
        try {
            // ── 1. Status : en cours ──────────────────────────────
            $this->meeting->update(['status' => 'processing']);

            // ── 2. Transcription avec Whisper ─────────────────────
            $audioPath = Storage::disk('public')->path($this->meeting->audio_path);

            $transcription = $this->transcribeWithWhisper($audioPath);

            $this->meeting->update(['transcription' => $transcription]);

            // ── 3. Analyse avec GPT-4o ────────────────────────────
            $result = $this->analyzeWithGPT($transcription);

            // ── 4. Sauvegarde résumé ──────────────────────────────
            $this->meeting->update([
                'summary' => $result['summary'] ?? null,
                'status'  => 'done',
            ]);

            // ── 5. Sauvegarde décisions ───────────────────────────
            foreach ($result['decisions'] ?? [] as $content) {
                $this->meeting->decisions()->create(['content' => $content]);
            }

            // ── 6. Sauvegarde tâches ──────────────────────────────
            foreach ($result['tasks'] ?? [] as $task) {
                $this->meeting->tasks()->create([
                    'assignee' => $task['assignee'] ?? 'Non assigné',
                    'action'   => $task['action'] ?? '',
                ]);
            }

            // ── 7. Envoie le CR à tous les participants ───────────
            $teamController = new \App\Http\Controllers\TeamController();
            $teamController->sendSummaryToParticipants($this->meeting);

        } catch (\Throwable $e) {
            $errorMessage = $e->getMessage();
            $userMessage = 'L\'IA n\'a pas pu traiter cet enregistrement.';
            if (str_contains($errorMessage, 'insufficient_quota')) {
                $userMessage = 'Quota OpenAI épuisé. Merci de vérifier la facturation ou la clé API.';
            } elseif (str_contains($errorMessage, '401')) {
                $userMessage = 'Clé OpenAI invalide ou expirée.';
            } elseif (str_contains($errorMessage, 'timeout')) {
                $userMessage = 'Temps de traitement dépassé. Réessayez avec un audio plus court.';
            }

            Log::error('ProcessMeetingAudio failed', [
                'meeting_id' => $this->meeting->id,
                'error'      => $errorMessage,
            ]);
            $this->meeting->update([
                'status' => 'failed',
                'summary' => $userMessage,
            ]);
        }
    }

    // ── Whisper ───────────────────────────────────────────────────────
    private function transcribeWithWhisper(string $audioPath): string
    {
        $response = Http::withToken(config('services.openai.key'))
            ->withoutVerifying()
            ->timeout(120)
            ->attach('file', fopen($audioPath, 'r'), basename($audioPath))
            ->post('https://api.openai.com/v1/audio/transcriptions', [
                'model'    => 'whisper-1',
                'language' => 'fr',
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Whisper error: ' . $response->body());
        }

        return $response->json('text', '');
    }

    // ── GPT-4o ────────────────────────────────────────────────────────
    private function analyzeWithGPT(string $transcription): array
    {
        $prompt = <<<PROMPT
Tu es un assistant expert en compte-rendu de réunion.
Analyse la transcription suivante et retourne UNIQUEMENT un JSON valide avec cette structure :
{
  "summary": "Résumé clair en 5 lignes maximum",
  "decisions": ["décision 1", "décision 2"],
  "tasks": [
    {"assignee": "Prénom Nom", "action": "description de la tâche"}
  ]
}

Transcription :
$transcription
PROMPT;

        $response = Http::withToken(config('services.openai.key'))
            ->withoutVerifying()
            ->timeout(60)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('services.openai.model', 'gpt-4-turbo'),
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.3,
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('GPT-4o error: ' . $response->body());
        }

        $content = $response->json('choices.0.message.content', '{}');
        return json_decode($content, true) ?? [];
    }

    /* private function transcribeWithWhisper(string $audioPath): string
{
    // Mode simulation — pas besoin d'OpenAI
    return "C'est une transcription simulée de la réunion.  
            C'est une réunion de suivi du projet MindScribe AI. 
            Christophe s'occupera du backend avant vendredi. 
            Hans gère le design de l'application. 
            On a décidé de livrer la version 1 la semaine prochaine.
            Le budget alloué est de 500000 FCFA.";
}

private function analyzeWithGPT(string $transcription): array
{
    // Mode simulation — génère un vrai compte-rendu fictif
    return [
        'summary' => 'Réunion de suivi du projet MindScribe AI. L\'équipe a fait le point sur les avancements techniques et les prochaines échéances. Les responsabilités ont été clairement définies entre les membres.',
        'decisions' => [
            'Livraison de la version 1 fixée à la semaine prochaine',
            'Budget alloué validé à 500000 FCFA',
            'Réunion de suivi prévue chaque vendredi',
        ],
        'tasks' => [
            ['assignee' => 'Le chef Moïse', 'action' => 'Finaliser le développement backend avant vendredi'],
            ['assignee' => 'Hans', 'action' => 'Livrer les maquettes finales du design'],
            ['assignee' => 'Toute l\'équipe', 'action' => 'Préparer la démonstration de la version 1'],
        ],
    ];
} */
}