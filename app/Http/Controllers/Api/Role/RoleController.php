<?php

namespace App\Http\Controllers\Api\Role;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * @OA\Post(
     * path="/api/role.store",
     * summary="Create Role",
     * description="Creation du role",
     * security={{ "bearerAuth":{ }}},
     * operationId="storeRole",
     * tags={"Role"},
     * @OA\RequestBody(
     * required=true,
     * description="Enregistrer un role",
     * @OA\JsonContent(
     * required={"name","permissions"},
     * @OA\Property(property="name", type="string", format="text",example="super admin"),
     * @OA\Property(property="permissions", type="array", @OA\Items(type="string"), example={"create-users", "view-reports"}),
     * ),
     * ),
     * @OA\Response(
     * response=201,
     * description="Role créé avec succès",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="Rôle ajouté avec succès et permissions assignées."),
     * @OA\Property(property="success", type="boolean", example=true)
     * )
     * ),
     * @OA\Response(
     * response=409,
     * description="Role already exists",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="Le rôle existe déjà.")
     * )
     * ),
     * @OA\Response(
     * response=404,
     * description="Permissions not found",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="Une ou plusieurs permissions n'ont pas été trouvées."),
     * @OA\Property(property="success", type="boolean", example=false)
     * )
     * ),
     * @OA\Response(
     * response=422,
     * description="Validation error",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="Les données envoyées ne sont pas valides."),
     * @OA\Property(property="errors", type="object")
     * )
     * )
     * )
     */
    public function storeRole(Request $request)
    {
        $rules = [
            'name' => [
                'required',
                'string',
                'unique:roles,name'
            ],
            'permissions' => 'required|array',
            'permissions.*' => 'string',
        ];

        $messages = [
            'name.unique' => 'Le rôle existe déjà.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Les données envoyées ne sont pas valides.',
                'errors' => $validator->errors()
            ], 422);
        }

        $permissionsCount = Permission::whereIn('name', $request->permissions)->count();
        if ($permissionsCount !== count($request->permissions)) {
            return response()->json([
                'message' => 'Une ou plusieurs permissions n\'ont pas été trouvées.',
                'success' => false
            ], 404);
        }

        try {
            DB::beginTransaction();

            $role = Role::create([
                'name' => $request->name,
                'guard_name' => 'web'
            ]);

            $permissions = Permission::whereIn('name', $request->permissions)->get();
            $role->syncPermissions($permissions);

            DB::commit();

            return response()->json([
                'message' => "Rôle ajouté avec succès et permissions assignées.",
                'success' => true
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Une erreur est survenue lors de la création du rôle.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     * path="/api/role.update/{id}",
     * summary="Update Role",
     * description="Modification d'un role existant",
     * security={{ "bearerAuth":{ }}},
     * operationId="updateRole",
     * tags={"Role"},
     * @OA\Parameter(
     * name="id",
     * in="path",
     * required=true,
     * description="ID of the role to update",
     * @OA\Schema(type="integer")
     * ),
     * @OA\RequestBody(
     * required=true,
     * description="Données du rôle à modifier",
     * @OA\JsonContent(
     * @OA\Property(property="name", type="string", format="text",example="updated admin"),
     * @OA\Property(property="permissions", type="array", @OA\Items(type="string"), example={"view-users", "delete-users"}),
     * ),
     * ),
     * @OA\Response(
     * response=200,
     * description="Role updated successfully",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="Rôle mis à jour avec succès et permissions synchronisées."),
     * @OA\Property(property="success", type="boolean", example=true)
     * )
     * ),
     * @OA\Response(
     * response=404,
     * description="Role or permissions not found",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="Rôle non trouvé.")
     * )
     * ),
     * @OA\Response(
     * response=422,
     * description="Validation error",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="Les données envoyées ne sont pas valides."),
     * @OA\Property(property="errors", type="object")
     * )
     * )
     * )
     */
    public function updateRole(Request $request, $id)
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json([
                'message' => 'Rôle non trouvé.'
            ], 404);
        }

        $rules = [
            'name' => [
                'sometimes',
                'string',
                'unique:roles,name,' . $role->id
            ],
            'permissions' => 'sometimes|array',
            'permissions.*' => 'string|exists:permissions,name',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Les données envoyées ne sont pas valides.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            if ($request->has('name')) {
                $role->update(['name' => $request->name]);
            }

            if ($request->has('permissions')) {
                $permissions = Permission::whereIn('name', $request->permissions)->get();
                $role->syncPermissions($permissions);
            }

            DB::commit();

            return response()->json([
                'message' => 'Rôle mis à jour avec succès.',
                'success' => true
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Une erreur est survenue lors de la mise à jour du rôle.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *      path="/api/role.Options",
     *      operationId="getRole",
     *      tags={"Role"},
     *      summary="Get list of Services",
     *      description="Returns list of Services",
     *      @OA\Response(
     *          response=200,
     *          description="Successful",
     * *          @OA\MediaType(
     *           mediaType="application/json",
     *      )
     *       ),
     *     )
     */
    public function getRole()
    {
        $roles = Role::get();
        $result = [
            "success" => true,
            'status' => 200,
            "data" => $roles
        ];
        return response()->json($result);
    }

    /**
     * @OA\Get(
     *      path="/api/permissionDataByRole/{id}",
     *      operationId="getPermissionDataByRole",
     *      tags={"Role"},
     *      summary="Get permissions by role ID",
     *      description="Returns the list of permissions for a given role ID",
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          required=true,
     *          description="ID du rôle",
     *          @OA\Schema(type="integer", example=1)
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              type="array",
     *              @OA\Items(type="string", example="Voir_abonnement")
     *          )
     *      )
     * )
     */

    public function getPermissionDataByRole($id)
    {
        $permissions = DB::table('role_has_permissions')
            ->join('permissions', 'role_has_permissions.permission_id', '=', 'permissions.id')
            ->where('role_has_permissions.role_id', $id)
            ->pluck('permissions.name')
            ->toArray();
        $result = [
            'message' => "succes",
            'success' => true,
            'status' => 200,
            'data' => $permissions
        ];
        return response()->json($result);
    }
}
