<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Logistique extends Model
{
    protected $fillable = [
        'date_transaction',
        'previous_quantity',
        'new_quantity',
        'motif',
        'type_transaction',
        'product_id',
        'addedBy'
    ];
}
