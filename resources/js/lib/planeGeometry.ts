import type {
    DraftPlacement,
    MissileTypeValue,
    OrientationValue,
    PlaneCell,
    PlaneTypeValue,
} from '@/types/game';

interface OffsetCell {
    dx: number;
    dy: number;
    part: 'cockpit' | 'wing' | 'body' | 'tail';
}

const TEMPLATES: Record<PlaneTypeValue, OffsetCell[]> = {
    interceptor: [
        { dx: 0, dy: 0, part: 'cockpit' },
        { dx: -1, dy: 1, part: 'wing' },
        { dx: 0, dy: 1, part: 'body' },
        { dx: 1, dy: 1, part: 'wing' },
        { dx: 0, dy: 2, part: 'body' },
        { dx: 0, dy: 3, part: 'tail' },
    ],
    fighter: [
        { dx: 0, dy: 0, part: 'cockpit' },
        { dx: -2, dy: 1, part: 'wing' },
        { dx: -1, dy: 1, part: 'wing' },
        { dx: 0, dy: 1, part: 'body' },
        { dx: 1, dy: 1, part: 'wing' },
        { dx: 2, dy: 1, part: 'wing' },
        { dx: 0, dy: 2, part: 'body' },
        { dx: -1, dy: 3, part: 'tail' },
        { dx: 0, dy: 3, part: 'tail' },
        { dx: 1, dy: 3, part: 'tail' },
    ],
    stealth: [
        { dx: 0, dy: 0, part: 'cockpit' },
        { dx: -1, dy: 1, part: 'wing' },
        { dx: 0, dy: 1, part: 'body' },
        { dx: 1, dy: 1, part: 'wing' },
        { dx: -2, dy: 2, part: 'wing' },
        { dx: -1, dy: 2, part: 'tail' },
        { dx: 1, dy: 2, part: 'tail' },
        { dx: 2, dy: 2, part: 'wing' },
    ],
    heavy: [
        { dx: 0, dy: 0, part: 'cockpit' },
        { dx: -2, dy: 1, part: 'wing' },
        { dx: -1, dy: 1, part: 'wing' },
        { dx: 0, dy: 1, part: 'body' },
        { dx: 1, dy: 1, part: 'wing' },
        { dx: 2, dy: 1, part: 'wing' },
        { dx: -1, dy: 2, part: 'wing' },
        { dx: 0, dy: 2, part: 'body' },
        { dx: 1, dy: 2, part: 'wing' },
        { dx: 0, dy: 3, part: 'body' },
        { dx: -1, dy: 4, part: 'tail' },
        { dx: 0, dy: 4, part: 'tail' },
        { dx: 1, dy: 4, part: 'tail' },
    ],
};

function rotateOffset(
    dx: number,
    dy: number,
    orientation: OrientationValue,
): [number, number] {
    switch (orientation) {
        case 'N':
            return [dx, dy];
        case 'E':
            return [-dy, dx];
        case 'S':
            return [-dx, -dy];
        case 'W':
            return [dy, -dx];
    }
}

export function nextOrientation(current: OrientationValue): OrientationValue {
    const order: OrientationValue[] = ['N', 'E', 'S', 'W'];

    return order[(order.indexOf(current) + 1) % order.length];
}

export function computePlaneCells(
    type: PlaneTypeValue,
    originX: number,
    originY: number,
    orientation: OrientationValue,
): PlaneCell[] {
    return TEMPLATES[type].map((offset) => {
        const [rx, ry] = rotateOffset(offset.dx, offset.dy, orientation);

        return {
            x: originX + rx,
            y: originY + ry,
            part: offset.part,
        };
    });
}

export function isPlacementValid(
    cells: PlaneCell[],
    gridSize: number,
    occupiedKeys: Set<string>,
): boolean {
    return cells.every(
        (c) =>
            c.x >= 0 &&
            c.x < gridSize &&
            c.y >= 0 &&
            c.y < gridSize &&
            !occupiedKeys.has(`${c.x},${c.y}`),
    );
}

export function generateRandomDraftFleet(
    roster: PlaneTypeValue[],
    gridSize: number,
): DraftPlacement[] {
    const orientations: OrientationValue[] = ['N', 'E', 'S', 'W'];

    for (let attempt = 0; attempt < 60; attempt++) {
        const occupied = new Set<string>();
        const placements: DraftPlacement[] = [];
        let ok = true;

        for (let i = 0; i < roster.length; i++) {
            const type = roster[i];
            let placed = false;

            for (let tries = 0; tries < 120; tries++) {
                const orientation =
                    orientations[
                        Math.floor(Math.random() * orientations.length)
                    ];
                const originX = Math.floor(Math.random() * gridSize);
                const originY = Math.floor(Math.random() * gridSize);
                const cells = computePlaneCells(
                    type,
                    originX,
                    originY,
                    orientation,
                );

                if (!isPlacementValid(cells, gridSize, occupied)) {
                    continue;
                }

                for (const c of cells) {
                    occupied.add(`${c.x},${c.y}`);
                }

                placements.push({
                    id: `${type}-${i}`,
                    plane_type: type,
                    origin_x: originX,
                    origin_y: originY,
                    orientation,
                });
                placed = true;
                break;
            }

            if (!placed) {
                ok = false;
                break;
            }
        }

        if (ok) {
            return placements;
        }
    }

    return [];
}

export function computeWeaponFootprint(
    missile: MissileTypeValue,
    targetX: number,
    targetY: number,
    gridSize: number,
    orientation: 'H' | 'V' = 'H',
): Array<{ x: number; y: number }> {
    let offsets: Array<[number, number]>;

    switch (missile) {
        case 'standard':
            offsets = [[0, 0]];
            break;
        case 'radar':
            offsets = [
                [-1, -1],
                [0, -1],
                [1, -1],
                [-1, 0],
                [0, 0],
                [1, 0],
                [-1, 1],
                [0, 1],
                [1, 1],
            ];
            break;
        case 'cluster':
            offsets = [
                [0, 0],
                [-1, 0],
                [1, 0],
                [0, -1],
                [0, 1],
            ];
            break;
        case 'carpet':
            offsets =
                orientation === 'V'
                    ? [
                          [0, -1],
                          [0, 0],
                          [0, 1],
                          [0, 2],
                      ]
                    : [
                          [-1, 0],
                          [0, 0],
                          [1, 0],
                          [2, 0],
                      ];
            break;
        case 'emp':
            offsets = [
                [0, 0],
                [1, 0],
                [0, 1],
                [1, 1],
            ];
            break;
    }

    return offsets
        .map(([dx, dy]) => ({ x: targetX + dx, y: targetY + dy }))
        .filter(
            (c) => c.x >= 0 && c.x < gridSize && c.y >= 0 && c.y < gridSize,
        );
}

export function formatCoordinate(x: number, y: number): string {
    const col = String.fromCharCode(65 + x);

    return `${col}${y + 1}`;
}
