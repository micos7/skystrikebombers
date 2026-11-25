export type GameModeValue = 'classic' | 'tactical' | 'blitz';
export type GameStatusValue =
    | 'waiting'
    | 'placing'
    | 'playing'
    | 'finished'
    | 'abandoned';
export type PlaneTypeValue = 'interceptor' | 'fighter' | 'stealth' | 'heavy';
export type OrientationValue = 'N' | 'E' | 'S' | 'W';
export type MissileTypeValue =
    | 'standard'
    | 'radar'
    | 'cluster'
    | 'carpet'
    | 'emp';
export type CellOutcomeValue =
    | 'miss'
    | 'hit'
    | 'cockpit_cracked'
    | 'cockpit_killed'
    | 'radar_ping';

export interface PlaneCell {
    x: number;
    y: number;
    part: 'cockpit' | 'wing' | 'body' | 'tail';
}

export interface PlaneState {
    id: string;
    plane_type: PlaneTypeValue;
    label: string;
    origin_x: number;
    origin_y: number;
    orientation: OrientationValue;
    cells: PlaneCell[];
    hit_cells: string[];
    cockpit_hp: number;
    max_cockpit_hp: number;
    is_destroyed: boolean;
    destroyed_at_turn: number | null;
}

export interface FleetRosterItem {
    plane_type: PlaneTypeValue;
    label: string;
    cockpit_hp: number;
    is_stealth: boolean;
    perk: string;
}

export interface MissileMeta {
    type: MissileTypeValue;
    label: string;
    description: string;
    is_special: boolean;
}

export interface ShotCellResult {
    x: number;
    y: number;
    outcome: CellOutcomeValue;
    plane_type: PlaneTypeValue | null;
}

export interface DestroyedPlaneSnapshot {
    id: string;
    plane_type: PlaneTypeValue;
    origin_x: number;
    origin_y: number;
    orientation: OrientationValue;
    cells: PlaneCell[];
}

export interface ShotState {
    id: string;
    attacker_slot: number;
    turn_number: number;
    missile_type: MissileTypeValue;
    target_x: number;
    target_y: number;
    orientation: 'H' | 'V' | null;
    results: ShotCellResult[];
    radar_count: number | null;
    destroyed_planes: DestroyedPlaneSnapshot[];
    created_at: string | null;
}

export interface CombatLogEntry {
    id: string;
    turn_number: number;
    attacker_slot: number;
    attacker_callsign: string;
    missile_type: MissileTypeValue;
    missile_label: string;
    target_x: number;
    target_y: number;
    orientation: 'H' | 'V' | null;
    results: ShotCellResult[];
    radar_count: number | null;
    destroyed_count: number;
    created_at: string | null;
}

export interface ChatMessage {
    id: string;
    sender_slot: number;
    sender_callsign: string;
    body: string;
    is_emote: boolean;
    created_at: string | null;
}

export interface PlayerSummary {
    slot: number;
    callsign: string;
    is_ai: boolean;
    is_ready: boolean;
    rematch_requested: boolean;
    planes_alive: number;
    planes_total: number;
}

export interface EnemyFleetSummaryItem {
    plane_type: PlaneTypeValue;
    label: string;
    is_destroyed: boolean;
    cockpit_cracked: boolean;
}

export interface GameStatePayload {
    id: string;
    code: string;
    mode: GameModeValue;
    mode_label: string;
    status: GameStatusValue;
    grid_size: number;
    is_public: boolean;
    is_vs_ai: boolean;
    settings: {
        turn_timer_seconds: number | null;
        extra_turn_on_kill: boolean;
    };
    fleet_roster: FleetRosterItem[];
    missiles_meta: MissileMeta[];
    current_turn_slot: number | null;
    winner_slot: number | null;
    turn_number: number;
    turn_seconds_remaining: number | null;
    invite_url: string;
    rematch_game_code: string | null;
    viewer: {
        id: string;
        callsign: string;
        is_guest: boolean;
        slot: number | null;
        is_participant: boolean;
        is_my_turn: boolean;
        is_ready: boolean;
        ammo: Record<MissileTypeValue, number | null>;
        emp_jammed: boolean;
        rematch_requested: boolean;
    };
    players: PlayerSummary[];
    my_planes: PlaneState[];
    enemy_revealed_planes: PlaneState[];
    enemy_fleet_summary: EnemyFleetSummaryItem[];
    my_shots: ShotState[];
    enemy_shots: ShotState[];
    combat_log: CombatLogEntry[];
    messages: ChatMessage[];
}

export interface DraftPlacement {
    id: string;
    plane_type: PlaneTypeValue;
    origin_x: number;
    origin_y: number;
    orientation: OrientationValue;
}
