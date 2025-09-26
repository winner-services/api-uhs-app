<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PointEauAbonne extends Model
{
    protected $fillable = [
        'abonne_id',
        'point_eau_id',
        'addedBy'
    ];
    public function abonne()
    {
        return $this->belongsTo(Abonne::class, 'abonne_id');
    }
}
