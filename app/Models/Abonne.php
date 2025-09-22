<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Abonne extends Model
{
    protected $fillable = ['nom', 'categorie_id', 'telephone', 'adresse'];
}
