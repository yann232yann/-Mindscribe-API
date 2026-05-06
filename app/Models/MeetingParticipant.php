<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeetingParticipant extends Model
{
    protected $fillable = ['meeting_id', 'team_member_id', 'notified_at', 'summary_sent_at'];

    protected $casts = [
        'notified_at'    => 'datetime',
        'summary_sent_at' => 'datetime',
    ];

    public function meeting()
    {
        return $this->belongsTo(Meeting::class);
    }

    public function member()
    {
        return $this->belongsTo(TeamMember::class, 'team_member_id');
    }
}