<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Abonne extends Model
{
   protected $fillable = [
        'nom', 'categorie_id', 'telephone', 'adresse', 'addedBy'
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
            ->orWhere('abonnement_categories.designation', 'like', $term);
        });
    }
    public function pointsEau()
    {
        return $this->belongsToMany(PointEau::class, 'point_eau_abonnes', 'abonne_id', 'point_eau_id');
    }
}
