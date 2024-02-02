<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class sms_gateway extends Model
{
    protected $table = 'sms_gateway';

    protected $fillable = [
        'title', 'workspace_id', 
    ];
    
    protected $casts = [
        'workspace_id' => 'integer',
    ];

    public function workspace()
    {
        return $this->belongsTo('App\Models\Workspace');
    }
}
