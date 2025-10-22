<?php

namespace App\Http\Controllers\Api\DashBoard;

use App\Http\Controllers\Controller;
use App\Models\Abonne;
use App\Models\Facturation;
use App\Models\PointEau;
use App\Models\Ticket;
use App\Models\TrasactionTresorerie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PDO;

class DashBoardController extends Controller
{
    public function indexMobile()
    {
        $total_factures = Facturation::count();
        $total_factures_paye = Facturation::where('status', 'payé')->count();
        $total_factures_acompte = Facturation::where('status', 'acompte')->count();
        $total_factures_insolde = Facturation::where('status', 'insoldée')->count();
        return response()->json([
            'success' => true,
            'status' => 200,
            'total_factures' => $total_factures,
            'total_factures_paye' => $total_factures_paye,
            'total_factures_acompte' => $total_factures_acompte,
            'total_factures_insolde' => $total_factures_insolde
        ]);
    }

    /**
     * @OA\Get(
     *      path="/api/dashBoardAdmin.getData",
     *      operationId="indexWeb",
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

    public function indexWeb()
    {
        $abonnesTotaux = Abonne::count();
        $montantPaye = TrasactionTresorerie::query()
            ->where('transaction_type', 'RECETTE')
            // ->where('motif', 'LIKE', '%Paiement de la facture%')
            ->sum('amount');
        $montantImpayes = Facturation::where('status', 'insoldée')->sum('montant');
        $montantFacture = $montantPaye + $montantImpayes;
        $ticketsOuverts = Ticket::count();
        $ticketsResolus = Ticket::where('statut', 'CLOTURE')->count();
        $ticketsAttente = Ticket::where('statut', 'En attente')->count();
        $randomBornes = PointEau::all();
        $interventionCloture = Ticket::where('statut', 'CLOTURE')->count();

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
            'randomBornes' => $randomBornes
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
