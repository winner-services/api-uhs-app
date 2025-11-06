<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bornier extends Model
{
    protected $fillable = [
        'nom',
        'phone',
        'adresse',
        'borne_id',
        'addedBy'
    ];

    public function scopeSearh($query, $term)
    {
        $term = "%$term%";
        $query->where(function ($query) use ($term) {
            $query->where('borniers.nom', 'like', $term)
                ->orWhere('borniers.phone', 'like', $term)
                ->orWhere('borniers.adresse', 'like', $term);
        });
    }
}
