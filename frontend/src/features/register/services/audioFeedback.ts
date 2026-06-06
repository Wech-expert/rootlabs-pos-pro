let audioContext: AudioContext | null = null;

function getContext(): AudioContext | null {
  try {
    if (!audioContext) {
      audioContext = new AudioContext();
    }
    return audioContext;
  } catch {
    return null;
  }
}

function playTone(
  ctx: AudioContext,
  frequency: number,
  startTime: number,
  duration: number,
  gainValue: number,
): void {
  const osc = ctx.createOscillator();
  const gain = ctx.createGain();

  osc.type = 'triangle';
  osc.frequency.setValueAtTime(frequency, startTime);

  gain.gain.setValueAtTime(0.0001, startTime);
  gain.gain.exponentialRampToValueAtTime(gainValue, startTime + 0.015);
  gain.gain.exponentialRampToValueAtTime(0.0001, startTime + duration);

  osc.connect(gain);
  gain.connect(ctx.destination);

  osc.start(startTime);
  osc.stop(startTime + duration + 0.02);
}

async function playBeepInternal(): Promise<void> {
  try {
    const ctx = getContext();

    if (!ctx) {
      return;
    }

    if (ctx.state === 'suspended') {
      await ctx.resume();
    }

    const now = ctx.currentTime;

    playTone(ctx, 659.25, now,          0.18, 0.07);
    playTone(ctx, 987.77, now + 0.04,   0.18, 0.05);

    if (import.meta.env.DEV) {
      console.debug('[MX POS] success chime');
    }
  } catch {
    // Silently fail — el audio no debe bloquear la venta
  }
}

export function playBeep(): void {
  if (window.mxPosProSettings?.beepEnabled === false) {
    return;
  }

  void playBeepInternal();
}
