<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Designation extends Model
{
    use HasFactory;

    protected $dates = ['deleted_at'];

    protected $fillable = [
        'workspace_id', 'designation','department_id','company_id'
    ];

    protected $casts = [
        'workspace_id' => 'integer',
        'department_id' => 'integer',
        'company_id'    => 'integer',
    ];


    public function company()
    {
        return $this->hasOne('App\Models\Company', 'id', 'company_id');
    }

    public function department()
    {
        return $this->hasOne('App\Models\Department', 'id', 'department_id');
    }

    public function workspace()
    {
        return $this->belongsTo('App\Models\Workspace');
    }
}
