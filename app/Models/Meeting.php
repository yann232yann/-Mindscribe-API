<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Meeting extends Model
{
    protected $fillable = [
        'user_id', 'title', 'audio_path',
        'transcription', 'summary', 'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function decisions()
    {
        return $this->hasMany(Decision::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function participants()
    {
    return $this->belongsToMany(TeamMember::class, 'meeting_participants', 'meeting_id', 'team_member_id')
                ->withPivot('notified_at', 'summary_sent_at')
                ->withTimestamps();
    }
}