<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserLead extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'email',
        'company',
        'service',
    ];
}
