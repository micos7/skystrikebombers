<?php

namespace Database\Factories;

use App\Models\GuestPlayer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GuestPlayer>
 */
class GuestPlayerFactory extends Factory
{
    protected $model = GuestPlayer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'token_hash' => hash('sha256', Str::random(40)),
            'callsign' => fake()->randomElement(['Viper', 'Falcon', 'Raptor', 'Ghost', 'Maverick', 'Talon']).'-'.fake()->numberBetween(10, 99),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'wins' => 0,
            'losses' => 0,
            'last_seen_at' => now(),
        ];
    }
}
