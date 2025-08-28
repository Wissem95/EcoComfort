<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Energy Pricing Configuration
    |--------------------------------------------------------------------------
    |
    | Configure energy pricing and cost calculation parameters for the
    | EcoComfort system energy loss calculations.
    |
    */

    'price_per_kwh' => env('ENERGY_PRICE_PER_KWH', 0.15),
    'currency' => env('ENERGY_CURRENCY', 'EUR'),
    'currency_symbol' => env('ENERGY_CURRENCY_SYMBOL', '€'),

    /*
    |--------------------------------------------------------------------------
    | Energy Calculation Constants
    |--------------------------------------------------------------------------
    |
    | Physical constants used in energy loss calculations.
    |
    */

    'constants' => [
        'air_specific_heat' => 1005, // J/(kg·K)
        'air_density' => 1.225, // kg/m³ at sea level
        'door_opening_area' => 2.0, // m² (average door)
        'window_opening_area' => 1.5, // m² (average window)
        'air_exchange_rate_door' => 3.0, // air changes per hour when door is open
        'air_exchange_rate_window' => 2.0, // air changes per hour when window is open
        'co2_per_kwh' => 0.475, // kg CO2 per kWh (average grid emission factor)
        'tree_co2_absorption' => 21.77, // kg CO2 absorbed per tree per year
    ],

    /*
    |--------------------------------------------------------------------------
    | Efficiency Targets
    |--------------------------------------------------------------------------
    |
    | Configure efficiency targets and thresholds for organizations.
    |
    */

    'targets' => [
        'default_savings_percent' => env('ENERGY_DEFAULT_TARGET', 20),
        'excellent_efficiency' => 90,
        'good_efficiency' => 75,
        'fair_efficiency' => 60,
        'poor_efficiency' => 40,
    ],

    /*
    |--------------------------------------------------------------------------
    | Alert Thresholds
    |--------------------------------------------------------------------------
    |
    | Configure energy loss thresholds that trigger different alert severities.
    |
    */

    'alert_thresholds' => [
        'temperature_deviation' => [
            'info' => 3.0, // degrees
            'warning' => 5.0,
            'critical' => 8.0,
        ],
        'humidity_deviation' => [
            'info' => 15.0, // percentage
            'warning' => 20.0,
            'critical' => 30.0,
        ],
        'energy_loss_watts' => [
            'door' => [
                'info' => 50,
                'warning' => 100,
                'critical' => 200,
            ],
            'window' => [
                'info' => 25,
                'warning' => 50,
                'critical' => 100,
            ],
        ],
        'energy_loss_duration' => [
            'info' => 300, // 5 minutes
            'warning' => 900, // 15 minutes
            'critical' => 1800, // 30 minutes
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Calculation Factors
    |--------------------------------------------------------------------------
    |
    | Factors used to adjust energy calculations based on environmental conditions.
    |
    */

    'factors' => [
        'wind_speed_factor' => 0.1, // per m/s above 5 m/s
        'temperature_stack_factor' => 0.02, // per degree above 10°C difference
        'humidity_factor' => 0.002, // per percentage above 60%
        'door_vs_window_factor' => 1.2, // doors typically have higher air exchange
        'max_correction_factor' => 2.0, // maximum total correction factor
    ],

    /*
    |--------------------------------------------------------------------------
    | Estimation Parameters
    |--------------------------------------------------------------------------
    |
    | Parameters for rough energy consumption estimations.
    |
    */

    'estimation' => [
        'hvac_watts_per_m2' => 30, // base HVAC consumption per m²
        'heating_watts_per_degree_m2' => 50, // extra watts per degree deviation per m²
        'cooling_efficiency_loss_per_humidity_percent' => 0.01, // efficiency loss per humidity %
        'max_efficiency_loss' => 0.3, // maximum 30% efficiency loss
    ],

    /*
    |--------------------------------------------------------------------------
    | Reporting Periods
    |--------------------------------------------------------------------------
    |
    | Configure default reporting periods for energy analytics.
    |
    */

    'reporting' => [
        'default_period_hours' => 24,
        'max_period_days' => 90,
        'analytics_cache_minutes' => 10,
        'statistics_cache_minutes' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Gamification Rewards
    |--------------------------------------------------------------------------
    |
    | Configure how energy savings are converted to gamification points.
    |
    */

    'gamification' => [
        'points_per_kwh_saved' => 10,
        'bonus_quick_response_seconds' => 30,
        'quick_response_multiplier' => 2.0,
        'team_goal_bonus_percent' => 0.5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Weather Integration
    |--------------------------------------------------------------------------
    |
    | Configure external weather API integration for more accurate calculations.
    |
    */

    'weather' => [
        'api_enabled' => env('WEATHER_API_ENABLED', false),
        'api_key' => env('WEATHER_API_KEY'),
        'api_url' => env('WEATHER_API_URL', 'https://api.openweathermap.org/data/2.5'),
        'update_interval_minutes' => env('WEATHER_UPDATE_INTERVAL', 60),
        'cache_duration_minutes' => env('WEATHER_CACHE_DURATION', 30),
        'default_outdoor_temperature' => env('DEFAULT_OUTDOOR_TEMP', 10.0),
        'default_wind_speed' => env('DEFAULT_WIND_SPEED', 3.0),
    ],

];