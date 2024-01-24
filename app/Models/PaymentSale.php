<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentSale extends Model
{
    use SoftDeletes;
    protected $dates = ['deleted_at'];

    protected $fillable = [
        'workspace_id', 'sale_id', 'date', 'montant', 'Ref','change', 'Reglement', 'user_id', 'notes',
    ];

    protected $casts = [
        'workspace_id' => 'integer',
        'montant' => 'double',
        'change'  => 'double',
        'sale_id' => 'integer',
        'user_id' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }

    public function sale()
    {
        return $this->belongsTo('App\Models\Sale');
    }

    public function workspace()
    {
        return $this->belongsTo('App\Models\Workspace');
    }
}
