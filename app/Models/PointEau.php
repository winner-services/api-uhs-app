<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PointEau extends Model
{
    protected $fillable = ['lat', 'long', 'numero_compteur', 'status', 'matricule', 'village', 'quartier', 'num_avenue', 'num_parcelle', 'nom_chef', 'contact'];

    public function scopeSearh($query, $term)
    {
        $term = "%$term%";
        $query->where(function ($query) use ($term) {
            $query->where('point_eaus.numero_compteur', 'like', $term)
                ->orWhere('lat', 'like', $term)
                ->orWhere('status', 'like', $term)
                ->orWhere('matricule', 'like', $term)
                ->orWhere('long', 'like', $term)
                ->orWhere('village', 'like', $term)
                ->orWhere('quartier', 'like', $term)
                ->orWhere('num_parcelle', 'like', $term)
                ->orWhere('nom_chef', 'like', $term)
                ->orWhere('contact', 'like', $term)
                ->orWhere('num_avenue', 'like', $term);
        });
    }
    public function abonnements()
    {
        return $this->hasMany(PointEauAbonne::class, 'point_eau_id');
    }

    public function borniers()
    {
        return $this->hasMany(Bornier::class, 'borne_id');
    }
}
