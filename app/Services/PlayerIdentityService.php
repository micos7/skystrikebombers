<?php

namespace App\Services;

use App\Data\PlayerIdentity;
use App\Models\GuestPlayer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class PlayerIdentityService
{
    public const COOKIE_NAME = 'bombers_guest_token';

    /** @var list<string> */
    private const CALLSIGN_PREFIXES = [
        'Viper', 'Falcon', 'Raptor', 'Ghost', 'Maverick', 'Talon',
        'Hawk', 'Phoenix', 'Spectre', 'Reaper', 'Cobra', 'Striker',
        'Valkyrie', 'Nomad', 'Apex', 'Raven', 'Hornet', 'Aegis',
    ];

    public function resolve(Request $request): PlayerIdentity
    {
        $existing = $request->attributes->get('player_identity');

        if ($existing instanceof PlayerIdentity) {
            return $existing;
        }

        /** @var User|null $user */
        $user = $request->user();

        if ($user !== null) {
            $identity = new PlayerIdentity(user: $user, guest: null);
            $request->attributes->set('player_identity', $identity);

            return $identity;
        }

        $guest = $this->resolveOrCreateGuest($request);
        $identity = new PlayerIdentity(user: null, guest: $guest);
        $request->attributes->set('player_identity', $identity);

        return $identity;
    }

    public function updateCallsign(PlayerIdentity $identity, string $callsign): void
    {
        $clean = trim($callsign);

        if ($identity->guest !== null) {
            $identity->guest->update([
                'callsign' => $clean,
                'last_seen_at' => now(),
            ]);
        }
    }

    private function resolveOrCreateGuest(Request $request): GuestPlayer
    {
        $rawToken = $request->cookie(self::COOKIE_NAME);

        if (is_string($rawToken) && strlen($rawToken) >= 32) {
            $guest = GuestPlayer::query()
                ->where('token_hash', hash('sha256', $rawToken))
                ->first();

            if ($guest !== null) {
                if ($guest->last_seen_at === null || $guest->last_seen_at->lt(now()->subMinutes(5))) {
                    $guest->forceFill([
                        'ip_address' => $request->ip(),
                        'last_seen_at' => now(),
                    ])->save();
                }

                return $guest;
            }
        }

        $token = Str::random(48);

        $guest = GuestPlayer::query()->create([
            'token_hash' => hash('sha256', $token),
            'callsign' => $this->generateCallsign(),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
            'last_seen_at' => now(),
        ]);

        Cookie::queue(cookie(
            name: self::COOKIE_NAME,
            value: $token,
            minutes: 60 * 24 * 30,
            path: '/',
            domain: null,
            secure: $request->isSecure(),
            httpOnly: true,
            raw: false,
            sameSite: 'lax',
        ));

        return $guest;
    }

    private function generateCallsign(): string
    {
        $prefix = self::CALLSIGN_PREFIXES[array_rand(self::CALLSIGN_PREFIXES)];

        return $prefix.'-'.random_int(10, 99);
    }
}
