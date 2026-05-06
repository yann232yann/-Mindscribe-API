<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable = ['meeting_id', 'assignee', 'action', 'is_done'];

    protected $casts = ['is_done' => 'boolean'];

    public function meeting()
    {
        return $this->belongsTo(Meeting::class);
    }
}