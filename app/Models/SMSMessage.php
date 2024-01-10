<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SMSMessage extends Model
{
    protected $table = 'sms_messages';

    protected $fillable = [
        'text','name'
    ];

}
