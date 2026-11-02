<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RemarksStatus extends Model
{
    //
    public function remarks(){
        return $this->hasOne(Remark::class, 'id', 'remarks_id');
    }

    public function status(){
        return $this->hasOne(Status::class, 'id', 'status_id');
    }
}
