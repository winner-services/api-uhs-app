<?php

namespace App\Http\Controllers\Api\Abonnement;

use App\Http\Controllers\Controller;
use App\Models\AbonnementCategorie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AbonnementCategoryController extends Controller
{
/**
     * @OA\Get(
     * path="/api/category_abonne.getAllData",
     * summary="Liste des catégories d'abonnés",
     * tags={"Abonnement Categories"},
     * @OA\Response(response=200, description="Liste récupérée avec succès"),
     * )
     */
    public function index()
    {
        $page = request("paginate", 10);
        $q = request("q", "");
        $sort_direction = request('sort_direction', 'desc');
        $sort_field = request('sort_field', 'id');
        $data = AbonnementCategorie::latest()
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
     * path="/api/category_abonne.getOptionsData",
     * summary="Liste des catégories d'abonnés",
     * tags={"Abonnement Categories"},
     * @OA\Response(response=200, description="Liste récupérée avec succès"),
     * )
     */
    public function getallData()
    {
        $result = [
            'message' => "OK",
            'success' => true,
            'data' => AbonnementCategorie::latest()->get(),
            'status' => 200
        ];
        return response()->json($result);
    }

    /**
     * @OA\Post(
     * path="/api/category_abonne.store",
     * summary="Créer une catégorie d'abonné",
     * tags={"Abonnement Categories"},
     * @OA\RequestBody(
     *    required=true,
     *    @OA\JsonContent(
     *       required={"designation","prix_mensuel"},
     *       @OA\Property(property="designation", type="string", example="Église"),
     *       @OA\Property(property="prix_mensuel", type="number", format="float", example="50.00")
     *    )
     * ),
     * @OA\Response(response=201, description="Catégorie créée avec succès"),
     * @OA\Response(response=422, description="Erreur de validation")
     * )
     */
    public function store(Request $request)
    {
        $rules = [
            'designation'   => ['required', 'string', 'max:255', 'unique:abonnement_categories,designation'],
            'prix_mensuel'  => ['nullable', 'numeric', 'min:0'],
        ];

        $messages = [
            'designation.unique' => 'Cette désignation existe déjà.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Les données envoyées ne sont pas valides.',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $categorie = AbonnementCategorie::create([
                'designation'  => $request->input('designation'),
                'prix_mensuel' => $request->input('prix_mensuel'),
            ]);

            DB::commit();

            return response()->json([
                'message' => "Catégorie ajoutée avec succès",
                'success' => true,
                'status'  => 201,
                'data'    => $categorie
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erreur lors de la création de la catégorie.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     * path="/api/category_abonne.update/{id}",
     * summary="Mettre à jour une catégorie d'abonné",
     * tags={"Abonnement Categories"},
     * @OA\Parameter(name="id", in="path", required=true, description="ID de la catégorie"),
     * @OA\RequestBody(
     *    required=true,
     *    @OA\JsonContent(
     *       @OA\Property(property="designation", type="string", example="Hôpital"),
     *       @OA\Property(property="prix_mensuel", type="number", format="float", example="30.00")
     *    )
     * ),
     * @OA\Response(response=200, description="Catégorie mise à jour avec succès"),
     * @OA\Response(response=404, description="Catégorie non trouvée")
     * )
     */
    public function update(Request $request, $id)
    {
        $categorie = AbonnementCategorie::find($id);
        if (!$categorie) {
            return response()->json([
                'message' => 'Catégorie non trouvée'
            ], 404);
        }

        $rules = [
            'designation'   => ['required', 'string', 'max:255', 'unique:abonnement_categories,designation,' . $id],
            'prix_mensuel'  => ['nullable', 'numeric', 'min:0'],
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

            $categorie->update([
                'designation'  => $request->input('designation'),
                'prix_mensuel' => $request->input('prix_mensuel'),
            ]);

            DB::commit();

            return response()->json([
                'message' => "Catégorie mise à jour avec succès",
                'success' => true,
                'status'  => 200,
                'data'    => $categorie
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erreur lors de la mise à jour.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     * path="/api/category_abonne.delete/{id}",
     * summary="Supprimer une catégorie d'abonné",
     * tags={"Abonnement Categories"},
     * @OA\Parameter(name="id", in="path", required=true, description="ID de la catégorie"),
     * @OA\Response(response=200, description="Catégorie supprimée avec succès"),
     * @OA\Response(response=404, description="Catégorie non trouvée")
     * )
     */
    public function destroy($id)
    {
        $categorie = AbonnementCategorie::find($id);
        if (!$categorie) {
            return response()->json([
                'message' => 'Catégorie non trouvée'
            ], 404);
        }

        try {
            DB::beginTransaction();
            $categorie->delete();
            DB::commit();

            return response()->json([
                'message' => "Catégorie supprimée avec succès",
                'success' => true,
                'status'  => 200
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erreur lors de la suppression.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
