<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Server extends Model
{

    protected $fillable = [
        'workspace_id', 'mail_mailer','sender_name','host', 'port', 'username', 'password', 'encryption',
    ];

    protected $casts = [
        'workspace_id' => 'integer',
        'port' => 'integer',
    ];

    public function workspace()
    {
        return $this->belongsTo('App\Models\Workspace');
    }

}
