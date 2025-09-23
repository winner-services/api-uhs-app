<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbonnementCategorie extends Model
{
    protected $fillable = ['designation', 'prix_mensuel'];

    public function abonne()
    {
        return $this->hasMany(Abonne::class, 'categorie_id');
    }

    public function scopeSearh($query, $term)
    {
        $term = "%$term%";
        $query->where(function ($query) use ($term) {
            $query->where('designation', 'like', $term);
        });
    }
}
