<?php

namespace App\Http\Controllers\Api\Facturation;

use App\Http\Controllers\Controller;
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
        $q = request("q", "");
        $data = Facturation::with('pointEauAbonne.abonne', 'user')
            ->orderByRaw("
            CASE 
                WHEN status = 'impayé'  THEN 1
                WHEN status = 'acompte' THEN 2
                WHEN status = 'payé' THEN 3
                WHEN status = 'insoldée'    THEN 4
                ELSE 5
            END
        ")
            ->orderBy('created_at', 'desc')
            ->searh(trim($q))
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
            /**
             * 1️⃣ Récupérer tous les abonnements actifs (point_eau_abonnes)
             *    + abonné + catégorie
             */
            $abonnements = PointEauAbonne::with(['abonne.categorie'])
                ->whereHas('abonne') // sécurité : exclure les abonnements sans abonné
                ->get();

            if ($abonnements->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun abonnement trouvé pour la facturation.'
                ], 404);
            }

            /**
             * 2️⃣ Extraire les IDs de raccordement
             */
            $idRaccordements = $abonnements->pluck('id');

            /**
             * 3️⃣ Identifier les factures déjà existantes pour ce mois
             */
            $facturesExistantes = Facturation::whereIn('point_eau_abonnes_id', $idRaccordements)
                ->where('mois', $mois)
                ->pluck('point_eau_abonnes_id')
                ->toArray();

            /**
             * 4️⃣ Récupérer les factures du mois précédent
             */
            $facturesPrecedentes = Facturation::whereIn('point_eau_abonnes_id', $idRaccordements)
                ->where('mois', $moisPrecedent)
                ->get()
                ->keyBy('point_eau_abonnes_id');

            /**
             * 5️⃣ Préparer les données à insérer
             */
            $insertData = [];

            foreach ($abonnements as $raccordement) {

                // 🔹 Si déjà facturé ce mois → on saute
                if (in_array($raccordement->id, $facturesExistantes)) {
                    continue;
                }

                // 🔹 Prix mensuel depuis la catégorie
                $prixMensuel = $raccordement->abonne->categorie->prix_mensuel ?? 0;

                // 🔹 Récupérer la facture du mois précédent
                $facturePrecedente = $facturesPrecedentes->get($raccordement->id);

                // 🔹 Calcul de la dette
                if ($facturePrecedente) {
                    if ($facturePrecedente->status !== 'payé') {
                        $dette = $facturePrecedente->dette + $facturePrecedente->dete_en_cours;
                        $facturePrecedente->dete_en_cours = $prixMensuel;
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

                // 🔹 Préparer la ligne à insérer
                $insertData[] = [
                    'point_eau_abonnes_id' => $raccordement->id,
                    'mois'                 => $mois,
                    'montant'              => $prixMensuel,
                    'dete_en_cours'        => $prixMensuel,
                    'deja_paye'            => 0,
                    'dette'                => $dette,
                    'status'               => $status,
                    'date_emission'        => $date->toDateString(),
                    'addedBy'              => $user->id,
                    'created_at'           => now(),
                    'updated_at'           => now(),
                    'reference'            => fake()->unique()->numerify('FAC-#####'),
                ];
            }

            /**
             * 6️⃣ Insertion en masse
             */
            if (!empty($insertData)) {
                Facturation::insert($insertData);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Factures générées avec succès.',
                'count'   => count($insertData),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }

       /**
     * @OA\Get(
     *     path="/api/facturations.Proformat",
     *     summary="Liste",
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
    public function getByStatusGrouped()
    {
        // Statuts ciblés
        $statuses = ['impayé', 'acompte', 'insoldée'];

        // Récupération et groupement
        $factures = Facturation::with('pointEauAbonne.abonne', 'user')
            ->whereIn('status', $statuses)
            ->orderBy('point_eau_abonnes_id')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('point_eau_abonnes_id');

        return response()->json([
            'message' => 'OK',
            'success' => true,
            'data' => $factures,
            'status' => 200
        ]);
    }
}
