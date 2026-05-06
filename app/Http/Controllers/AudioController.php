<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessMeetingAudio;
use App\Models\Meeting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AudioController extends Controller
{
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'nullable|string|max:255',
            'audio' => 'required|file|mimes:mp3,mp4,m4a,wav,ogg,webm|max:102400',
        ]);

        $user = $request->user();
        $path = $request->file('audio')->store('audio', 'public');
        $meetingCount = Meeting::where('user_id', $user->id)->count();
        $title = trim((string) $request->input('title', ''));
        if ($title === '') {
            $title = 'Réunion ' . ($meetingCount + 1);
        }

        $meeting = Meeting::create([
            'user_id'    => $user->id,
            'title'      => $title,
            'audio_path' => $path,
            'status'     => 'pending',
        ]);

        // Lance la transcription en arrière-plan
        try {
            Log::info('Dispatching ProcessMeetingAudio for meeting: ' . $meeting->id);
            (new ProcessMeetingAudio($meeting))->handle();
            Log::info('ProcessMeetingAudio completed for meeting: ' . $meeting->id);
        } catch (\Throwable $e) {
            Log::error('ProcessMeetingAudio failed: ' . $e->getMessage());
            $meeting->update(['status' => 'failed']);
        }

        $meeting->refresh();

        return response()->json([
            'id'         => $meeting->id,
            'title'      => $meeting->title,
            'audio_path' => $meeting->audio_path,
            'status'     => $meeting->status,
            'created_at' => $meeting->created_at,
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $meetings = Meeting::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json($meetings);
    }

    public function show(Request $request, int $meetingId): JsonResponse
    {
        $meeting = Meeting::where('user_id', $request->user()->id)
            ->findOrFail($meetingId);

        $decisions = $meeting->decisions()->pluck('content') ?? [];
        $tasks = $meeting->tasks()->get()->map(fn($t) => [
            'id'       => $t->id,
            'assignee' => $t->assignee,
            'action'   => $t->action,
            'is_done'  => $t->is_done,
        ]) ?? [];

        return response()->json([
            'id'            => $meeting->id,
            'title'         => $meeting->title,
            'audio_path'    => $meeting->audio_path,
            'transcription' => $meeting->transcription,
            'summary'       => $meeting->summary,
            'status'        => $meeting->status,
            'created_at'    => $meeting->created_at,
            'decisions'     => $decisions,
            'tasks'         => $tasks,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $query = $request->query('q', '');

        $meetings = Meeting::where('user_id', $request->user()->id)
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%$query%")
                  ->orWhere('summary', 'like', "%$query%");
            })
            ->orderByDesc('created_at')
            ->get();

        return response()->json($meetings);
    }
}