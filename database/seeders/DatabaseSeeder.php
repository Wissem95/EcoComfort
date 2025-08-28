<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * PRODUCTION DATABASE SEEDER
     * Aucune donnée factice - Admin uniquement
     */
    public function run(): void
    {
        $this->command->info("🚀 PRODUCTION DATABASE SEEDING");
        $this->command->line("====================================");
        
        // En production : UNIQUEMENT les données essentielles
        $this->call([
            AdminUserSeeder::class,
        ]);
        
        $this->command->line("====================================");
        $this->command->info("✅ PRODUCTION SEEDING COMPLETED");
        $this->command->info("   • Infrastructure à créer via Admin PWA");
        $this->command->info("   • Données RuuviTag en temps réel uniquement");
    }
}
