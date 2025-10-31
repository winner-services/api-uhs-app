<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayementPane extends Model
{
    protected $fillable = [
        'transacion_date',
        'reference',
        'loan_amount',
        'paid_amount',
        'point_eau_abonnes_id',
        'acount_id',
        'addedBy'
    ];
}
