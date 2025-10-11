<?php

namespace App\Http\Controllers\Api\Facturation;

use App\Http\Controllers\Controller;
use App\Models\Abonne;
use App\Models\Facturation;
use App\Models\PointEauAbonne;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FacturationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/facturations.getAllData",
     *     summary="Liste des facturations",
     *     description="Récupérer toutes les facturations avec leurs abonnés",
     *     tags={"Facturations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Liste des facturations",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Facturation"))
     *         )
     *     )
     * )
     */
    public function index()
    {
        $page = request("paginate", 10);

        $data = Facturation::with('abonne', 'user')
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
            ->paginate($page);

        $result = [
            'message' => "OK",
            'success' => true,
            'data'    => $data,
            'status'  => 200
        ];

        return response()->json($result);
    }

    /**
     * @OA\Delete(
     *     path="/api/facturations.delete/{id}",
     *     summary="Supprimer une facturation",
     *     description="Suppression d’une facturation par ID",
     *     tags={"Facturations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id", in="path", required=true, description="ID de la facturation", @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Facturation supprimée avec succès"),
     *     @OA\Response(response=404, description="Facturation non trouvée")
     * )
     */
    public function destroy($id)
    {
        $facturation = Facturation::find($id);

        if (!$facturation) {
            return response()->json(['message' => "Facturation non trouvée"], 404);
        }

        $facturation->delete();

        return response()->json([
            'message' => "Facturation supprimée avec succès",
            'success' => true
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/facturations.store",
     *     summary="Créer une facturation",
     *     description="Ajout d’une nouvelle facturation pour les abonnés",
     *     tags={"Facturations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"date_emission"},
     *             @OA\Property(property="date_emission", type="string", format="date", example="2025-09-01"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Facturation créée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Facturation ajoutée avec succès"),
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/Facturation")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Erreur de validation"),
     *     @OA\Response(response=500, description="Erreur serveur")
     * )
     */

    public function genererFacturesMensuelles(Request $request)
    {
        $request->validate([
            'date_emission' => 'required|date',
        ]);

        $date = Carbon::parse($request->date_emission);
        $mois = $date->format('m-Y');
        $moisPrecedent = $date->copy()->subMonth()->format('m-Y');
        $user = Auth::user();

        DB::beginTransaction();
        try {
            // 1️⃣ Récupérer tous les abonnements actifs (liaisons point_eau_abonnes)
            $abonnements = PointEauAbonne::with(['abonne.categorie'])->get();

            // 2️⃣ Extraire les IDs des liaisons
            $id_raccordement = $abonnements->pluck('id');

            // 3️⃣ Factures déjà existantes ce mois
            $facturesExistantes = Facturation::whereIn('point_eau_abonnes_id', $id_raccordement)
                ->where('mois', $mois)
                ->pluck('point_eau_abonnes_id')
                ->toArray();

            // 4️⃣ Factures du mois précédent
            $facturesPrecedentes = Facturation::whereIn('point_eau_abonnes_id', $id_raccordement)
                ->where('mois', $moisPrecedent)
                ->get()
                ->keyBy('point_eau_abonnes_id');

            $insertData = [];

            foreach ($abonnements as $raccordement) {

                if (in_array($raccordement->id, $facturesExistantes)) {
                    continue; // déjà facturé ce mois
                }

                // ✅ accéder via $raccordement->abonne->categorie
                $prixMensuel = $raccordement->abonne->categorie->prix_mensuel ?? 0;

                $facturePrecedente = $facturesPrecedentes->get($raccordement->id);

                if ($facturePrecedente) {
                    if ($facturePrecedente->status !== 'payé') {
                        $dette = $facturePrecedente->dette + $facturePrecedente->montant;
                        $status = 'impayé';
                        $facturePrecedente->update(['status' => 'insoldée']);
                    } else {
                        $dette = 0;
                        $status = 'impayé';
                    }
                } else {
                    $dette = 0;
                    $status = 'impayé';
                }

                $insertData[] = [
                    'point_eau_abonnes_id' => $raccordement->id,
                    'mois'                 => $mois,
                    'montant'              => $prixMensuel,
                    'dette'                => $dette,
                    'status'               => $status,
                    'date_emission'        => $date->toDateString(),
                    'addedBy'              => $user->id,
                    'created_at'           => now(),
                    'updated_at'           => now(),
                    'reference'            => fake()->unique()->numerify('FAC-#####')
                ];

                dd($insertData);
            }

            if (!empty($insertData)) {
                Facturation::insert($insertData);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Factures générées avec succès',
                'count'   => count($insertData)
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'line'    => $e->getLine()
            ], 500);
        }
    }
}
