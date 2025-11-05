<?php

namespace App\Http\Controllers\Api\Rapport;

use App\Http\Controllers\Controller;
use App\Models\Rapport;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RapportController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/depenses.index",
     *     summary="Afficher toutes les dépenses avec leurs détails associés",
     *     tags={"Dépenses"},
     *     @OA\Response(
     *         response=200,
     *         description="Liste des dépenses récupérée avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="Liste des dépenses récupérée avec succès"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="date", type="string", example="2025-10-03"),
     *                     @OA\Property(property="description", type="string", example="Achat matériel"),
     *                     @OA\Property(property="status", type="string", example="Cloturer"),
     *                     @OA\Property(property="ticket_id", type="integer", example=2),
     *                     @OA\Property(property="addedBy", type="integer", example=1),
     *                     @OA\Property(
     *                         property="details",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=10),
     *                             @OA\Property(property="motif", type="string", example="Papeterie"),
     *                             @OA\Property(property="amount", type="number", example=150.75)
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=500, description="Erreur interne du serveur")
     * )
     */
    public function indexDepense()
    {
        try {
            $page = request('paginate', 10);
            $q = request('q', '');
            $sort_direction = request('sort_direction', 'desc');
            $sort_field = request('sort_field', 'id');

            $data = Rapport::with([
                'details',
                'ticket.user', // si ticket a un user
                'ticket.point.abonnements.abonne', // utilise la relation 'abonnements' que tu as définie
                'user'
            ])
                ->when(trim($q) !== '', function ($query) use ($q) {
                    // recherche sur description, ticket.titre, user.name, ou abonne.name via abonnements
                    $query->where(function ($query) use ($q) {
                        $query->where('description', 'LIKE', "%{$q}%")
                            ->orWhereHas('ticket', function ($q2) use ($q) {
                                $q2->where('titre', 'LIKE', "%{$q}%");
                            })
                            ->orWhereHas('user', function ($q3) use ($q) {
                                $q3->where('name', 'LIKE', "%{$q}%");
                            })
                            ->orWhereHas('ticket.point.abonnements', function ($q4) use ($q) {
                                $q4->whereHas('abonne', function ($q5) use ($q) {
                                    $q5->where('name', 'LIKE', "%{$q}%");
                                });
                            });
                    });
                })
                ->orderBy($sort_field, $sort_direction)
                ->paginate($page);

            // Ajoute un champ 'abonnes' (array des noms d'abonnés) à chaque item du paginate
            $data->getCollection()->transform(function ($rapport) {
                $abonnes = [];

                if ($rapport->ticket && $rapport->ticket->point) {
                    $point = $rapport->ticket->point;

                    if ($point->relationLoaded('abonnements') && $point->abonnements) {
                        // On récupère les noms d'abonnés via la relation chargée
                        $abonnes = $point->abonnements
                            ->pluck('abonne.name') // nécessite que chaque PointEauAbonne ait la relation 'abonne' définie
                            ->filter()
                            ->unique()
                            ->values()
                            ->all();
                    } elseif ($point->abonnements) {
                        // fallback si relation pas explicitement marquée comme loaded mais accessible
                        $abonnes = collect($point->abonnements)
                            ->pluck('abonne.name')
                            ->filter()
                            ->unique()
                            ->values()
                            ->all();
                    }
                }

                $rapport->setAttribute('abonnes', $abonnes);

                return $rapport;
            });

            $result = [
                'message' => 'Liste des dépenses récupérée avec succès',
                'success' => true,
                'status' => 200,
                'data' => $data,
            ];

            return response()->json($result);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des dépenses',
                'success' => false,
                'status' => 500,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // public function indexDepense()
    // {
    //     try {
    //         $page = request('paginate', 10);
    //         $q = request('q', '');
    //         $sort_direction = request('sort_direction', 'desc');
    //         $sort_field = request('sort_field', 'id');

    //         $data = Rapport::with(['details', 'ticket', 'user', 'ticket.point'])
    //             ->when(trim($q) !== '', function ($query) use ($q) {
    //                 // Exemple de recherche sur la description ou le ticket
    //                 $query->where('description', 'LIKE', "%{$q}%")
    //                     ->orWhereHas('ticket', function ($q2) use ($q) {
    //                         $q2->where('titre', 'LIKE', "%{$q}%");
    //                     })
    //                     ->orWhereHas('user', function ($q3) use ($q) {
    //                         $q3->where('name', 'LIKE', "%{$q}%");
    //                     });
    //             })
    //             ->orderBy($sort_field, $sort_direction)
    //             ->paginate($page);

    //         $result = [
    //             'message' => 'Liste des dépenses récupérée avec succès',
    //             'success' => true,
    //             'status' => 200,
    //             'data' => $data,
    //         ];

    //         return response()->json($result);
    //     } catch (Exception $e) {
    //         return response()->json([
    //             'message' => 'Erreur lors de la récupération des dépenses',
    //             'success' => false,
    //             'status' => 500,
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }

    /**
     * @OA\Post(
     *     path="/api/depenses.store",
     *     summary="Créer une dépense avec ses détails",
     *     description="Crée une nouvelle dépense (main) et plusieurs détails associés dans une seule transaction.",
     *     operationId="storeDepense",
     *     tags={"Dépenses"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="main", type="object",
     *                 @OA\Property(property="date", type="string", example="2025-10-03"),
     *                 @OA\Property(property="description", type="string", example="lorem ipsum"),
     *                 @OA\Property(property="status", type="string", example="Cloturer"),
     *                 @OA\Property(property="ticket_id", type="integer", example=2)
     *             ),
     *             @OA\Property(property="details", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="motif", type="string", example="test"),
     *                     @OA\Property(property="amount", type="number", example=30.50)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Dépense créée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Dépense créée avec succès"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Erreur de validation"),
     *     @OA\Response(response=500, description="Erreur serveur interne")
     * )
     */
    public function storeDepense(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'main.date' => 'required|date',
            'main.description' => 'nullable|string',
            'main.status' => 'required|string',
            'main.total_price' => 'required',
            'main.ticket_id' => 'required|integer|exists:tickets,id',
            'details' => 'required|array|min:1',
            'details.*.motif' => 'required|string',
            'details.*.amount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Les données envoyées ne sont pas valides.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $depense = DB::transaction(function () use ($request) {
                $user = Auth::user();
                $mainData = $request->input('main');
                $mainData['addedBy'] = $user->id;
                $mainData['dette_amount'] = $request->input('main.total_price', 0);

                $depense = Rapport::create($mainData);

                foreach ($request->input('details') as $detail) {
                    $depense->details()->create($detail);
                }
            });

            return response()->json([
                'message' => 'Dépense créée avec succès',
                'success' => true,
                'status' => 201
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la création de la dépense',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/depenses.update/{id}",
     *     summary="Mettre à jour une dépense existante et ses détails sans les supprimer",
     *     tags={"Dépenses"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de la dépense à modifier",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="main", type="object",
     *                 @OA\Property(property="date", type="string", example="2025-10-03"),
     *                 @OA\Property(property="description", type="string", example="Achat matériel"),
     *                 @OA\Property(property="status", type="string", example="Cloturer"),
     *                 @OA\Property(property="ticket_id", type="integer", example=2)
     *             ),
     *             @OA\Property(property="details", type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="motif", type="string", example="Papeterie"),
     *                     @OA\Property(property="amount", type="number", example=120.5)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Dépense mise à jour avec succès"),
     *     @OA\Response(response=404, description="Dépense non trouvée"),
     *     @OA\Response(response=422, description="Données invalides"),
     *     @OA\Response(response=500, description="Erreur interne du serveur")
     * )
     */
    public function updateDepense(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'main.date' => 'required|date',
            'main.description' => 'nullable|string',
            'main.status' => 'required|string',
            'main.total_price' => 'required',
            'main.ticket_id' => 'required|integer|exists:tickets,id',
            'details' => 'required|array|min:1',
            'details.*.motif' => 'required|string',
            'details.*.amount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Les données envoyées ne sont pas valides.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $depense = Rapport::with('details')->find($id);

            if (!$depense) {
                return response()->json([
                    'message' => 'Dépense non trouvée',
                    'status' => 404
                ], 404);
            }

            DB::transaction(function () use ($depense, $request) {
                // 🧩 Met à jour les infos principales
                $mainData = $request->input('main');
                $depense->update($mainData);

                // 🧩 Met à jour les détails un par un
                foreach ($request->input('details') as $detailData) {
                    if (isset($detailData['id'])) {
                        // Si le détail existe -> on le met à jour
                        $detail = $depense->details()->where('id', $detailData['id'])->first();
                        if ($detail) {
                            $detail->update($detailData);
                        }
                    } else {
                        // Si pas d'ID -> on le crée
                        $depense->details()->create($detailData);
                    }
                }
            });

            return response()->json([
                'message' => 'Dépense mise à jour avec succès',
                'success' => true,
                'status' => 200
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la mise à jour de la dépense',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/depenses.delete/{id}",
     *     summary="Supprimer une dépense et tous ses détails associés",
     *     tags={"Dépenses"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de la dépense à supprimer",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Dépense supprimée avec succès"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Dépense non trouvée"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erreur interne du serveur"
     *     )
     * )
     */
    public function deleteDepense($id)
    {
        try {
            $depense = Rapport::with('details')->find($id);

            if (!$depense) {
                return response()->json([
                    'message' => 'Dépense non trouvée',
                    'status' => 404
                ], 404);
            }

            DB::transaction(function () use ($depense) {
                // 🔹 Supprimer les détails associés
                $depense->details()->delete();

                // 🔹 Supprimer la dépense principale
                $depense->delete();
            });

            return response()->json([
                'message' => 'Dépense et ses détails supprimés avec succès',
                'success' => true,
                'status' => 200
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la suppression de la dépense',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
