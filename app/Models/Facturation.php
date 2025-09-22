<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Facturation extends Model
{
    protected $fillable = [
        'client_id', 'mois', 'montant', 'dette',
        'status', 'date_emission', 'date_paiement'
    ];

    public function abonne()
    {
        return $this->belongsTo(Abonne::class);
    }
}
