<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Abonne extends Model
{
    protected $fillable = [
        'nom',
        'categorie_id',
        'telephone',
        'adresse',
        'addedBy',
        'gender',
        'status',
        'piece_identite',
        'num_piece_identite'
    ];

    public function categorie()
    {
        return $this->belongsTo(AbonnementCategorie::class, 'categorie_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'addedBy');
    }

    public function scopeSearh($query, $term)
    {
        $term = "%$term%";
        $query->where(function ($query) use ($term) {
            $query->where('abonnes.nom', 'like', $term)
                ->orWhere('abonnes.adresse', 'like', $term)
                ->orWhere('abonnes.status', 'like', $term)
                ->orWhere('abonnes.num_piece_identite', 'like', $term)
                ->orWhere('abonnes.gender', 'like', $term)
                ->orWhereHas('categorie', function ($q2) use ($term) {
                    $q2->where('abonnement_categories.designation', 'like', $term);
                });
        });
    }
    public function pointsEau()
    {
        return $this->belongsToMany(PointEau::class, 'point_eau_abonnes', 'abonne_id', 'point_eau_id');
    }
}
