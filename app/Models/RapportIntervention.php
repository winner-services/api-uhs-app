<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RapportIntervention extends Model
{
    protected $fillable = ['ticket_id', 'description', 'cout_reparation', 'date_rapport'];
}
