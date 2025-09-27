<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PointEau extends Model
{
    protected $fillable = ['lat', 'long', 'numero_compteur', 'status','matricule'];
    
    public function scopeSearh($query, $term)
    {
        $term = "%$term%";
        $query->where(function ($query) use ($term) {
            $query->where('point_eaus.numero_compteur', 'like', $term)
            ->orWhere('lat', 'like', $term)
            ->orWhere('status', 'like', $term)
            ->orWhere('matricule', 'like', $term)
            ->orWhere('long', 'like', $term);
        });
    }
}
