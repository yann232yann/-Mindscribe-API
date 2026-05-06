<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\TeamMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class TeamController extends Controller
{
    // ── Liste tous les membres actifs ─────────────────────────────
    public function index(): JsonResponse
    {
        $members = TeamMember::where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json($members);
    }

    // ── Invite des participants à une réunion ─────────────────────
    public function inviteParticipants(Request $request, int $meetingId): JsonResponse
    {
        $request->validate([
            'member_ids' => 'required|array',
            'member_ids.*' => 'exists:team_members,id',
        ]);

        $meeting = Meeting::where('user_id', $request->user()->id)
            ->findOrFail($meetingId);

        $initiator = $request->user();

        foreach ($request->member_ids as $memberId) {
            $member = TeamMember::find($memberId);
            if (!$member) continue;

            // Evite les doublons
            MeetingParticipant::firstOrCreate([
                'meeting_id'     => $meeting->id,
                'team_member_id' => $member->id,
            ]);

            // Envoie email d'invitation
            $this->sendInvitationEmail($member, $meeting, $initiator->name);
        }

        return response()->json(['message' => 'Participants invités avec succès.']);
    }

    // ── Envoie email d'invitation ─────────────────────────────────
    private function sendInvitationEmail($member, $meeting, string $initiatorName): void
    {
        try {
            Mail::send([], [], function ($m) use ($member, $meeting, $initiatorName) {
                $m->to($member->email)
                  ->subject("🎙️ $initiatorName a démarré une réunion — MindScribe AI")
                  ->html("
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                        <div style='background: #00004D; padding: 30px; border-radius: 12px 12px 0 0; text-align: center;'>
                            <h1 style='color: white; margin: 0; font-size: 24px;'>🎙️ MindScribe AI</h1>
                        </div>
                        <div style='background: #f8f9ff; padding: 30px; border-radius: 0 0 12px 12px;'>
                            <h2 style='color: #00004D;'>Bonjour {$member->name} !</h2>
                            <p style='color: #444; font-size: 16px;'>
                                <strong>{$initiatorName}</strong> a démarré une réunion :
                            </p>
                            <div style='background: white; border-left: 4px solid #4F6FFF; padding: 16px; border-radius: 8px; margin: 20px 0;'>
                                <p style='margin: 0; font-size: 18px; color: #00004D; font-weight: bold;'>
                                    📋 {$meeting->title}
                                </p>
                                <p style='margin: 8px 0 0 0; color: #666;'>
                                    🕐 " . now()->format('d/m/Y à H:i') . "
                                </p>
                            </div>
                            <p style='color: #666; font-size: 14px;'>
                                Le compte-rendu vous sera envoyé automatiquement dès que la réunion sera terminée.
                            </p>
                            <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
                            <p style='color: #999; font-size: 12px; text-align: center;'>
                                MindScribe AI — Usage interne
                            </p>
                        </div>
                    </div>
                  ");
            });

            MeetingParticipant::where('meeting_id', $meeting->id)
                ->where('team_member_id', $member->id)
                ->update(['notified_at' => now()]);

        } catch (\Throwable $e) {
            // Log mais ne bloque pas
        }
    }

    // ── Envoie le compte-rendu à tous les participants ────────────
    public function sendSummaryToParticipants(Meeting $meeting): void
    {
        $participants = MeetingParticipant::where('meeting_id', $meeting->id)
            ->with('member')
            ->get();

        $decisions = $meeting->decisions()->pluck('content')->toArray();
        $tasks = $meeting->tasks()->get();

        foreach ($participants as $participant) {
            $member = $participant->member;
            if (!$member) continue;

            try {
                $decisionsHtml = '';
                foreach ($decisions as $d) {
                    $decisionsHtml .= "<li style='margin: 8px 0;'>$d</li>";
                }

                $tasksHtml = '';
                foreach ($tasks as $t) {
                    $tasksHtml .= "<li style='margin: 8px 0;'><strong>{$t->assignee}</strong> : {$t->action}</li>";
                }

                Mail::send([], [], function ($m) use ($member, $meeting, $decisionsHtml, $tasksHtml) {
                    $m->to($member->email)
                      ->subject("📋 Compte-rendu : {$meeting->title} — MindScribe AI")
                      ->html("
                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                            <div style='background: #00004D; padding: 30px; border-radius: 12px 12px 0 0; text-align: center;'>
                                <h1 style='color: white; margin: 0;'>📋 Compte-rendu de réunion</h1>
                            </div>
                            <div style='background: #f8f9ff; padding: 30px; border-radius: 0 0 12px 12px;'>
                                <h2 style='color: #00004D;'>{$meeting->title}</h2>
                                <p style='color: #666;'>🕐 " . $meeting->created_at->format('d/m/Y à H:i') . "</p>

                                <h3 style='color: #4F6FFF; border-bottom: 2px solid #4F6FFF; padding-bottom: 8px;'>📝 Résumé</h3>
                                <p style='color: #444; line-height: 1.6;'>{$meeting->summary}</p>

                                " . ($decisionsHtml ? "
                                <h3 style='color: #00C9A7; border-bottom: 2px solid #00C9A7; padding-bottom: 8px;'>✅ Décisions prises</h3>
                                <ul style='color: #444;'>$decisionsHtml</ul>
                                " : "") . "

                                " . ($tasksHtml ? "
                                <h3 style='color: #E8A838; border-bottom: 2px solid #E8A838; padding-bottom: 8px;'>📌 Tâches assignées</h3>
                                <ul style='color: #444;'>$tasksHtml</ul>
                                " : "") . "

                                <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
                                <p style='color: #999; font-size: 12px; text-align: center;'>
                                    MindScribe AI — Compte-rendu généré automatiquement par IA
                                </p>
                            </div>
                        </div>
                      ");
                });

                $participant->update(['summary_sent_at' => now()]);

            } catch (\Throwable $e) {
                // Log mais ne bloque pas
            }
        }
    }
}