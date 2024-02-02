<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SMSMessage extends Model
{
    protected $table = 'sms_messages';

    protected $fillable = [
        'text','name','workspace_id', 
    ];

    protected $casts = [
        'workspace_id' => 'integer',
    ];

    public function workspace()
    {
        return $this->belongsTo('App\Models\Workspace');
    }
}
