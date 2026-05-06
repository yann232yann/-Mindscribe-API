<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamMember extends Model
{
    protected $fillable = ['name', 'email', 'role', 'phone', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function meetings()
    {
        return $this->belongsToMany(Meeting::class, 'meeting_participants', 'team_member_id', 'meeting_id')
                    ->withPivot('notified_at', 'summary_sent_at')
                    ->withTimestamps();
    }
}