<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Workspace extends Model
{
    use HasFactory;
    protected $dates = ['deleted_at'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'created_by',
    ];

    protected $casts = [
        'created_by' => 'integer',
    ];

    public function owner()
    {
        return $this->hasOne('App\Models\Workspace', 'id', 'created_by');
    }
}
