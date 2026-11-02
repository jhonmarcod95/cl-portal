<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Remark extends Model
{
    //
    public function remarksStatuses(){
        return $this->hasMany(RemarksStatus::class, 'remarks_id', 'id');
    }
}
