<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Users",
 *     description="Gestion des utilisateurs"
 * )
 */
class UserController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/users.getData",
     *      operationId="index",
     *     tags={"Users"},
     *     summary="Lister tous les utilisateurs",
     *     description="Retourne tous les utilisateurs avec roles et permissions",
     *     @OA\Response(
     *         response=200,
     *         description="Liste des utilisateurs",
     *         @OA\JsonContent(
     *              type="array",
     *              @OA\Items(ref="#/components/schemas/User")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $users = User::query()
            ->search($request->get('search', ''))
            ->paginate(10);

        return UserResource::collection($users);
    }

    /**
     * @OA\Get(
     *      path="/api/user.Options",
     *      operationId="getAllUsersOptions",
     *      tags={"Users"},
     *      summary="Get list of Users",
     *      description="Returns list of Users",
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     * *          @OA\MediaType(
     *           mediaType="application/json",
     *      )
     *       ),
     *     )
     */
    public function getAllUsersOptions()
    {
        $q = request("q", "");
        $data = User::latest()->searh(trim($q))->get();
        $result = [
            'message' => "OK",
            'success' => true,
            'status' => 200,
            'data' => $data
        ];
        return response()->json($result);
    }

    /**
     * @OA\Post(
     * path="/api/user.store",
     * summary="Create User",
     * description="Creation of a new user.",
     * security={{ "bearerAuth":{} }},
     * operationId="store",
     * tags={"Users"},
     * @OA\RequestBody(
     * required=true,
     * description="User data to be stored",
     * @OA\JsonContent(
     * required={"name","phone", "password", "role_id"},
     * @OA\Property(property="name", type="string", example="Winner Kambale"),
     * @OA\Property(property="email", type="string", format="email", example="winner@gmail.com"),
     * @OA\Property(property="phone", type="string", example="+243997604471"),
     * @OA\Property(property="password", type="string", format="password", example="Winner00"),
     * @OA\Property(property="point_eau_id", type="integer", example=1), 
     * @OA\Property(property="role_id", type="integer", example=1),
     * ),
     * ),
     * @OA\Response(
     * response=201,
     * description="User created successfully",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="ajouté avec success"),
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="status", type="integer", example=201)
     * )
     * ),
     * @OA\Response(
     * response=422,
     * description="Validation error",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="Les données envoyées ne sont pas valides."),
     * @OA\Property(property="errors", type="object")
     * )
     * ),
     * @OA\Response(
     * response=409,
     * description="User already exists",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="exist")
     * )
     * ),
     * @OA\Response(
     * response=401,
     * description="Unauthorized"
     * )
     * )
     */
    public function store(Request $request)
    {
        $rules = [
            'name'       => ['required', 'string', 'max:255', 'unique:users,name'],
            'email'      => ['nullable', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone'      => ['required', 'string', 'max:20', 'unique:users,phone'],
            'password'   => ['required', 'string'],
            'role_id'    => ['required', 'integer', 'exists:roles,id'],
        ];

        $messages = [
            'phone.unique' => 'Le numéro de téléphone existe déjà.',
            'name.unique' => 'Le nom d\'utilisateur existe déjà.',
            'email.unique' => 'L\'adresse e-mail existe déjà.',
            'role_id.exists' => 'Le rôle spécifié n\'existe pas.',
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

            $user = User::create([
                'name'       => $request->input('name'),
                'email'      => $request->input('email'),
                'phone'      => $request->input('phone'),
                'point_eau_id' => $request->borne_id,
                'password'   => bcrypt($request->input('password'))
            ]);

            $user->assignRole($request->input('role_id'));

            DB::commit();

            return response()->json([
                'message' => "Agent ajouté avec success",
                'success' => true,
                'status'  => 201
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Une erreur de la création de l\'utilisateur.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     * path="/api/user.update/{id}",
     * summary="Update a User",
     * description="Update an existing user's information.",
     * security={{ "bearerAuth":{} }},
     * operationId="update",
     * tags={"Users"},
     * @OA\Parameter(
     * name="id",
     * in="path",
     * required=true,
     * description="ID of the user to update",
     * @OA\Schema(type="integer")
     * ),
     * @OA\RequestBody(
     * required=true,
     * description="User data to update",
     * @OA\JsonContent(
     * @OA\Property(property="name", type="string", example="Winner Kambale Updated"),
     * @OA\Property(property="email", type="string", format="email", example="winner.updated@gmail.com"),
     * @OA\Property(property="phone", type="string", example="+243999999999"),
     * @OA\Property(property="role_id", type="integer", example=2),
     * @OA\Property(property="point_eau_id", type="integer", example=2),
     * @OA\Property(property="service_id", type="integer", example=2)
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="User updated successfully",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="Agent mis à jour avec succès")
     * )
     * ),
     * @OA\Response(
     * response=404,
     * description="User not found",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="Utilisateur non trouvé")
     * )
     * ),
     * @OA\Response(
     * response=422,
     * description="Validation error",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="Les données envoyées ne sont pas valides.")
     * )
     * ),
     * @OA\Response(
     * response=401,
     * description="Unauthorized"
     * )
     * )
     */
    public function update(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'message' => 'Utilisateur non trouvé'
            ], 404);
        }

        $rules = [
            'name'       => ['sometimes', 'string', 'max:255', 'unique:users,name,' . $user->id],
            'email'      => ['sometimes', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'phone'      => ['sometimes', 'string', 'max:20', 'unique:users,phone,' . $user->id],
            'password'   => ['nullable', 'string'],
            'role_id'    => ['sometimes', 'integer', 'exists:roles,id'],
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

            $user->fill($request->only([
                'name',
                'email',
                'phone',
                'point_eau_id'
            ]));

            if ($request->has('password')) {
                $user->password = bcrypt($request->input('password'));
            }

            $user->save();

            if ($request->has('role_id')) {
                $user->syncRoles($request->input('role_id'));
            }

            DB::commit();

            return response()->json([
                'message' => 'Agent mis à jour avec succès',
                'success' => true,
                'status' => 200
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Une erreur de la mise à jour de l\'utilisateur.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     * path="/api/user.delete/{id}",
     * summary="Delete a User",
     * description="Delete a user by their ID.",
     * security={{ "bearerAuth":{} }},
     * operationId="destroy",
     * tags={"Users"},
     * @OA\Parameter(
     * name="id",
     * in="path",
     * required=true,
     * description="ID of the user to delete",
     * @OA\Schema(type="integer")
     * ),
     * @OA\Response(
     * response=200,
     * description="User deleted successfully",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="Utilisateur supprimé avec succès")
     * )
     * ),
     * @OA\Response(
     * response=404,
     * description="User not found",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="Utilisateur non trouvé")
     * )
     * ),
     * @OA\Response(
     * response=401,
     * description="Unauthorized"
     * )
     * )
     */
    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'Utilisateur non trouvé'
            ], 404);
        }

        try {
            DB::beginTransaction();
            $user->delete();
            DB::commit();

            return response()->json([
                'message' => 'Utilisateur supprimé avec succès',
                'status' => 200,
                'success' => true
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Une erreur est survenue lors de la suppression de l\'utilisateur.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/user.activate/{id}",
     *     summary="Activer un utilisateur",
     *     description="Activer un utilisateur en utilisant l'ID spécifié",
     *     security={{"bearerAuth":{}}},
     *     operationId="activateUser",
     *     tags={"Users"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de l'utilisateur à activer",
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Utilisateur activer avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Utilisateur activer avec succès")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Utilisateur non trouvé",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Utilisateur non trouvé")
     *         )
     *     )
     * )
     */
    public function activateUser($id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'Utilisateur non trouvé'], 404);
        }
        $user->active = true;
        $user->save();
        $result = [
            'message' => "Utilisateur activé avec succès",
            'success' => true,
            'status' => 201
        ];
        return response()->json($result);
    }

    /**
     * @OA\Put(
     *     path="/api/user.disable/{id}",
     *     summary="Suspendre un utilisateur",
     *     description="Suspendre un utilisateur en utilisant l'ID spécifié",
     *     security={{"bearerAuth":{}}},
     *     operationId="disableUser",
     *     tags={"Users"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de l'utilisateur à suspendre",
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Utilisateur suspendu avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Utilisateur suspendu avec succès")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Utilisateur non trouvé",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Utilisateur non trouvé")
     *         )
     *     )
     * )
     */
    public function disableUser($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'Utilisateur non trouvé'], 404);
        }

        $user->active = false;
        $user->save();

        $result = [
            'message' => "Utilisateur désactivé avec succès",
            'success' => true,
            'status' => 201
        ];
        return response()->json($result);
    }
}
