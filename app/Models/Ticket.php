<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $fillable = [
        'point_id', 'description', 'statut',
        'priorite', 'technicien_id', 'date_ouverture', 'date_cloture'
    ];
}
