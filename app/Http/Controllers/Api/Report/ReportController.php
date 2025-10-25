<?php

namespace App\Http\Controllers\Api\Report;

use App\Http\Controllers\Controller;
use App\Models\Facturation;
use App\Models\PointEau;
use App\Models\PointEauAbonne;
use App\Models\Ticket;
use App\Models\Versement;
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
     * path="/api/rapport.point-eau-abonne",
     * summary="Liste des points d’eau abonnes",
     * tags={"Rapports"},
     *     @OA\Parameter(
     *         name="date_start",
     *         in="query",
     *         required=false,
     *         description="Date de début au format YYYY-MM-DD (inclus). Par défaut : début du mois courant.",
     *         @OA\Schema(type="string", format="date", example="2025-10-01")
     *     ),
     *     @OA\Parameter(
     *         name="date_end",
     *         in="query",
     *         required=false,
     *         description="Date de fin au format YYYY-MM-DD (inclus). Par défaut : date du jour.",
     *         @OA\Schema(type="string", format="date", example="2025-10-25")
     *     ),
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
            ->whereBetween('date_operation', [$date_start, $date_end])->get();

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
     * summary="Liste des facturations",
     * tags={"Rapports"},
     *     @OA\Parameter(
     *         name="date_start",
     *         in="query",
     *         required=false,
     *         description="Date de début au format YYYY-MM-DD (inclus). Par défaut : début du mois courant.",
     *         @OA\Schema(type="string", format="date", example="2025-10-01")
     *     ),
     *     @OA\Parameter(
     *         name="date_end",
     *         in="query",
     *         required=false,
     *         description="Date de fin au format YYYY-MM-DD (inclus). Par défaut : date du jour.",
     *         @OA\Schema(type="string", format="date", example="2025-10-25")
     *     ),
     * @OA\Response(response=200, description="Liste récupérée avec succès"),
     * )
     */

    public function rapportFacturations()
    {
        $date_start = request('date_start', date('Y-m-01'));
        $date_end = request('date_end', date('Y-m-d'));
        $data = Facturation::with('pointEauAbonne.abonne', 'user')
            ->orderByRaw("
            CASE 
                WHEN status = 'impayé'  THEN 1
                WHEN status = 'acompte' THEN 2
                WHEN status = 'insoldée' THEN 3
                WHEN status = 'payé'    THEN 4
                ELSE 5
            END
        ")
            ->orderBy('created_at', 'desc')
            ->whereBetween('date_emission', [$date_start, $date_end])->get();

        $result = [
            'message' => "OK",
            'success' => true,
            'status'  => 200,
            'data'    => $data
        ];

        return response()->json($result);
    }

    /**
     * @OA\Get(
     * path="/api/rapport.versements",
     * summary="Liste des versements",
     * tags={"Rapports"},
     *     @OA\Parameter(
     *         name="date_start",
     *         in="query",
     *         required=false,
     *         description="Date de début au format YYYY-MM-DD (inclus). Par défaut : début du mois courant.",
     *         @OA\Schema(type="string", format="date", example="2025-10-01")
     *     ),
     *     @OA\Parameter(
     *         name="date_end",
     *         in="query",
     *         required=false,
     *         description="Date de fin au format YYYY-MM-DD (inclus). Par défaut : date du jour.",
     *         @OA\Schema(type="string", format="date", example="2025-10-25")
     *     ),
     * @OA\Response(response=200, description="Liste récupérée avec succès"),
     * )
     */

    public function versements()
    {
        $date_start = request('date_start', date('Y-m-01'));
        $date_end = request('date_end', date('Y-m-d'));
        $data = Versement::join('tresoreries', 'versements.account_id', '=', 'tresoreries.id')
            ->join('users as u1', 'versements.addedBy', '=', 'u1.id')
            ->join('users as u2', 'versements.agent_id', '=', 'u2.id')
            ->select('versements.*', 'u2.name as agent', 'u1.name as addedBy', 'tresoreries.designation as tresorerie')
            ->latest()
            ->whereBetween('transaction_date', [$date_start, $date_end])->get();
        $result = [
            'message' => "OK",
            'success' => true,
            'status'  => 200,
            'data'    => $data
        ];

        return response()->json($result);
    }

    /**
     * @OA\Get(
     * path="/api/rapport.tickets",
     * summary="Liste des tickets",
     * tags={"Rapports"},
     *     @OA\Parameter(
     *         name="date_start",
     *         in="query",
     *         required=false,
     *         description="Date de début au format YYYY-MM-DD (inclus). Par défaut : début du mois courant.",
     *         @OA\Schema(type="string", format="date", example="2025-10-01")
     *     ),
     *     @OA\Parameter(
     *         name="date_end",
     *         in="query",
     *         required=false,
     *         description="Date de fin au format YYYY-MM-DD (inclus). Par défaut : date du jour.",
     *         @OA\Schema(type="string", format="date", example="2025-10-25")
     *     ),
     * @OA\Response(response=200, description="Liste récupérée avec succès"),
     * )
     */
    public function rapportTickets()
    {
        $date_start = request('date_start', date('Y-m-01'));
        $date_end = request('date_end', date('Y-m-d'));
        $data = Ticket::join('point_eaus', 'tickets.point_id', '=', 'point_eaus.id')
            ->join('users as u1', 'tickets.addedBy', '=', 'u1.id')
            ->join('users as u2', 'tickets.technicien_id', '=', 'u2.id')
            ->select(
                'tickets.*',
                'tickets.statut as status',
                'point_eaus.matricule as point_eau',
                'point_eaus.numero_compteur',
                'point_eaus.lat',
                'point_eaus.long',
                'u1.name as addedBy',
                'u2.name as technicien'
            )->whereBetween('date_ouverture', [$date_start, $date_end])->get();
        $result = [
            'message' => "OK",
            'success' => true,
            'status'  => 200,
            'data'    => $data
        ];

        return response()->json($result);
    }
}
