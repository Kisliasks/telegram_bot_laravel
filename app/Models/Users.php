<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Users extends Model
{
    protected $table = 'users';
    protected $guarded = false;
    public $timestamps = null;

    public function getTransactionDateAttribute($value)
    {
        return Carbon::parse($value)->format('d.m.Y');
    }
}
