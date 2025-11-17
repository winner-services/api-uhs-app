<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Entree extends Model
{
    protected $fillable = [
        'quantite',
        'prix_unit_achat',
        'product_id',
        'addedBy',
        'reference',
        'account_id',
        'date_transaction',
        'deleted'
    ];

    public function produit()
    {
        return $this->belongsTo(Produit::class, 'product_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'addedBy');
    }

    public function scopeSearh($query, $term)
    {
        $term = "%$term%";
        $query->where(function ($query) use ($term) {
            $query->where('entrees.reference', 'like', $term)
            ->orWhereHas('user', function ($q2) use ($term) {
                    $q2->where('users.name', 'like', $term);
                })
                ->orWhereHas('produit', function ($q2) use ($term) {
                    $q2->where('produits.designation', 'like', $term);
                });
        });
    }

}
