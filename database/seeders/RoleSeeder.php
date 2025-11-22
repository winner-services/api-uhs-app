<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rolesPermissions = [
            'admin' => [
                'abonnement',
                'categorie-abonne',
                'abonne',
                'raccordement',
                'borne-fontaine',
                'bornier-regulier',
                'facturation',
                'finance',
                'tresorerie',
                'transaction-tresorerie',
                'paiement-mantenance',
                'logistique',
                'mantenance',
                'rapport-mantenance',
                'paramettre',
                'agent',
                'role-agent',
                'technicien-dashboard',
                'technicien-ticket'
            ],
            'technicien' => [
                'technicien-dashboard',
                'technicien-ticket',
            ],
        ];
        // --- 2️⃣ Définir les actions disponibles ---
        $actions = ['voir', 'ajouter', 'modifier', 'supprimer'];

        // --- 3️⃣ Boucler sur chaque rôle ---
        foreach ($rolesPermissions as $roleName => $permissions) {
            // Crée ou récupère le rôle
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            // Récupère les permissions existantes correspondantes
            $permissionsModels = Permission::whereIn('name', $permissions)->get();

            // Synchronise avec Spatie (table role_has_permissions)
            $role->syncPermissions($permissionsModels);

            // --- 4️⃣ Gère ta table personnalisée ---
            foreach ($permissionsModels as $perm) {
                // Crée ou met à jour la ligne dans role_permission_actions
                DB::table('role_permission_actions')->updateOrInsert(
                    [
                        'role_id'       => $role->id,
                        'permission_id' => $perm->id,
                    ],
                    [
                        // 🔥 Pour un admin, toutes les actions sont autorisées
                        // 🔥 Pour un technicien, seulement "voir" et "modifier"
                        'voir'      => true,
                        'ajouter'   => $roleName === 'admin',
                        'modifier'  => in_array($roleName, ['admin', 'technicien']),
                        // 'modifier' => $roleName === 'admin',
                        'supprimer' => $roleName === 'admin',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }
}
