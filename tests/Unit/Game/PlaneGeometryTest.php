<?php

use App\Enums\GameMode;
use App\Enums\Orientation;
use App\Enums\PlaneType;
use App\Game\PlaneGeometry;

it('computes exact cell counts for each aircraft class', function (PlaneType $type, int $expectedCount): void {
    $geometry = new PlaneGeometry;
    $cells = $geometry->cellsFor($type, 5, 5, Orientation::North);

    expect($cells)->toHaveCount($expectedCount);
    expect($cells[0])->toBe(['x' => 5, 'y' => 5, 'part' => 'cockpit']);
})->with([
    'interceptor' => [PlaneType::Interceptor, 6],
    'fighter' => [PlaneType::Fighter, 10],
    'stealth' => [PlaneType::Stealth, 8],
    'heavy' => [PlaneType::Heavy, 13],
]);

it('rotates aircraft cells accurately in all four cardinal directions', function (): void {
    $geometry = new PlaneGeometry;

    $north = $geometry->cellsFor(PlaneType::Interceptor, 5, 5, Orientation::North);
    $east = $geometry->cellsFor(PlaneType::Interceptor, 5, 5, Orientation::East);
    $south = $geometry->cellsFor(PlaneType::Interceptor, 5, 5, Orientation::South);
    $west = $geometry->cellsFor(PlaneType::Interceptor, 5, 5, Orientation::West);

    // Tail is (dx=0, dy=3) facing North -> (5, 8)
    expect(collect($north)->firstWhere('part', 'tail'))->toBe(['x' => 5, 'y' => 8, 'part' => 'tail']);
    // Facing East -> tail is (-3, 0) -> (2, 5)
    expect(collect($east)->firstWhere('part', 'tail'))->toBe(['x' => 2, 'y' => 5, 'part' => 'tail']);
    // Facing South -> tail is (0, -3) -> (5, 2)
    expect(collect($south)->firstWhere('part', 'tail'))->toBe(['x' => 5, 'y' => 2, 'part' => 'tail']);
    // Facing West -> tail is (3, 0) -> (8, 5)
    expect(collect($west)->firstWhere('part', 'tail'))->toBe(['x' => 8, 'y' => 5, 'part' => 'tail']);
});

it('rejects out-of-bounds and overlapping fleet placements', function (): void {
    $geometry = new PlaneGeometry;

    expect(fn () => $geometry->validateFleet(GameMode::Classic, 10, [
        ['plane_type' => 'fighter', 'origin_x' => 0, 'origin_y' => 0, 'orientation' => 'N'],
        ['plane_type' => 'fighter', 'origin_x' => 5, 'origin_y' => 0, 'orientation' => 'N'],
        ['plane_type' => 'fighter', 'origin_x' => 5, 'origin_y' => 5, 'orientation' => 'N'],
    ]))->toThrow(InvalidArgumentException::class, 'outside');

    expect(fn () => $geometry->validateFleet(GameMode::Classic, 10, [
        ['plane_type' => 'fighter', 'origin_x' => 2, 'origin_y' => 0, 'orientation' => 'N'],
        ['plane_type' => 'fighter', 'origin_x' => 2, 'origin_y' => 0, 'orientation' => 'N'],
        ['plane_type' => 'fighter', 'origin_x' => 7, 'origin_y' => 5, 'orientation' => 'N'],
    ]))->toThrow(InvalidArgumentException::class, 'overlap');
});

it('generates valid randomized fleets for all game modes', function (GameMode $mode): void {
    $geometry = new PlaneGeometry;
    $placements = $geometry->generateRandomFleet($mode, $mode->gridSize());
    $validated = $geometry->validateFleet($mode, $mode->gridSize(), $placements);

    expect($validated)->toHaveCount(count($mode->fleetRoster()));
})->with([
    'classic' => [GameMode::Classic],
    'tactical' => [GameMode::Tactical],
    'blitz' => [GameMode::Blitz],
]);
