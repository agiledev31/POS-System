<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentWithCreditCard extends Model
{

    protected $table = 'payment_with_credit_card';

    protected $fillable = [
        'workspace_id', 'payment_id', 'customer_id', 'customer_stripe_id', 'charge_id',
    ];

    protected $casts = [
        'workspace_id' => 'integer',
        'payment_id' => 'integer',
        'customer_id' => 'integer',
    ];

    public function workspace()
    {
        return $this->belongsTo('App\Models\Workspace');
    }
}
