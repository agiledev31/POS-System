<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailMessage extends Model
{
    protected $table = 'email_messages';

    protected $fillable = [
        'subject','body', 'workspace_id', 'name'
    ];

    protected $casts = [
        'workspace_id' => 'integer',
    ];

    public function workspace()
    {
        return $this->belongsTo('App\Models\Workspace');
    }

}
