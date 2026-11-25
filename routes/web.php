<?php

use App\Http\Controllers\Game\BroadcastAuthController;
use App\Http\Controllers\Game\GameActionController;
use App\Http\Controllers\Game\GameChatController;
use App\Http\Controllers\Game\GameController;
use App\Http\Controllers\Game\LobbyController;
use App\Http\Middleware\EnsurePlayerIdentity;
use Illuminate\Support\Facades\Route;

Route::middleware([EnsurePlayerIdentity::class])->group(function (): void {
    Route::get('/', [LobbyController::class, 'index'])->name('home');
    Route::get('/lobby', [LobbyController::class, 'index'])->name('games.lobby');
    Route::post('/guest/callsign', [LobbyController::class, 'updateCallsign'])->name('guest.callsign.update');

    Route::post('/games', [LobbyController::class, 'store'])->name('games.store');
    Route::get('/games/join/{code}', [GameController::class, 'joinByLink'])->name('games.join.link');
    Route::post('/games/broadcasting/auth', BroadcastAuthController::class)->name('games.broadcasting.auth');

    Route::get('/games/{game}', [GameController::class, 'show'])->name('games.show');
    Route::get('/games/{game}/state', [GameController::class, 'state'])->name('games.state');
    Route::post('/games/{game}/join', [GameController::class, 'join'])->name('games.join');
    Route::post('/games/{game}/fleet', [GameActionController::class, 'placeFleet'])->name('games.fleet.store');
    Route::post('/games/{game}/fire', [GameActionController::class, 'fire'])->name('games.fire');
    Route::post('/games/{game}/forfeit', [GameActionController::class, 'forfeit'])->name('games.forfeit');
    Route::post('/games/{game}/rematch', [GameActionController::class, 'rematch'])->name('games.rematch');
    Route::post('/games/{game}/messages', [GameChatController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('games.messages.store');
});

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
