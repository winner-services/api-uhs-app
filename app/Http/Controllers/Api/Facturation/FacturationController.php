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
        $q = request("q", "");
        $sort_direction = request('sort_direction', 'desc');
        // $sort_field = request('sort_field', 'id');
        $data = Facturation::with('abonne', 'user')
            ->orderByRaw("
        CASE 
            WHEN status = 'impayée' THEN 1
            WHEN status = 'avance sur payement' THEN 2
            WHEN status = 'insoldée' THEN 2
            WHEN status = 'payée' THEN 3
            ELSE 4
        END ASC
    ")
            ->latest('created_at') // ensuite par date
            // ->orderBy($sort_field, $sort_direction)
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
            // 1️⃣ Récupérer tous les abonne_ids reliés à un point d’eau
            $abonneIds = PointEauAbonne::distinct()->pluck('abonne_id');

            // 2️⃣ Charger les abonnés + leur catégorie en une seule requête
            $abonnes = Abonne::with('categorie')
                ->whereIn('id', $abonneIds)
                ->get();

            // 3️⃣ Charger les factures déjà générées pour éviter doublons
            $facturesExistantes = Facturation::whereIn('abonne_id', $abonneIds)
                ->where('mois', $mois)
                ->pluck('abonne_id')
                ->toArray();

            // 4️⃣ Charger les factures du mois précédent en une seule fois
            $facturesPrecedentes = Facturation::whereIn('abonne_id', $abonneIds)
                ->where('mois', $moisPrecedent)
                ->get()
                ->keyBy('abonne_id'); // clé = abonne_id

            $insertData = [];

            // foreach ($abonnes as $abonne) {
            //     if (in_array($abonne->id, $facturesExistantes)) {
            //         continue; // facture déjà créée ce mois
            //     }

            //     $prixMensuel = $abonne->categorie->prix_mensuel ?? 0;
            //     $facturePrecedente = $facturesPrecedentes->get($abonne->id);

            //     if ($facturePrecedente && $facturePrecedente->status !== 'payé') {
            //         $dette = $facturePrecedente->dette + $facturePrecedente->montant;
            //     } else {
            //         $dette = $prixMensuel;
            //     }

            //     $insertData[] = [
            //         'abonne_id'     => $abonne->id,
            //         'mois'          => $mois,
            //         'montant'       => $prixMensuel,
            //         'dette'         => $dette,
            //         'status'        => 'impayé',
            //         'date_emission' => $date->toDateString(),
            //         'addedBy'       => $user->id,
            //         'created_at'    => now(),
            //         'updated_at'    => now(),
            //         'reference'     => fake()->unique()->numerify('FAC-#####')
            //     ];
            // }

            foreach ($abonnes as $abonne) {
                if (in_array($abonne->id, $facturesExistantes)) {
                    continue; // facture déjà créée ce mois
                }

                $prixMensuel = $abonne->categorie->prix_mensuel ?? 0;
                $facturePrecedente = $facturesPrecedentes->get($abonne->id);

                if ($facturePrecedente) {
                    // ✅ Cas où il existe déjà une facture précédente
                    if ($facturePrecedente->status !== 'payé') {
                        $dette  = $facturePrecedente->dette + $facturePrecedente->montant;
                        $status = 'impayé'; // 🔴 Nouvel état quand il y a une dette
                        $facturePrecedente->status = 'insoldée';
                        $facturePrecedente->save();
                    } else {
                        $dette  = 0;
                        $status = 'impayé';
                    }
                } else {
                    // ✅ Première facturation → dette = 0
                    $dette  = 0;
                    $status = 'impayé';
                }

                $insertData[] = [
                    'abonne_id'     => $abonne->id,
                    'mois'          => $mois,
                    'montant'       => $prixMensuel,
                    'dette'         => $dette,
                    'status'        => $status, // 💡 on insère selon le cas
                    'date_emission' => $date->toDateString(),
                    'addedBy'       => $user->id,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                    'reference'     => fake()->unique()->numerify('FAC-#####')
                ];
            }
            // 5️⃣ Insertion en une seule requête
            if (!empty($insertData)) {
                Facturation::insert($insertData);
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Factures générées avec succès',
                'count'   => count($insertData) // combien insérées
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
