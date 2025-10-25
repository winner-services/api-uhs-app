<?php

namespace App\Http\Controllers\Api\Report;

use App\Http\Controllers\Controller;
use App\Models\Facturation;
use App\Models\PointEau;
use App\Models\PointEauAbonne;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * @OA\Get(
     * path="/api/rapport.borne",
     * summary="Liste des points d’eau",
     * tags={"Rapports"},
     * @OA\Response(response=200, description="Liste récupérée avec succès"),
     * )
     */
    public function rapportBorne()
    {
        $date_start = request('date_start', date('Y-m-01'));
        $date_end = request('date_end', date('Y-m-d'));
        $data = PointEau::where('status', 'Actif')
            ->whereBetween('created_at', [$date_start, $date_end])->get();
        return response()->json([
            'message' => 'success',
            'success' => true,
            'status' => 200,
            'data' => $data
        ]);
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
        $date_start = request('date_start', date('Y-m-01'));
        $date_end = request('date_end', date('Y-m-d'));
        $data = PointEauAbonne::join('abonnes', 'point_eau_abonnes.abonne_id', '=', 'abonnes.id')
            ->join('users', 'point_eau_abonnes.addedBy', '=', 'users.id')
            ->join('point_eaus', 'point_eau_abonnes.point_eau_id', '=', 'point_eaus.id')
            ->select('point_eau_abonnes.*', 'point_eaus.numero_compteur', 'point_eaus.matricule', 'abonnes.nom as abonne', 'users.name as addedBy')
            ->whereBetween('created_at', [$date_start, $date_end])->get();

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
