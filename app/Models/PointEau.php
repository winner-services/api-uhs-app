<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PointEau extends Model
{
    protected $fillable = ['abonne_id', 'localisation', 'numero_compteur', 'status'];

    public function abonne() {
        return $this->belongsTo(Abonne::class);
    }

    public function scopeSearh($query, $term)
    {
        $term = "%$term%";
        $query->where(function ($query) use ($term) {
            $query->where('abonnes.nom', 'like', $term)
            ->orWhere('abonnes.adresse', 'like', $term)
            ->orWhere('abonnes.nom', 'like', $term);
        });
    }
}
