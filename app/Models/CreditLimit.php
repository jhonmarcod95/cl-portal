<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class CreditLimit extends Model implements Auditable
{
    //
    use \OwenIt\Auditing\Auditable;

    public function clStatus()
    {
        return $this->hasOne(Status::class, 'id', 'status');
    }
}
