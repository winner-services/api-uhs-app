<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            'abonnement',
            'categorie_abonnement',
            'abonne',
            'raccordement',
            'borne',
            'facturation',
            'paiement',
            'finance',
            'tresorerie',
            'transaction_tresorerie',
            'ticket',
            'mantenance',
            'paramettre',
            'technicien_dashboard',
            'technicien_ticket'
        ];
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }
    }
}
