<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Produit extends Model
{
    protected $fillable = [
        'designation',
        'quantite',
        'prix_unit_achat',
        'prix_unit_vente',
        'addedBy'
    ];

    public function scopeSearh($query, $term)
    {
        $term = "%$term%";
        $query->where(function ($query) use ($term) {
            $query->where('produits.designation', 'like', $term)
                ->orWhere('produits.quantite', 'like', $term)
                ->orWhere('produits.prix_unit_vente', 'like', $term);
        });
    }
}
