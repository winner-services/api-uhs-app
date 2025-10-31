<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayementPane extends Model
{
    protected $fillable = [
        'transaction_date',
        'reference',
        'loan_amount',
        'paid_amount',
        'abonne_id',
        'acount_id',
        'addedBy'
    ];
}
