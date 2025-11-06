<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bornier extends Model
{
    protected $fillable = [
        'nom',
        'phone',
        'adresse',
        'borne_id'
    ];
}
