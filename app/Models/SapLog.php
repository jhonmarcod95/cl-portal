<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SapLog extends Model
{
    //
    protected $fillable = [
        'type',
        'parameter',
        'sap_server',
        'message',
    ];
}
