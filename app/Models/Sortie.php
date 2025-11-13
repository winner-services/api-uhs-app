<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sortie extends Model
{
    protected $fillable = [
        'quantite',
        'prix_unit_vente',
        'product_id',
        'addedBy',
        'reference',
        'account_id'
    ];
}
