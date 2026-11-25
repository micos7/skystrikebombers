let audioCtx: AudioContext | null = null;
let muted = false;

function getContext(): AudioContext | null {
    if (muted || typeof window === 'undefined') {
        return null;
    }

    if (!audioCtx) {
        const Ctx =
            window.AudioContext ||
            (window as unknown as { webkitAudioContext?: typeof AudioContext })
                .webkitAudioContext;

        if (!Ctx) {
            return null;
        }

        audioCtx = new Ctx();
    }

    if (audioCtx.state === 'suspended') {
        void audioCtx.resume();
    }

    return audioCtx;
}

export function isSoundMuted(): boolean {
    return muted;
}

export function setSoundMuted(value: boolean): void {
    muted = value;
}

function playTone(
    frequency: number,
    duration: number,
    type: OscillatorType = 'sine',
    gainValue = 0.08,
    endFrequency?: number,
    delay = 0,
): void {
    const ctx = getContext();

    if (!ctx) {
        return;
    }

    const now = ctx.currentTime + delay;
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();

    osc.type = type;
    osc.frequency.setValueAtTime(frequency, now);

    if (endFrequency !== undefined) {
        osc.frequency.exponentialRampToValueAtTime(
            Math.max(20, endFrequency),
            now + duration,
        );
    }

    gain.gain.setValueAtTime(gainValue, now);
    gain.gain.exponentialRampToValueAtTime(0.0001, now + duration);

    osc.connect(gain);
    gain.connect(ctx.destination);

    osc.start(now);
    osc.stop(now + duration);
}

export const soundFx = {
    place(): void {
        playTone(440, 0.08, 'triangle', 0.06, 620);
    },
    radar(): void {
        playTone(880, 0.25, 'sine', 0.07, 1320);
        playTone(1320, 0.3, 'sine', 0.05, 920, 0.12);
    },
    miss(): void {
        playTone(220, 0.16, 'sine', 0.05, 110);
    },
    hit(): void {
        playTone(180, 0.22, 'sawtooth', 0.09, 65);
        playTone(320, 0.12, 'square', 0.05, 140, 0.04);
    },
    cockpitKill(): void {
        playTone(150, 0.42, 'sawtooth', 0.12, 42);
        playTone(520, 0.16, 'square', 0.07, 260, 0.05);
        playTone(780, 0.22, 'triangle', 0.08, 1040, 0.18);
    },
    victory(): void {
        playTone(523.25, 0.18, 'triangle', 0.08, undefined, 0);
        playTone(659.25, 0.18, 'triangle', 0.08, undefined, 0.15);
        playTone(783.99, 0.22, 'triangle', 0.09, undefined, 0.3);
        playTone(1046.5, 0.45, 'triangle', 0.1, undefined, 0.48);
    },
};
