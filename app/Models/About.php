<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="About",
 *     type="object",
 *     title="About",
 *     required={"id","denomination"},
 *
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="denomination", type="string", example="UHS Asbl"),
 *     @OA\Property(property="details", type="string", example="ONG œuvrant dans l’humanitaire"),
 *     @OA\Property(property="register", type="string", example="RCCM12345"),
 *     @OA\Property(property="national_id", type="string", example="IDN12345"),
 *     @OA\Property(property="tax_number", type="string", example="NIF12345"),
 *     @OA\Property(property="phone", type="string", example="+243900000000"),
 *     @OA\Property(property="address", type="string", example="Goma, RDC"),
 *     @OA\Property(property="logo", type="string", example="logos/uhs.png"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */

class About extends Model
{
    protected $fillable =[
        'denomination',
        'details',
        'register',
        'national_id',
        'tax_number',
        'phone',
        'address',
        'logo'
    ];

    public function getLogoAttribute($value)
    {
        if (!$value) {
            return null;
        }

        $path = storage_path('app/public/' . $value);
        if (file_exists($path)) {
            $type = pathinfo($path, PATHINFO_EXTENSION);
            $data = file_get_contents($path);
            return 'logo/' . $type . ';base64,' . base64_encode($data);
        }

        return $value;
    }
}
