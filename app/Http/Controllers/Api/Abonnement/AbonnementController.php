<?php

namespace App\Http\Controllers\Api\Abonnement;

use App\Http\Controllers\Controller;
use App\Models\Abonne;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AbonnementController extends Controller
{
    /**
     * @OA\Get(
     * path="/api/abonnes.getAllData",
     * summary="Liste des abonnés",
     * tags={"Abonnés"},
     * @OA\Response(response=200, description="Liste récupérée avec succès"),
     * )
     */
    public function index()
    {

        $page = request("paginate", 10);
        $q = request("q", "");
        $sort_direction = request('sort_direction', 'desc');
        $sort_field = request('sort_field', 'id');
        $data = Abonne::join('abonnement_categories', 'abonnes.categorie_id', '=', 'abonnement_categories.id')
            ->join('users', 'abonnes.addedBy', '=', 'users.id')
            ->select('abonnes.*', 'abonnement_categories.designation as category', 'users.name as addedBy')
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
     * path="/api/abonnes.getOptionsData",
     * summary="Liste des abonnés",
     * tags={"Abonnés"},
     * @OA\Response(response=200, description="Liste récupérée avec succès"),
     * )
     */
    public function getaAbonnellData()
    {
        return response()->json([
            'success' => true,
            'data' => Abonne::with(['categorie', 'user'])->get(),
            'status' => 200
        ]);
    }

    /**
     * @OA\Post(
     * path="/api/abonnes.store",
     * summary="Créer un abonné",
     * tags={"Abonnés"},
     * @OA\RequestBody(
     *    required=true,
     *    @OA\JsonContent(
     *       required={"nom","categorie_id","addedBy"},
     *       @OA\Property(property="nom", type="string", example="Paroisse St Luc"),
     *       @OA\Property(property="categorie_id", type="integer", example=1),
     *       @OA\Property(property="telephone", type="string", example="+243900000000"),
     *       @OA\Property(property="adresse", type="string", example="Goma, RDC"),
     *       @OA\Property(property="addedBy", type="integer", example=2)
     *    )
     * ),
     * @OA\Response(response=201, description="Abonné créé avec succès"),
     * @OA\Response(response=422, description="Erreur de validation")
     * )
     */
    public function store(Request $request)
    {
            

        $rules = [
            'nom'          => ['required', 'string', 'max:255'],
            'categorie_id' => ['required', 'integer', 'exists:abonnement_categories,id'],
            'telephone'    => ['nullable', 'string', 'max:20'],
            'adresse'      => ['nullable', 'string', 'max:255'],
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

            $abonne = Abonne::create([
                'nom' => $request->nom,
                'categorie_id' => $request->categorie_id,
                'telephone' => $request->telephone,
                'adresse' => $request->adresse,
                'addedBy' => $user->id
            ]);

            DB::commit();

            return response()->json([
                'message' => "Abonné ajouté avec succès",
                'success' => true,
                'status'  => 201,
                'data'    => $abonne
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erreur lors de la création de l\'abonné.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     * path="/api/abonnes.update/{id}",
     * summary="Mettre à jour un abonné",
     * tags={"Abonnés"},
     * @OA\Parameter(name="id", in="path", required=true, description="ID de l'abonné"),
     * @OA\RequestBody(
     *    required=true,
     *    @OA\JsonContent(
     *       @OA\Property(property="nom", type="string", example="Hôpital Heal Africa"),
     *       @OA\Property(property="categorie_id", type="integer", example=2),
     *       @OA\Property(property="telephone", type="string", example="+243991234567"),
     *       @OA\Property(property="adresse", type="string", example="Kinshasa, RDC")
     *    )
     * ),
     * @OA\Response(response=200, description="Abonné mis à jour avec succès"),
     * @OA\Response(response=404, description="Abonné non trouvé")
     * )
     */
    public function update(Request $request, $id)
    {
        $abonne = Abonne::find($id);
        if (!$abonne) {
            return response()->json([
                'message' => 'Abonné non trouvé'
            ], 404);
        }

        $rules = [
            'nom'          => ['required', 'string', 'max:255'],
            'categorie_id' => ['required', 'integer', 'exists:abonnement_categories,id'],
            'telephone'    => ['nullable', 'string', 'max:20'],
            'adresse'      => ['nullable', 'string', 'max:255'],
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

            $abonne->update($request->all());

            DB::commit();

            return response()->json([
                'message' => "Abonné mis à jour avec succès",
                'success' => true,
                'status'  => 200,
                'data'    => $abonne
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
     * path="/api/abonnes.delete/{id}",
     * summary="Supprimer un abonné",
     * tags={"Abonnés"},
     * @OA\Parameter(name="id", in="path", required=true, description="ID de l'abonné"),
     * @OA\Response(response=200, description="Abonné supprimé avec succès"),
     * @OA\Response(response=404, description="Abonné non trouvé")
     * )
     */
    public function destroy($id)
    {
        $abonne = Abonne::find($id);
        if (!$abonne) {
            return response()->json([
                'message' => 'Abonné non trouvé'
            ], 404);
        }

        try {
            DB::beginTransaction();
            $abonne->delete();
            DB::commit();

            return response()->json([
                'message' => "Abonné supprimé avec succès",
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
