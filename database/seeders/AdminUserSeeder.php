<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Organization;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * PRODUCTION SEEDER - Admin et Organisation uniquement
     * Aucune donnée de test, aucun capteur fictif
     */
    public function run(): void
    {
        $this->command->info("🚀 PRODUCTION MODE - Création données minimales");
        $this->command->line("----------------------------------------");

        // Créer UNIQUEMENT l'organisation principale
        $organization = Organization::firstOrCreate(
            ['name' => 'EcoComfort HQ'],
            [
                'surface_m2' => 0, // Sera défini via Admin PWA
                'target_percent' => 20,
                'industry' => 'IoT Energy Management',
                'country' => 'France',
                'timezone' => 'Europe/Paris',
            ]
        );

        // Créer UNIQUEMENT l'utilisateur admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@ecocomfort.com'],
            [
                'name' => 'Admin EcoComfort',
                'password' => Hash::make('EcoAdmin2024!'),
                'organization_id' => $organization->id,
                'role' => 'admin',
                'is_active' => true,
                'points' => 0, // Points réels seulement
                'level' => 1, // Niveau réel seulement
                'email_verified_at' => now(),
            ]
        );

        $this->command->info("✅ Organisation créée: " . $organization->name);
        $this->command->info("✅ Admin user créé:");
        $this->command->info("   📧 Email: admin@ecocomfort.com");
        $this->command->info("   🔑 Password: EcoAdmin2024!");
        $this->command->line("----------------------------------------");
        $this->command->info("🎯 PRODUCTION READY");
        $this->command->info("   • Aucune donnée de test");
        $this->command->info("   • Bâtiments/Salles/Capteurs à créer via PWA Admin");
        $this->command->info("   • Données réelles RuuviTag uniquement");
    }
}