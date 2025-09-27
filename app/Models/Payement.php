<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payement extends Model
{
    protected $fillable = [
        'loan_amount',
        'paid_amount',
        'transaction_date',
        'account_id',
        'addedBy',
        'status',
        'reference',
        'facture_id'
    ];
}
