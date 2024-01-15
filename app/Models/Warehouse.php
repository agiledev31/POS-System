<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    protected $dates = ['deleted_at'];

    protected $fillable = [
        'name', 'workspace_id', 'mobile', 'country', 'city', 'email', 'zip',
    ];

    protected $casts = [
        'workspace_id' => 'integer',
    ];

    public function assignedUsers()
    {
        return $this->belongsToMany('App\Models\User');
    }

    public function workspace()
    {
        return $this->belongsTo('App\Models\Workspace');
    }
}
