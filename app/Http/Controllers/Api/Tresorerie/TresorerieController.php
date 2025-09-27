<?php

namespace App\Http\Controllers\Api\Tresorerie;

use App\Http\Controllers\Controller;
use App\Models\Tresorerie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TresorerieController extends Controller
{
    /**
     * @OA\Get(
     * path="/api/tresoreries.getAllData",
     * summary="Liste des Tresoreries",
     * tags={"Tresorerie"},
     * @OA\Response(response=200, description="Liste récupérée avec succès"),
     * )
     */
    public function indexTresorerie()
    {
        $page = request("paginate", 10);
        $q = request("q", "");
        $sort_direction = request('sort_direction', 'desc');
        $sort_field = request('sort_field', 'id');
        $data = Tresorerie::latest()
            ->searh(trim($q))
            ->orderBy($sort_field, $sort_direction)
            ->paginate($page);
        $result = [
            'message' => "OK",
            'success' => true,
            'data' => $data,
            'status' => 200
        ];
        return response()->json($result);
    }

    /**
     * @OA\Get(
     * path="/api/Tresoreries.getOptionsData",
     * summary="Liste des Tresoreries",
     * tags={"Tresorerie"},
     * @OA\Response(response=200, description="Liste récupérée avec succès"),
     * )
     */
    public function getOptionsTresorerie()
    {
        $data = Tresorerie::latest()->get();
        $result = [
            'message' => "OK",
            'success' => true,
            'data' => $data,
            'status' => 200
        ];
        return response()->json($result);
    }
    /**
     * @OA\Post(
     *     path="/api/tresoreries.store",
     *     summary="Créer une trésorerie",
     *     tags={"Trésoreries"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"designation","type"},
     *             @OA\Property(property="designation", type="string", example="Caisse principale"),
     *             @OA\Property(property="reference", type="string", example="CAISSE-001"),
     *             @OA\Property(property="type", type="string", example="caisse")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Trésorerie créée avec succès"),
     *     @OA\Response(response=400, description="Erreur de validation"),
     *     @OA\Response(response=409, description="Trésorerie déjà existante")
     * )
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'designation' => 'required|string',
            'reference'   => 'nullable|string',
            'type'        => 'required|string',
        ]);

        // Vérifier les doublons
        $exists = Tresorerie::where('designation', $validated['designation'])
            ->where('type', $validated['type'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Cette trésorerie existe déjà.'
            ], 409);
        }

        $tresorerie = Tresorerie::create([
            'designation' => $validated['designation'],
            'reference' => $validated['reference'],
            'type' => $validated['type'],
            'addedBy' => $user->id
        ]);

        return response()->json([
            'message' => "success",
            'success' => true,
            'status'  => 201,
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/tresoreries.update/{id}",
     *     summary="Mettre à jour une trésorerie",
     *     tags={"Trésoreries"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de la trésorerie",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="designation", type="string", example="Caisse secondaire"),
     *             @OA\Property(property="reference", type="string", example="CAISSE-002"),
     *             @OA\Property(property="type", type="string", example="banque")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Trésorerie mise à jour avec succès"),
     *     @OA\Response(response=404, description="Trésorerie non trouvée")
     * )
     */
    public function update(Request $request, int $id)
    {
        $user = Auth::user();
        $tresorerie = Tresorerie::findOrFail($id);

        $validated = $request->validate([
            'designation' => 'sometimes|required|string',
            'reference'   => 'nullable',
            'type'        => 'sometimes|required|string',
        ]);

        $tresorerie->update([
            'designation' => $validated['designation'],
            'reference' => $validated['reference'],
            'type' => $validated['type'],
            'addedBy' => $user->id ?? $tresorerie->addedBy
        ]);

        return response()->json([
            'message' => "success",
            'success' => true,
            'status'  => 200,
        ], 200);
    }

    /**
     * @OA\Delete(
     *     path="/api/tresoreries.delete/{id}",
     *     summary="Supprimer une trésorerie",
     *     tags={"Trésoreries"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de la trésorerie",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=204, description="Trésorerie supprimée avec succès"),
     *     @OA\Response(response=404, description="Trésorerie non trouvée")
     * )
     */
    public function destroy(int $id)
    {
        $tresorerie = Tresorerie::findOrFail($id);
        $tresorerie->delete();

        return response()->json([
            'message' => "success",
            'success' => true,
            'status'  => 200,
        ], 200);
    }
}
