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
        try {
            // ✅ Validation
            $request->validate([
                'name' => 'required|string|unique:roles,name',
                'permissions' => 'array',
            ]);

            $role = null;

            // ✅ Début de la transaction
            DB::transaction(function () use ($request, &$role) {
                // ✅ Créer le rôle
                $role = Role::create([
                    'name' => $request->name,
                    'guard_name' => 'web',
                ]);

                $permissions = $request->permissions ?? [];

                $actionMap = [
                    'Voir'     => 'voir',
                    'Ajouter'  => 'ajouter',
                    'Modifier' => 'modifier',
                    'Supprimer' => 'supprimer',
                ];

                foreach ($permissions as $permission) {
                    $parts = explode('_', $permission);

                    if (count($parts) !== 2) {
                        throw new \Exception("Format de permission invalide: $permission. Format attendu: Action_Permission (ex: Voir_User)");
                    }

                    [$user_permission, $role_permission] = $parts;

                    // 🔎 Vérifier que la permission existe
                    $perm = Permission::where('name', $role_permission)->first();
                    if (!$perm) {
                        throw new \Exception("Permission $role_permission non trouvée");
                    }

                    $column = $actionMap[$user_permission] ?? null;
                    if (!$column) {
                        continue;
                    }

                    DB::table('role_permission_actions')->updateOrInsert(
                        [
                            'role_id'       => $role->id,
                            'permission_id' => $perm->id,
                        ],
                        [
                            $column => true,
                        ]
                    );
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'success',
                'data'    => $role,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // public function storeRole(Request $request)
    // {
    //     try {
    //         // ✅ Validation
    //         $request->validate([
    //             'name' => 'required|string|unique:roles,name',
    //             'permissions' => 'array',
    //         ]);

    //         // ✅ Créer le rôle
    //         $role = Role::create([
    //             'name' => $request->name,
    //             'guard_name' => 'web',
    //         ]);

    //         $permissions = $request->permissions ?? [];

    //         $actionMap = [
    //             'Voir' => 'voir',
    //             'Ajouter' => 'ajouter',
    //             'Modifier' => 'modifier',
    //             'Supprimer' => 'supprimer',
    //         ];

    //         foreach ($permissions as $permission) {
    //             $parts = explode('_', $permission);

    //             if (count($parts) !== 2) {
    //                 // Si le format est incorrect, on ignore ou on retourne une erreur
    //                 return response()->json([
    //                     'success' => false,
    //                     'message' => "Format de permission invalide: $permission. Format attendu: Action_Permission (ex: Voir_User)",
    //                 ], 400);
    //             }

    //             [$user_permission, $role_permission] = $parts;

    //             // 🔎 Vérifier que la permission existe
    //             $perm = Permission::where('name', $role_permission)->first();
    //             if (!$perm) {
    //                 return response()->json([
    //                     'success' => false,
    //                     'message' => "Permission $role_permission non trouvée"
    //                 ], 400);
    //             }

    //             $actionMap = [
    //                 "Voir"     => "voir",
    //                 "Ajouter"  => "ajouter",
    //                 "Modifier" => "modifier",
    //                 "Supprimer" => "supprimer"
    //             ];

    //             $column = $actionMap[$user_permission] ?? null;
    //             if (!$column) {
    //                 continue;
    //             }

    //             DB::table('role_permission_actions')->updateOrInsert(
    //                 [
    //                     'role_id'       => $role->id,
    //                     'permission_id' => $perm->id,
    //                 ],
    //                 [
    //                     $column => true,
    //                 ]
    //             );
    //         }

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'success',
    //             'data'    => $role,
    //         ], 201);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => $e->getMessage(),
    //         ], 500);
    //     }
    // }

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
        try {
            // 🔎 Vérifier que le rôle existe
            $role = Role::findOrFail($id);

            // ✅ Validation
            $request->validate([
                'name' => 'required|string|unique:roles,name,' . $role->id,
                'permissions' => 'array',
            ]);

            // ✅ Mettre à jour le rôle
            $role->update([
                'name' => $request->name,
                'guard_name' => 'web',
            ]);

            $permissions = $request->permissions ?? [];

            // ❌ Réinitialiser toutes les permissions (remettre à false)
            DB::table('role_permission_actions')
                ->where('role_id', $role->id)
                ->update([
                    'voir' => false,
                    'ajouter' => false,
                    'modifier' => false,
                    'supprimer' => false,
                ]);

            $actionMap = [
                'Voir' => 'voir',
                'Ajouter' => 'ajouter',
                'Modifier' => 'modifier',
                'Supprimer' => 'supprimer',
            ];

            foreach ($permissions as $permission) {
                $parts = explode('_', $permission);

                if (count($parts) !== 2) {
                    return response()->json([
                        'success' => false,
                        'message' => "Format de permission invalide: $permission. Format attendu: Action_Permission (ex: Voir_User)",
                    ], 400);
                }

                [$user_permission, $role_permission] = $parts;

                // 🔎 Vérifier que la permission existe
                $perm = Permission::where('name', $role_permission)->first();
                if (!$perm) {
                    return response()->json([
                        'success' => false,
                        'message' => "Permission $role_permission non trouvée"
                    ], 400);
                }

                $column = $actionMap[$user_permission] ?? null;
                if (!$column) {
                    continue;
                }

                // ✅ Insérer ou mettre à jour
                DB::table('role_permission_actions')->updateOrInsert(
                    [
                        'role_id'       => $role->id,
                        'permission_id' => $perm->id,
                    ],
                    [
                        $column => true,
                    ]
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Rôle mis à jour avec succès',
                'data'    => $role,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
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

    // public function getPermissionDataByRole($id)
    // {
    //     $permissions = DB::table('role_has_permissions')
    //         ->join('permissions', 'role_has_permissions.permission_id', '=', 'permissions.id')
    //         ->where('role_has_permissions.role_id', $id)
    //         ->pluck('permissions.name')
    //         ->toArray();
    //     $result = [
    //         'message' => "succes",
    //         'success' => true,
    //         'status' => 200,
    //         'data' => $permissions
    //     ];
    //     return response()->json($result);
    // }

    public function getPermissionDataByRole($id)
    {
        $rows = DB::table('role_permission_actions')
            ->join('permissions', 'permissions.id', '=', 'role_permission_actions.permission_id')
            ->where('role_permission_actions.role_id', $id)
            ->select('permissions.name as permission_name', 'role_permission_actions.*')
            ->get();

        $actionMap = [
            'voir'     => "Voir",
            'ajouter'  => "Ajouter",
            'modifier' => "Modifier",
            'supprimer' => "Supprimer",
        ];

        $result = [];

        foreach ($rows as $row) {
            foreach ($actionMap as $col => $prefix) {
                if ($row->$col) {
                    $result[] = $prefix . "_" . $row->permission_name;
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'success',
            'data'    => $result
        ]);
    }
}
