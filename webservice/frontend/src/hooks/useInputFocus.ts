import { useEffect, type RefObject } from 'react';

/** Focus an opening input once it is actually focusable, not after a guessed delay.
 * Stop after success, user interaction elsewhere, closing, or disabling it.
 */
export function useInputFocus(ref: RefObject<HTMLInputElement>, enabled: boolean) {
  useEffect(() => {
    if (!enabled) return;
    let frame: number;
    let cancelled = false;
    const initial = document.activeElement;
    const cancel = (event: Event) => {
      if (event.target !== ref.current) cancelled = true;
    };
    const focus = () => {
      const input = ref.current;
      if (cancelled || !input || !input.isConnected || input.disabled) return;
      const active = document.activeElement;
      if (active === input && document.hasFocus()) return;
      if (active !== document.body && active !== initial && active !== input) return;
      if (document.hasFocus() && !input.closest('[inert]') && input.getClientRects().length && getComputedStyle(input).visibility === 'visible') {
        input.focus({ preventScroll: true });
        if (document.activeElement === input) return;
      }
      frame = requestAnimationFrame(focus);
    };
    frame = requestAnimationFrame(focus);
    document.addEventListener('pointerdown', cancel, true);
    document.addEventListener('keydown', cancel, true);
    return () => {
      cancelled = true; cancelAnimationFrame(frame);
      document.removeEventListener('pointerdown', cancel, true);
      document.removeEventListener('keydown', cancel, true);
    };
  }, [enabled, ref]);
}
