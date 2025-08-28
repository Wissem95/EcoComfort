<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gamification', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->enum('action', [
                'close_door',
                'close_window',
                'acknowledge_alert',
                'daily_login',
                'weekly_streak',
                'monthly_champion',
                'energy_saved',
                'quick_response',
                'team_goal',
                'quick_door_close',
                'temperature_reduction',
                'daily_challenge',
                'milestone',
                'badge_earned',
                'achievement_unlocked',
                'energy_saving_action',
                'level_up'
            ]);
            $table->integer('points');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            $table->index('user_id');
            $table->index('action');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gamification');
    }
};