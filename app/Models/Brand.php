<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    protected $dates = ['deleted_at'];

    protected $fillable = [
        'name', 'description', 'image', 'workspace_id'
    ];

    protected $casts = [
        'workspace_id' => 'integer',
    ];

    public function workspace()
    {
        return $this->belongsTo('App\Models\Workspace');
    }

}
