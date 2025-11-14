<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompteComptable extends Model
{
    protected $table = 'compte_comptables';

    protected $fillable = [
        'designation',
        'description',
        'transaction_type',
        'status'
    ];

    public function scopeSearh($query, $term)
    {
        $term = "%$term%";
        $query->where(function ($query) use ($term) {
            $query->where('designation', 'like', $term)
                ->orWhere('transaction_type', 'like', $term)
                ->orWhere('description', 'like', $term);
        });
    }
}
