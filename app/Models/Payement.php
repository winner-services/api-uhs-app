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

    public function facture()
    {
        return $this->belongsTo(Facturation::class, 'facture_id');
    }

    public function scopeSearh($query, $term)
    {
        $term = "%$term%";
        $query->where(function ($query) use ($term) {
            $query->where('payements.transaction_date', 'like', $term)
                ->orWhere('payements.reference', 'like', $term)
                ->orWhere('payements.status', 'like', $term)
                ->orWhereHas('facture.abonne', function ($q) use ($term) {
                    $q->where('abonnes.nom', 'like', $term);
                });
        });
    }
}
