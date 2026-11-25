<?php

use App\Enums\CellOutcome;
use App\Enums\MissileType;
use App\Enums\Orientation;
use App\Enums\PlaneType;
use App\Game\OrdnanceResolver;
use App\Game\PlaneGeometry;
use App\Models\GamePlane;
use Illuminate\Support\Str;

function makePlane(PlaneType $type, int $x, int $y, Orientation $orientation = Orientation::North): GamePlane
{
    $geometry = new PlaneGeometry;
    $plane = new GamePlane;
    $plane->id = (string) Str::uuid();
    $plane->plane_type = $type;
    $plane->origin_x = $x;
    $plane->origin_y = $y;
    $plane->orientation = $orientation;
    $plane->cells = $geometry->cellsFor($type, $x, $y, $orientation);
    $plane->hit_cells = [];
    $plane->cockpit_hp = $type->cockpitHp();
    $plane->max_cockpit_hp = $type->cockpitHp();
    $plane->is_destroyed = false;

    return $plane;
}

it('instant-kills a standard 1-HP cockpit with a Standard SAM', function (): void {
    $resolver = new OrdnanceResolver;
    $fighter = makePlane(PlaneType::Fighter, 4, 2);

    $res = $resolver->resolve(MissileType::Standard, 4, 2, 12, 1, collect([$fighter]));

    expect($res['results'][0]['outcome'])->toBe(CellOutcome::CockpitKilled->value);
    expect($res['cockpit_kill_scored'])->toBeTrue();
    expect($res['all_destroyed'])->toBeTrue();
    expect($fighter->is_destroyed)->toBeTrue();
});

it('requires two Standard hits to kill a Heavy Stratofortress armored cockpit, but EMP one-shots it', function (): void {
    $resolver = new OrdnanceResolver;
    $heavy = makePlane(PlaneType::Heavy, 5, 2);

    $first = $resolver->resolve(MissileType::Standard, 5, 2, 12, 1, collect([$heavy]));
    expect($first['results'][0]['outcome'])->toBe(CellOutcome::CockpitCracked->value);
    expect($heavy->is_destroyed)->toBeFalse();
    expect($heavy->cockpit_hp)->toBe(1);

    $second = $resolver->resolve(MissileType::Standard, 5, 2, 12, 2, collect([$heavy]));
    expect($second['results'][0]['outcome'])->toBe(CellOutcome::CockpitKilled->value);
    expect($heavy->is_destroyed)->toBeTrue();

    // Fresh Heavy vs EMP Bunker-Buster (pierces 2-HP armor in 1 hit + applies jam)
    $heavyTwo = makePlane(PlaneType::Heavy, 5, 2);
    $emp = $resolver->resolve(MissileType::Emp, 5, 2, 12, 1, collect([$heavyTwo]));

    expect($emp['emp_jam_applied'])->toBeTrue();
    expect($emp['cockpit_kill_scored'])->toBeTrue();
    expect($heavyTwo->is_destroyed)->toBeTrue();
});

it('hides Stealth Bombers from Radar Recon sweeps while detecting non-stealth planes', function (): void {
    $resolver = new OrdnanceResolver;
    $stealth = makePlane(PlaneType::Stealth, 4, 4);
    $interceptor = makePlane(PlaneType::Interceptor, 9, 4);

    $stealthScan = $resolver->resolve(MissileType::Radar, 4, 5, 12, 1, collect([$stealth, $interceptor]));
    expect($stealthScan['radar_count'])->toBe(0);

    $interceptorScan = $resolver->resolve(MissileType::Radar, 9, 5, 12, 2, collect([$stealth, $interceptor]));
    expect($interceptorScan['radar_count'])->toBeGreaterThan(0);
});

it('prevents Cluster Bomb from instant-killing a cockpit while still damaging 5 cells', function (): void {
    $resolver = new OrdnanceResolver;
    $fighter = makePlane(PlaneType::Fighter, 5, 4);

    $res = $resolver->resolve(MissileType::Cluster, 5, 4, 12, 1, collect([$fighter]));

    expect($res['results'])->toHaveCount(5);
    expect($res['results'][0]['outcome'])->toBe(CellOutcome::CockpitCracked->value);
    expect($fighter->is_destroyed)->toBeFalse();
});

it('sweeps a 1x4 line with Carpet Bomb in both Horizontal and Vertical orientations', function (): void {
    $resolver = new OrdnanceResolver;

    $horiz = $resolver->targetCells(MissileType::Carpet, 5, 5, 12, 'H');
    $vert = $resolver->targetCells(MissileType::Carpet, 5, 5, 12, 'V');

    expect($horiz)->toBe([
        ['x' => 4, 'y' => 5],
        ['x' => 5, 'y' => 5],
        ['x' => 6, 'y' => 5],
        ['x' => 7, 'y' => 5],
    ]);

    expect($vert)->toBe([
        ['x' => 5, 'y' => 4],
        ['x' => 5, 'y' => 5],
        ['x' => 5, 'y' => 6],
        ['x' => 5, 'y' => 7],
    ]);
});
