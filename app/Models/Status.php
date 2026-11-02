<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Status extends Model
{
    //
    public function remarksStatuses()
    {
        return $this->hasMany(RemarksStatus::class, 'status_id', 'id');
    }
}
