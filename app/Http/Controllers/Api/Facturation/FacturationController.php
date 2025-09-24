<?php

namespace App\Http\Controllers\Api\Facturation;

use App\Http\Controllers\Controller;
use App\Models\Facturation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

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
        $facturations = Facturation::with('abonne','user')->get();

        return response()->json([
            'success' => true,
            'data'    => $facturations
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/facturations.store",
     *     summary="Créer une facturation",
     *     description="Ajout d’une nouvelle facturation pour un abonné",
     *     tags={"Facturations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"abonne_id","mois","montant","status","date_emission","addedBy"},
     *             @OA\Property(property="abonne_id", type="integer", example=1),
     *             @OA\Property(property="mois", type="string", example="09-2025"),
     *             @OA\Property(property="montant", type="number", format="float", example=150.75),
     *             @OA\Property(property="dette", type="number", format="float", example=50.00),
     *             @OA\Property(property="status", type="string", example="Non payé"),
     *             @OA\Property(property="date_emission", type="string", format="date", example="2025-09-01"),
     *             @OA\Property(property="date_paiement", type="string", format="date", example="2025-09-10"),
     *             @OA\Property(property="addedBy", type="integer", example=2)
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
    public function store(Request $request)
    {
        $rules = [
            'abonne_id'     => ['required', 'integer', 'exists:abonnes,id'],
            'mois'          => ['required', 'string'],
            'montant'       => ['required', 'numeric', 'min:0'],
            'dette'         => ['nullable', 'numeric', 'min:0'],
            'status'        => ['required', 'string'],
            'date_emission' => ['required', 'date'],
            'date_paiement' => ['nullable', 'date'],
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Les données envoyées ne sont pas valides.',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();
            $user = Auth::user();

            $facturation = Facturation::create([
                'abonne_id' => $request->abonne_id,
                'mois' => $request->mois,
                'montant' => $request->montant,
                'dette' => $request->dette,
                'status' => $request->status,
                'date_emission' => $request->date_emission,
                'date_paiement' => $request->date_paiement,
                'addedBy' => $user->id
            ]);

            DB::commit();

            return response()->json([
                'message' => "Facturation ajoutée avec succès",
                'success' => true,
                'data'    => $facturation
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => "Erreur lors de la création",
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/facturations.update/{id}",
     *     summary="Modifier une facturation",
     *     description="Mettre à jour une facturation existante",
     *     tags={"Facturations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id", in="path", required=true, description="ID de la facturation", @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="mois", type="string", example="09-2025"),
     *             @OA\Property(property="montant", type="number", example=200.50),
     *             @OA\Property(property="dette", type="number", example=30.00),
     *             @OA\Property(property="status", type="string", example="Payé"),
     *             @OA\Property(property="date_emission", type="string", format="date", example="2025-09-01"),
     *             @OA\Property(property="date_paiement", type="string", format="date", example="2025-09-12")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Facturation mise à jour avec succès"),
     *     @OA\Response(response=404, description="Facturation non trouvée"),
     *     @OA\Response(response=422, description="Erreur de validation")
     * )
     */
    public function update(Request $request, $id)
    {
        $facturation = Facturation::find($id);

        if (!$facturation) {
            return response()->json(['message' => "Facturation non trouvée"], 404);
        }

        $rules = [
            'mois'          => ['sometimes', 'string'],
            'montant'       => ['sometimes', 'numeric', 'min:0'],
            'dette'         => ['sometimes', 'numeric', 'min:0'],
            'status'        => ['sometimes', 'string'],
            'date_emission' => ['sometimes', 'date'],
            'date_paiement' => ['nullable', 'date'],
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Les données envoyées ne sont pas valides.',
                'errors'  => $validator->errors()
            ], 422);
        }

        $facturation->update($request->all());

        return response()->json([
            'message' => "Facturation mise à jour avec succès",
            'success' => true,
            'data'    => $facturation
        ], 200);
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
}
