<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{

    protected $fillable = [
        'workspace_id', 'currency_id', 'email', 'CompanyName', 'CompanyPhone', 'CompanyAdress','quotation_with_stock',
        'logo','footer','developed_by','client_id','warehouse_id','default_language',
        'is_invoice_footer','invoice_footer','timezone',
    ];

    protected $casts = [
        'workspace_id' =>'integer',
        'currency_id' => 'integer',
        'client_id' => 'integer',
        'quotation_with_stock' => 'integer',
        'is_invoice_footer' => 'integer',
        'warehouse_id' => 'integer',
    ];

    public function Currency()
    {
        return $this->belongsTo('App\Models\Currency');
    }

    public function Client()
    {
        return $this->belongsTo('App\Models\Client');
    }
    public function workspace()
    {
        return $this->belongsTo('App\Models\Workspace');
    }
}
