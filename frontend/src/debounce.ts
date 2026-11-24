/** Calls `fn` once, with the arguments of the last call, when no call has come for `delayMs`. */
export function debounce<A extends unknown[]>(
  fn: (...args: A) => void,
  delayMs: number,
): { call(...args: A): void; cancel(): void } {
  let timer: ReturnType<typeof setTimeout> | null = null;
  const cancel = (): void => {
    if (timer !== null) {
      clearTimeout(timer);
      timer = null;
    }
  };
  return {
    call(...args: A): void {
      cancel();
      timer = setTimeout(() => {
        timer = null;
        fn(...args);
      }, delayMs);
    },
    cancel,
  };
}
