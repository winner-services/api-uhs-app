<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PointEauAbonne extends Model
{
    protected $fillable = [
        'abonne_id',
        'point_eau_id',
        'date_operation',
        'addedBy'
    ];
    public function abonne()
    {
        return $this->belongsTo(Abonne::class, 'abonne_id');
    }
    public function pointEau()
    {
        return $this->belongsTo(PointEau::class, 'point_eau_id');
    }
    public function facturations()
    {
        return $this->hasMany(Facturation::class, 'point_eau_abonnes_id');
    }

    public function scopeSearh($query, $term)
    {
        $term = "%$term%";
        $query->where(function ($query) use ($term) {
            $query->where('point_eau_abonnes.date_operation', 'like', $term)
                ->orWhereHas('pointEau', function ($q1) use ($term) {
                    $q1->where('point_eaus.matricule', 'like', $term);
                })
                ->orWhereHas('abonne', function ($q2) use ($term) {
                    $q2->where('abonnes.nom', 'like', $term);
                });
        });
    }
}
