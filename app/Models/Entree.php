<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Entree extends Model
{
    protected $fillable = [
        'quantite',
        'prix_unit_achat',
        'product_id',
        'addedBy',
        'reference',
        'account_id',
        'date_transaction',
        'deleted'
    ];
}
