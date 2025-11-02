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
        'addedBy',
        'status'
    ];

    public function scopeSearh($query, $term)
    {
        $term = "%$term%";
        $query->where(function ($query) use ($term) {
            $query->where('payement_panes.transaction_date', 'like', $term)
                ->orWhere('payement_panes.reference', 'like', $term)
                ->orWhere('status', 'like', $term);
        });
    }
}
