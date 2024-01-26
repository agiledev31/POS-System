<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use HasFactory;

    protected $dates = ['deleted_at'];
    
    protected $fillable = [
        'title','workspace_id','company_id','start_date','end_date','description'
    ];

    protected $casts = [
        'workspace_id' => 'integer',
        'company_id'  => 'integer',
    ];

    public function company()
    {
        return $this->hasOne('App\Models\Company', 'id', 'company_id');
    }

    public function workspace()
    {
        return $this->belongsTo('App\Models\Workspace');
    }
}
