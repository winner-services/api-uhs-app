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
}
