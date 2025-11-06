<?php

namespace App\Http\Controllers\Api\DashBoard;

use App\Http\Controllers\Controller;
use App\Models\Abonne;
use App\Models\Facturation;
use App\Models\PointEau;
use App\Models\Ticket;
use App\Models\TrasactionTresorerie;
use Illuminate\Support\Facades\Auth;

class DashBoardController extends Controller
{
    public function indexMobile()
    {
        $total_factures = Facturation::count();
        $total_factures_paye = Facturation::where('status', 'payé')->count();
        $total_factures_impaye = Facturation::where('status', 'impayé')->count();
        $total_factures_acompte = Facturation::where('status', 'acompte')->count();
        $total_factures_insolde = Facturation::where('status', 'insoldée')->count();
        return response()->json([
            'success' => true,
            'status' => 200,
            'total_factures' => $total_factures,
            'total_factures_paye' => $total_factures_paye,
            'total_factures_acompte' => $total_factures_acompte,
            'total_factures_impaye' => $total_factures_impaye,
            'total_factures_insolde' => $total_factures_insolde
        ]);
    }


    /**
     * @OA\Get(
     * path="/api/dashBoardAdmin.getData",
     * summary="dashBoardAdmin",
     * tags={"DashBoard"},
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

    public function indexWeb()
    {
        $date_start = request('date_start', date('Y-m-01'));
        $date_end = request('date_end', date('Y-m-d'));

        $abonnesTotaux = Abonne::count();
        $montantPaye = TrasactionTresorerie::query()
            ->where('transaction_type', 'RECETTE')
            ->where('motif', 'LIKE', '%Paiement de la facture%')
            ->whereBetween('transaction_date', [$date_start, $date_end])
            ->sum('amount');
        $montantImpayes = Facturation::where('status', 'insoldée')
            ->whereBetween('date_emission', [$date_start, $date_end])
            ->sum('montant');
        $payementMaintenance =         $montantPaye = TrasactionTresorerie::query()
            ->where('transaction_type', 'RECETTE')
            ->where('motif', 'LIKE', '%Paiement facture maintenance%')
            ->whereBetween('transaction_date', [$date_start, $date_end])
            ->sum('amount');
        $montantFacture = $montantPaye + $montantImpayes;
        $ticketsOuverts = Ticket::whereBetween('date_ouverture', [$date_start, $date_end])->count();
        $ticketsResolus = Ticket::where('statut', 'CLOTURE')->whereBetween('date_cloture', [$date_start, $date_end])->count();
        $ticketsAttente = Ticket::where('statut', 'En attente')->whereBetween('date_ouverture', [$date_start, $date_end])->count();
        $interventionCloture = Ticket::where('statut', 'CLOTURE')->whereBetween('date_cloture', [$date_start, $date_end])->count();
        $randomBornes = PointEau::all();

        return response()->json([
            'success' => true,
            'status' => 200,
            'anonnesTotaux' => $abonnesTotaux,
            'montantPaye' => $montantPaye,
            'montantImpayes' => $montantImpayes,
            'montantFacture' => $montantFacture,
            'ticketsOuverts' => $ticketsOuverts,
            'ticketsResolus' => $ticketsResolus,
            'ticketsAttente' => $ticketsAttente,
            'interventionCloture' => $interventionCloture,
            'randomBornes' => $randomBornes,
            'payementMaintenance' => $payementMaintenance
        ]);
    }

    /**
     * @OA\Get(
     *      path="/api/dashBoardTechnicien.getData",
     *      operationId="indexTechnicien",
     *      tags={"DashBoard"},
     *      summary="Récupère tous les rapports d'intervention",
     *      description="Retourne la liste des rapports d'intervention",
     *      @OA\Response(
     *          response=200,
     *          description="Succès",
     *          @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/RapportIntervention"))
     *      )
     * )
     */

    public function indexTechnicien()
    {
        $user = Auth::user();
        $interventionTotaux = Ticket::where('technicien_id', $user->id)->count();
        $interventionEncours = Ticket::where('technicien_id', $user->id)->where('statut', 'EN_COURS')->count();
        $interventionAttente = Ticket::where('technicien_id', $user->id)->where('statut', 'En attente')->count();
        $interventionCloture = Ticket::where('technicien_id', $user->id)->where('statut', 'CLOTURE')->count();
        return response()->json([
            'success' => true,
            'status' => 200,
            'interventionTotaux' => $interventionTotaux,
            'interventionEncours' => $interventionEncours,
            'interventionAttente' => $interventionAttente,
            'interventionCloture' => $interventionCloture
        ]);
    }
}
