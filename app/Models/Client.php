<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{

    protected $dates = ['deleted_at'];

    protected $fillable = [
        'name', 'code', 'address', 'email', 'phone', 'country', 'city','tax_number', 'workspace_id',

    ];

    protected $casts = [
        'code' => 'integer',
        'workspace_id' => 'integer',
    ];

    public function workspace()
    {
        return $this->belongsTo('App\Models\Workspace');
    }
}
