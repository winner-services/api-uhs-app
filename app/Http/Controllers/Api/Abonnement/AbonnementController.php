<?php

namespace App\Http\Controllers\Api\Abonnement;

use App\Http\Controllers\Controller;
use App\Models\Abonne;
use App\Models\About;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AbonnementController extends Controller
{
    /**
     * @OA\Get(
     * path="/api/rapport.abonne",
     * summary="Liste des abonnés par categorie",
     * tags={"Abonnés"},
     * @OA\Response(response=200, description="Liste récupérée avec succès"),
     * )
     */

    public function getByCategorie()
    {
        $about = About::first();

        if ($about && $about->logo) {
            $path = storage_path('app/public/' . $about->logo);

            if (file_exists($path)) {
                $mime = mime_content_type($path);
                $data = base64_encode(file_get_contents($path));
                $about->logo = "data:$mime;base64,$data";
            } else {
                // Si fichier manquant, on peut utiliser une image par défaut
                $about->logo = asset('images/default-logo.png');
            }
        }
        $categorie_id = request('categorie_id');
        // On récupère tous les abonnés qui appartiennent à la catégorie donnée
        $abonnes = Abonne::join('abonnement_categories', 'abonnes.categorie_id', '=', 'abonnement_categories.id')
            ->join('users', 'abonnes.addedBy', '=', 'users.id')
            ->select('abonnes.*', 'abonnement_categories.designation as category', 'users.name as addedBy')
            ->where('abonnes.categorie_id', $categorie_id)->get();
        // Si aucun abonné trouvé
        if ($abonnes->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => "Aucun abonné trouvé pour cette catégorie.",
                'status'  => 404,
                'data'    => []
            ]);
        }

        // Retour succès
        return response()->json([
            'success' => true,
            'message' => "Liste des abonnés de la catégorie sélectionnée.",
            'status'  => 200,
            'data'    => $abonnes,
            'company_info' => $about
        ]);
    }



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
        $q = request("q", "");
        return response()->json([
            'success' => true,
            'data' => Abonne::with(['categorie', 'user'])
                ->searh(trim($q))
                ->get(),
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
     *       @OA\Property(property="genre", type="string", example="Masculin"),
     *       @OA\Property(property="statut", type="string", example="propriétaire"),
     *       @OA\Property(property="num_piece", type="string", example="33305869789"),
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
            'nom' => ['required', 'string', 'max:255'],
            'categorie_id' => ['required', 'integer', 'exists:abonnement_categories,id'],
            'telephone' => ['nullable', 'string', 'max:20'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'genre' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:255'],
            'num_piece' => ['nullable', 'string', 'max:255'],
            'piece_identite' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Les données envoyées ne sont pas valides.',
                'errors'  => $validator->errors()
            ], 422);
        }

        $piecePath = null;

        // Si fichier présent et valide, on le stocke
        if ($request->hasFile('piece_identite')) {
            $piece = $request->file('piece_identite');

            if ($piece->isValid()) {
                $pieceName = time() . '_' . uniqid() . '.' . $piece->getClientOriginalExtension();
                $piecePath = $piece->storeAs('pieces_identite', $pieceName, 'public');
            } else {
                return response()->json([
                    'message' => 'Le fichier uploadé est invalide.'
                ], 400);
            }
        }

        try {
            DB::beginTransaction();

            $user = Auth::user();

            $abonne = Abonne::create([
                'nom' => $request->nom,
                'categorie_id' => $request->categorie_id,
                'telephone' => $request->telephone,
                'adresse' => $request->adresse,
                'genre' => $request->genre,
                'statut' => $request->status,
                'num_piece' => $request->num_piece,
                'piece_identite' => $piecePath,
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

            // Si on avait stocké un fichier, le supprimer pour éviter les fichiers orphelins
            if ($piecePath && Storage::disk('public')->exists($piecePath)) {
                try {
                    Storage::disk('public')->delete($piecePath);
                } catch (\Exception $ex) {
                    // log si tu veux : Log::error(...)
                }
            }

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
     *       @OA\Property(property="adresse", type="string", example="Kinshasa, RDC"),
     *       @OA\Property(property="genre", type="string", example="Masculin"),
     *       @OA\Property(property="statut", type="string", example="propriétaire"),
     *       @OA\Property(property="num_piece", type="string", example="33305869789"),
     *    )
     * ),
     * @OA\Response(response=200, description="Abonné mis à jour avec succès"),
     * @OA\Response(response=404, description="Abonné non trouvé")
     * )
     */
    public function update(Request $request, $id)
    {
        $abonne = Abonne::findOrFail($id);

        $rules = [
            'nom' => ['required', 'string', 'max:255'],
            'categorie_id' => ['required', 'integer', 'exists:abonnement_categories,id'],
            'telephone' => ['nullable', 'string', 'max:20'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'genre' => ['nullable', 'string', 'max:255'],
            'statut' => ['nullable', 'string', 'max:255'],
            'num_piece' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('abonnes', 'num_piece')->ignore($abonne->id),
            ],
            'piece_identite' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Les données envoyées ne sont pas valides.',
                'errors' => $validator->errors()
            ], 422);
        }

        $newPiecePath = null;
        $oldPiecePath = $abonne->piece_identite;

        // 🔹 Si un nouveau fichier est uploadé, on le stocke avant la transaction
        if ($request->hasFile('piece_identite')) {
            $file = $request->file('piece_identite');

            if (!$file->isValid()) {
                return response()->json([
                    'message' => 'Le fichier uploadé est invalide.'
                ], 400);
            }

            $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $newPiecePath = $file->storeAs('pieces_identite', $fileName, 'public');
        }

        try {
            DB::beginTransaction();

            $user = Auth::user();

            // 🔸 Mise à jour des informations
            $abonne->nom = $request->nom;
            $abonne->categorie_id = $request->categorie_id;
            $abonne->telephone = $request->telephone;
            $abonne->adresse = $request->adresse;
            $abonne->genre = $request->genre;
            $abonne->statut = $request->status;
            $abonne->num_piece = $request->num_piece;
            $abonne->addedBy = $user->id;

            if ($newPiecePath) {
                $abonne->piece_identite = $newPiecePath;
            }

            $abonne->save();

            DB::commit();

            // 🔹 Après commit : si nouveau fichier, supprimer l’ancien
            if ($newPiecePath && $oldPiecePath && Storage::disk('public')->exists($oldPiecePath)) {
                try {
                    Storage::disk('public')->delete($oldPiecePath);
                } catch (\Exception $e) {
                    // Optionnel : journaliser l'erreur
                }
            }

            return response()->json([
                'message' => 'Abonné mis à jour avec succès.',
                'success' => true,
                'status' => 200,
                'data' => $abonne
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            // 🔸 Si la transaction échoue, supprimer le nouveau fichier uploadé
            if ($newPiecePath && Storage::disk('public')->exists($newPiecePath)) {
                Storage::disk('public')->delete($newPiecePath);
            }

            return response()->json([
                'message' => 'Erreur lors de la mise à jour de l\'abonné.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    // public function update(Request $request, $id)
    // {
    //     $abonne = Abonne::find($id);
    //     if (!$abonne) {
    //         return response()->json([
    //             'message' => 'Abonné non trouvé'
    //         ], 404);
    //     }

    //     $rules = [
    //         'nom'          => ['nullable', 'string', 'max:255'],
    //         'categorie_id' => ['nullable', 'integer', 'exists:abonnement_categories,id'],
    //         'telephone'    => ['nullable', 'string', 'max:20'],
    //         'adresse'      => ['nullable', 'string', 'max:255'],
    //         'genre' => ['nullable'],
    //         'statut' => ['nullable'],
    //         'num_piece' => ['nullable']
    //     ];

    //     $validator = Validator::make($request->all(), $rules);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'message' => 'Les données envoyées ne sont pas valides.',
    //             'errors'  => $validator->errors()
    //         ], 422);
    //     }

    //     try {
    //         DB::beginTransaction();
    //         $user = Auth::user();
    //         $abonne->update([
    //             'nom' => $request->nom,
    //             'categorie_id' => $request->categorie_id,
    //             'telephone' => $request->telephone,
    //             'adresse' => $request->adresse,
    //             'genre' => $request->genre,
    //             'statut' => $request->statut,
    //             'num_piece' => $request->num_piece,
    //             'addedBy' => $user->id
    //         ]);

    //         DB::commit();

    //         return response()->json([
    //             'message' => "Abonné mis à jour avec succès",
    //             'success' => true,
    //             'status'  => 200,
    //             'data'    => $abonne
    //         ], 200);
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         return response()->json([
    //             'message' => 'Erreur lors de la mise à jour.',
    //             'error'   => $e->getMessage()
    //         ], 500);
    //     }
    // }

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
