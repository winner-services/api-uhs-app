<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="RapportIntervention",
 *     type="object",
 *     title="RapportIntervention",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="ticket_id", type="integer", example=2),
 *     @OA\Property(property="addedBy", type="integer", example=2),
 *     @OA\Property(property="description", type="string", example="Pompe changée avec succès"),
 *     @OA\Property(property="cout_reparation", type="number", format="float", example=120.50),
 *     @OA\Property(property="date_rapport", type="string", format="date", example="2025-09-22"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */

class RapportIntervention extends Model
{
    protected $fillable = ['ticket_id', 'description', 'cout_reparation', 'date_rapport', 'addedBy','dette_amount'];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    // Relation avec User
    public function user()
    {
        return $this->belongsTo(User::class, 'addedBy');
    }
}
