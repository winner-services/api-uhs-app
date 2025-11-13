<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrasactionTresorerie extends Model
{
    protected $fillable =[
        'motif',
        'transaction_date',
        'account_id',
        'amount',
        'transaction_type',
        'facturation_id',
        'solde',
        'status',
        'reference',
        'addedBy',
        'beneficiaire'
    ];

    public function scopeSearh($query, $term)
    {
        $term = "%$term%";
        $query->where(function ($query) use ($term) {
            $query->where('trasaction_tresoreries.transaction_type', 'like', $term)
                ->orWhere('transaction_date', 'like', $term)
                ->orWhere('motif', 'like', $term)
                ->orWhere('tresoreries.designation', 'like', $term);
        });
    }
}
