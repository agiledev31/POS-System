<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $dates = ['deleted_at'];

    protected $fillable = [
        'workspace_id', "department","department_head",'company_id'
    ];

    protected $casts = [
        'workspace_id' => 'integer', 
        'department_head' => 'integer',
        'company_id' => 'integer',
    ];


    public function employee()
    {
        return $this->hasOne('App\Models\Employee', 'id', 'department_head');
    }

    public function company()
    {
        return $this->hasOne('App\Models\Company', 'id', 'company_id');
    }

    public function workspace()
    {
        return $this->belongsTo('App\Models\Workspace');
    }

}
