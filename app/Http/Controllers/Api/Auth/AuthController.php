<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * @OA\Post(
     *   path="/api/auth/login",
     *   summary="Login User",
     *   description="Authenticate user with email or phone and return access token",
     *   operationId="login",
     *   tags={"Auth"},
     *   @OA\RequestBody(
     *     required=true,
     *     description="User login credentials",
     *     @OA\JsonContent(
     *       required={"email","password"},
     *       @OA\Property(property="email", type="string", description="Phone number or email", example="admin@admin.com"),
     *       @OA\Property(property="password", type="string", format="password", example="admin")
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Authenticated successfully"
     *   ),
     *   @OA\Response(
     *     response=401,
     *     description="Invalid credentials"
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Account disabled"
     *   )
     * )
     */

   public function login(Request $request)
    {
        // Validation
        $request->validate([
            'email'    => 'required|string',
            'password' => 'required|string|min:4',
        ]);

        // Recherche de l'utilisateur
        $user = User::where('email', $request->email)
                    ->orWhere('phone', $request->email)
                    ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Votre email ou mot de passe est incorrect'
            ], 401);
        }

        if (!$user->active) {
            return response()->json([
                'success' => false,
                'message' => 'Votre compte est désactivé'
            ], 422);
        }

        // Récupérer les permissions depuis role_permission_actions
        $rolePermissions = DB::table('role_permission_actions as rpa')
            ->join('permissions as p', 'rpa.permission_id', '=', 'p.id')
            ->where('rpa.role_id', $user->role_id)
            ->select('p.name as permission_name', 'rpa.voir', 'rpa.ajouter', 'rpa.modifier', 'rpa.supprimer')
            ->get();

        // Transformer en ["Action_Permission"] uniquement si true
        $permissions = [];
        $actionMap = [
            'voir' => 'Voir',
            'ajouter' => 'Ajouter',
            'modifier' => 'Modifier',
            'supprimer' => 'Supprimer'
        ];

        foreach ($rolePermissions as $rp) {
            foreach ($actionMap as $col => $prefix) {
                if (!empty($rp->$col) && $rp->$col) { // Inclut seulement si true
                    $permissions[] = $prefix . '_' . $rp->permission_name;
                }
            }
        }

        // Générer un token Sanctum
        $device_name = $request->header('User-Agent') ?? 'unknown_device';
        $token = $user->createToken($device_name, ['*'])->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'active' => $user->active,
                'role_name' => [$user->role->name ?? null],
                'permissions' => $permissions, // Seulement les permissions valides
            ],
        ]);
    }

    // public function login(Request $request)
    // {
    //     // ✅ Validation
    //     $validator = Validator::make($request->all(), [
    //         'email'    => 'required|string',
    //         'password' => 'required|string|min:4',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'message' => 'Les données envoyées ne sont pas valides.',
    //             'errors'  => $validator->errors()
    //         ], 422);
    //     }

    //     // ✅ Recherche de l'utilisateur (email ou téléphone)
    //     $user = User::where('email', $request->email)
    //         ->orWhere('phone', $request->email)
    //         ->first();

    //     if (!$user || !Hash::check($request->password, $user->password)) {
    //         return response()->json([
    //             'message' => 'Vérifiez vos identifiants svp.',
    //             'status'  => 401
    //         ], 401);
    //     }

    //     // ✅ Vérification si le compte est actif
    //     if (!$user->active) {
    //         return response()->json([
    //             'message' => 'Votre compte est désactivé.',
    //             'status'  => 422
    //         ], 422);
    //     }
    //     // ✅ Détection du device (optionnel : tu peux simplifier en fixant un nom unique)
    //     $device_name = $request->header('User-Agent') ?? 'unknown_device';

    //     // ✅ Génération du token Sanctum
    //     $token = $user->createToken($device_name, ['*'])->plainTextToken;

    //     $roles = $user->getRoleNames();
    //     $permissions = $user->getAllPermissions()
    //         ->pluck('name');

    //     return response()->json([
    //         'message' => 'Connexion réussie.',
    //         'token'   => $token,
    //         'user'    => [
    //             'id'          => $user->id,
    //             'name'        => $user->name,
    //             'email'       => $user->email,
    //             'phone'       => $user->phone,
    //             'active'      => $user->active,
    //             'role_name'   => $roles,
    //             'permissions' => $permissions,
    //         ],
    //         'status' => 200
    //     ]);
    // }

    /**
     * @OA\Post(
     *     path="/api/auth/logout",
     *     summary="Déconnexion",
     *     description="Déconnexion d'un utilisateur en utilisant son token",
     *     security={{"bearerAuth":{}}}, 
     *     operationId="logout",
     *     tags={"Auth"},
     *     @OA\Response(
     *         response=200,
     *         description="Utilisateur déconnecté avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="Déconnexion réussie")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Utilisateur non authentifié"
     *     )
     * )
     */

    public function logout(Request $request): JsonResponse
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        if ($user) {
            // Supprime uniquement le token utilisé pour cette requête
            $user->currentAccessToken()?->delete;

            return response()->json([
                'message' => 'Déconnexion réussie',
                'status' => 200
            ]);
        }

        return response()->json([
            'message' => 'Utilisateur non authentifié',
            'status' => 401
        ]);
    }
}
