<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="Ticket",
 *     type="object",
 *     title="Ticket",
 *     description="Modèle de ticket de maintenance",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="point_id", type="integer", example=2),
 *     @OA\Property(property="addedBy", type="integer", example=2),
 *     @OA\Property(property="description", type="string", example="Fuite détectée sur le compteur."),
 *     @OA\Property(property="statut", type="string", example="Ouvert"),
 *     @OA\Property(property="priorite", type="string", example="Haute"),
 *     @OA\Property(property="technicien", type="string", example="Jean Mukendi"),
 *     @OA\Property(property="date_ouverture", type="string", format="date", example="2025-09-21"),
 *     @OA\Property(property="date_cloture", type="string", format="date", nullable=true, example="2025-09-25"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-09-21T10:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-09-21T12:00:00Z")
 * )
 */

class Ticket extends Model
{
    protected $fillable = [
        'point_id', 'description', 'statut',
        'priorite', 'technicien', 'date_ouverture', 'date_cloture','addedBy'
    ];

    public function point()
    {
        return $this->belongsTo(PointEau::class, 'point_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'addedBy');
    }
}
