<?php

namespace App\Http\Controllers\Api\Report;

use App\Http\Controllers\Controller;
use App\Models\Facturation;
use App\Models\PointEau;
use App\Models\PointEauAbonne;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    /**
     * @OA\Post(
     * path="/api/rapport.borne",
     * summary="get",
     * description="get",
     * security={{ "bearerAuth":{ }}},
     * operationId="rapportBorne",
     * tags={"Rapports"},
     * @OA\RequestBody(
     *    required=true,
     *    description="Get",
     *    @OA\JsonContent(
     *       required={"date_start","date_end"},
     *       @OA\Property(property="date_start", type="string", format="text",example="2025-03-03"),
     *       @OA\Property(property="date_end", type="string", format="text",example="2025-03-03")
     *    ),
     * ),
     * @OA\Response(
     *    response=201,
     *    description="success",
     *     ),
     * @OA\Response(
     *    response=401,
     *    description="existe",
     *     )
     * )
     */

    public function rapportBorne(Request $request)
    {
        $date_start = $request->get('date_start', Carbon::now()->startOfMonth()->toDateString());
        $date_end   = $request->get('date_end', Carbon::now()->toDateString());

        $date_start = Carbon::parse($date_start)->startOfDay();
        $date_end   = Carbon::parse($date_end)->endOfDay();

        $data = PointEau::query()
            ->where('status', 'Actif')
            ->whereBetween('created_at', [$date_start, $date_end])
            ->latest()
            ->get()
            ->map(function ($item) {
                $item->created_at = Carbon::parse($item->created_at)->format('Y-m-d');
                return $item;
            });

        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => 'Liste des bornes actives filtrées par période',
            'data'    => $data,
        ], 200);
    }


    /**
     * @OA\Get(
     * path="/api/rapport.point-eau-abonne",
     * summary="Liste des points d’eau",
     * tags={"Rapports"},
     * @OA\Response(response=200, description="Liste récupérée avec succès"),
     * )
     */
    public function rapportPointEauAbonne()
    {
        $data = PointEauAbonne::join('abonnes', 'point_eau_abonnes.abonne_id', '=', 'abonnes.id')
            ->join('users', 'point_eau_abonnes.addedBy', '=', 'users.id')
            ->join('point_eaus', 'point_eau_abonnes.point_eau_id', '=', 'point_eaus.id')
            ->select('point_eau_abonnes.*', 'point_eaus.numero_compteur', 'point_eaus.matricule', 'abonnes.nom as abonne', 'users.name as addedBy')
            ->latest()->get();

        return response()->json([
            'message' => 'success',
            'success' => true,
            'status' => 200,
            'data' => $data
        ]);
    }

    /**
     * @OA\Get(
     * path="/api/rapport.facturations",
     * summary="Liste",
     * tags={"Rapports"},
     * @OA\Response(response=200, description="Liste récupérée avec succès"),
     * )
     */

    public function rapportFacturations()
    {
        $data = Facturation::with('pointEauAbonne.abonne', 'user')
            ->orderByRaw("
            CASE 
                WHEN status = 'impayé'  THEN 1
                WHEN status = 'acompte' THEN 2
                WHEN status = 'insoldée' THEN 2
                WHEN status = 'payé'    THEN 3
                ELSE 4
            END
        ")
            ->orderBy('created_at', 'desc')
            ->get();

        $result = [
            'message' => "OK",
            'success' => true,
            'status'  => 200,
            'data'    => $data
        ];

        return response()->json($result);
    }
}
