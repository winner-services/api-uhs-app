<?php

namespace App\Http\Controllers\Api\About;

use App\Http\Controllers\Controller;
use App\Models\About;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AboutController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/about.index",
     *     tags={"About"},
     *     summary="Récupérer les informations",
     *     description="Retourne les informations de l’entreprise (about)",
     *     @OA\Response(
     *         response=200,
     *         description="Données de l’entreprise",
     *         @OA\JsonContent(ref="#/components/schemas/About")
     *     )
     * )
     */
    public function getData()
    {
        $about = About::first();
            return response()->json([
            'message' => 'succes',
            'status' => 200,
            'data' => $about
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/about.store",
     *     tags={"About"},
     *     summary="Créer les informations",
     *     description="Créer les informations de l’entreprise (about)",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"denomination"},
     *                 @OA\Property(property="denomination", type="string", example="UHS Asbl"),
     *                 @OA\Property(property="details", type="string", example="ONG œuvrant dans l’humanitaire"),
     *                 @OA\Property(property="register", type="string", example="RCCM12345"),
     *                 @OA\Property(property="national_id", type="string", example="IDN12345"),
     *                 @OA\Property(property="tax_number", type="string", example="NIF12345"),
     *                 @OA\Property(property="phone", type="string", example="+243900000000"),
     *                 @OA\Property(property="address", type="string", example="Goma, RDC"),
     *                 @OA\Property(property="logo", type="file")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Créé avec succès",
     *         @OA\JsonContent(ref="#/components/schemas/About")
     *     )
     * )
     */
    public function store(Request $request)
    {
        $data = $request->all();

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('logo', 'public');
            $data['logo'] = $path;
        }

        $about = About::create($data);

        return response()->json($about, 201);
         return response()->json([
            'message' => 'succes',
            'status' => 201,
            'success' => true
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/about.update/{id}",
     *     tags={"About"},
     *     summary="Mettre à jour les informations",
     *     description="Modifier les informations de l’entreprise (about). 
     *                  Si aucun logo n’est envoyé, l’ancien reste.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID du about",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="denomination", type="string", example="UHS Asbl"),
     *                 @OA\Property(property="details", type="string", example="ONG mise à jour"),
     *                 @OA\Property(property="register", type="string"),
     *                 @OA\Property(property="national_id", type="string"),
     *                 @OA\Property(property="tax_number", type="string"),
     *                 @OA\Property(property="phone", type="string"),
     *                 @OA\Property(property="address", type="string"),
     *                 @OA\Property(property="logo", type="file")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Mise à jour réussie",
     *         @OA\JsonContent(ref="#/components/schemas/About")
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $about = About::findOrFail($id);
        $data = $request->all();

        if ($request->hasFile('logo')) {
            // supprimer l’ancien logo si besoin
            if ($about->logo && Storage::disk('public')->exists($about->logo)) {
                Storage::disk('public')->delete($about->logo);
            }
            $path = $request->file('logo')->store('logo', 'public');
            $data['logo'] = $path;
        } else {
            // garder l'ancien logo
            $data['logo'] = $about->logo;
        }

        $about->update($data);

        return response()->json([
            'message' => 'succes',
            'status' => 201,
            'success' => true
        ]);
    }
}
