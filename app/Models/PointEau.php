<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PointEau extends Model
{
    protected $fillable = ['abonne_id', 'localisation', 'numero_compteur', 'status'];

    public function abonne() {
        return $this->belongsTo(Abonne::class);
    }
}
