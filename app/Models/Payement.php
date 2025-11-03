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

    public function pointEauAbonne()
    {
        return $this->belongsTo(PointEauAbonne::class);
    }

    public function scopeSearh($query, $term)
    {
        $term = "%$term%";
        $query->where(function ($query) use ($term) {
            $query->where('payements.transaction_date', 'like', $term)
                ->orWhere('payements.reference', 'like', $term)
                ->orWhere('payements.status', 'like', $term)
                ->orWhereHas('pointEauAbonne.abonne', function ($q) use ($term) {
                    $q->where('abonnes.nom', 'like', $term);
                });
        });
    }
}
