<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistoriqueTransactions extends Model
{
    protected $fillable = [
        'account_from_id',
        'account_to_id',
        'montant',
        'type_transaction',
        'description',
        'addedBy',
        'date_transaction'
    ];
}
