<?php

namespace App\Http\Controllers\Api\Bornier;

use App\Http\Controllers\Controller;
use App\Models\About;
use App\Models\Bornier;
use App\Models\PointEauAbonne;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class BornierController extends Controller
{
    /**
     * @OA\Get(
     * path="/api/Borniers.Options",
     * summary="Liste des borniers",
     * tags={"Borniers"},
     * @OA\Response(response=200, description="Liste récupérée avec succès"),
     * )
     */
    public function indexOptions()
    {
        $q = request("q", "");
        $data = Bornier::latest()->searh(trim($q))->get();
        return response()->json([
            'message' => "OK",
            'success' => true,
            'data' => $data,
            'status' => 200
        ]);
    }

    /**
     * @OA\Get(
     * path="/api/Borniers.getAllData",
     * summary="Liste des borniers",
     * tags={"Borniers"},
     * @OA\Response(response=200, description="Liste récupérée avec succès"),
     * )
     */
    public function indexBornier()
    {

        $page = request("paginate", 100);
        $q = request("q", "");
        $sort_direction = request('sort_direction', 'desc');
        $sort_field = request('sort_field', 'id');
        $data = Bornier::join('point_eaus', 'borniers.borne_id', '=', 'point_eaus.id')
            ->join('users', 'borniers.addedBy', '=', 'users.id')
            ->select('borniers.*', 'point_eaus.matricule', 'point_eaus.numero_compteur', 'point_eaus.lat', 'point_eaus.long', 'users.name as addedBy')
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
     * @OA\Post(
     *     path="/api/borniers.store",
     *     tags={"Borniers"},
     *     summary="Créer un nouveau bornier",
     *     description="Crée un enregistrement de bornier avec les informations fournies.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"nom"},
     *             @OA\Property(property="nom", type="string", example="Bornier central"),
     *             @OA\Property(property="phone", type="string", example="+243971234567"),
     *             @OA\Property(property="adresse", type="string", example="Avenue Lumumba, Goma"),
     *             @OA\Property(property="borne_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Bornier créé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Bornier créé avec succès."),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation"
     *     )
     * )
     */
    public function storeBornier(Request $request)
    {
        try {
            $user = Auth::user();
            $validator = Validator::make($request->all(), [
                'nom'       => 'required|string|max:255|unique:borniers,nom',
                'phone'     => 'nullable|string|max:20',
                'adresse'   => 'nullable|string|max:255',
                'borne_id'  => 'required|exists:point_eaus,id',
            ], [
                'nom.required' => 'Le nom du bornier est obligatoire.',
                'nom.unique'   => 'Ce nom de bornier existe déjà.',
                'borne_id.exists' => 'Le point d’eau sélectionné est invalide.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Les données envoyées ne sont pas valides.',
                    'errors'  => $validator->errors(),
                    'status' => 422
                ], 422);
            }

            // Vérifier si ce point d’eau est déjà attribué à un autre abonné
            $alreadyLinked = PointEauAbonne::where('point_eau_id', $request->borne_id)->first();

            if ($alreadyLinked) {
                return response()->json([
                    'message' => 'Ce point d’eau est déjà attribué à un autre abonné.',
                    'success' => false,
                    'status'  => 409
                ], 409);
            }

            $exist = Bornier::where('borne_id', $request->borne_id)->first();
            if ($exist) {
                return response()->json([
                    'message' => 'Ce borne est déjà attribué à un autre.',
                    'success' => false,
                    'status'  => 409
                ], 409);
            }

            // Création du bornier
            $bornier = Bornier::create([
                'nom' => $request->nom,
                'phone' => $request->phone,
                'adresse' => $request->adresse,
                'borne_id' => $request->borne_id,
                'addedBy' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bornier créé avec succès.',
                'status' => 201,
                'data'    => $bornier
            ], 201);
        } catch (\Exception $e) {
            // Gestion des erreurs inattendues
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la création du bornier.',
                'error'   => $e->getMessage(), // tu peux masquer ce message en prod
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/borniers.update/{id}",
     *     tags={"Borniers"},
     *     summary="Mettre à jour un bornier existant",
     *     description="Met à jour les informations d’un bornier existant à partir de son ID.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID du bornier",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="nom", type="string", example="Bornier nord"),
     *             @OA\Property(property="phone", type="string", example="+243820000000"),
     *             @OA\Property(property="adresse", type="string", example="Quartier Katindo, Goma"),
     *             @OA\Property(property="borne_id", type="integer", example=2)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Bornier mis à jour avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Bornier mis à jour avec succès."),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Bornier introuvable"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation"
     *     )
     * )
     */

    public function updateBornier(Request $request, $id)
    {
        $bornier = Bornier::find($id);
        $user = Auth::user();

        if (!$bornier) {
            return response()->json([
                'success' => false,
                'message' => 'Bornier introuvable.',
                'status' => 404
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nom'       => 'sometimes|required|string|max:255',
            'phone'     => 'nullable|string|max:20',
            'adresse'   => 'nullable|string|max:255',
            'borne_id'  => 'nullable|exists:point_eaus,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation.',
                'errors'  => $validator->errors(),
                'status' => 422
            ], 422);
        }

        // Vérification si le borne_id existe déjà dans la table PointEauAbonne
        if ($request->has('borne_id') && PointEauAbonne::where('point_eau_id', $request->borne_id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Borne ID déjà attribuée à un abonné.',
                'status' => 400
            ], 400);
        }

        // Vérification si le borne_id existe déjà dans la table Bornier, excepté pour l'ID en cours
        if ($request->has('borne_id') && Bornier::where('borne_id', $request->borne_id)->where('id', '!=', $id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Ce Borne ID existe déjà pour un autre Bornier.',
                'status' => 400
            ], 400);
        }

        // Mise à jour des données
        $bornier->update([
            'nom' => $request->nom,
            'phone' => $request->phone,
            'adresse' => $request->adresse,
            'borne_id' => $request->borne_id ?? $bornier->borne_id,
            'addedBy' => $user->id
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Bornier mis à jour avec succès.',
            'status' => 200,
            'data'    => $bornier
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/borniers.delete/{id}",
     *     tags={"Borniers"},
     *     summary="Supprimer un bornier",
     *     description="Supprime un bornier existant par son ID.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID du bornier",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Bornier supprimé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Bornier supprimé avec succès.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Bornier introuvable"
     *     )
     * )
     */
    public function destroyBornier($id)
    {
        $bornier = Bornier::find($id);

        if (!$bornier) {
            return response()->json([
                'success' => false,
                'message' => 'Bornier introuvable.'
            ], 404);
        }

        $bornier->delete();

        return response()->json([
            'success' => true,
            'message' => 'Bornier supprimé avec succès.'
        ]);
    }

    /**
     * @OA\Get(
     * path="/api/rapportBornier.gettAllData",
     * summary="Liste des borniers",
     * tags={"Borniers"},
     * @OA\Response(response=200, description="Liste récupérée avec succès"),
     * )
     */
    public function rapportBornier()
    {
        $about = About::first();

        // 🔧 Vérifier si le logo existe et générer base64
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

        $data = Bornier::join('point_eaus', 'borniers.borne_id', '=', 'point_eaus.id')
            ->join('users', 'borniers.addedBy', '=', 'users.id')
            ->select('borniers.*', 'point_eaus.matricule', 'point_eaus.numero_compteur', 'point_eaus.lat', 'point_eaus.long', 'users.name as addedBy')
            ->latest()->get();
        $result = [
            'message' => "OK",
            'success' => true,
            'data' => $data,
            'company_info' => $about,
            'status' => 200
        ];
        return response()->json($result);
    }
}
