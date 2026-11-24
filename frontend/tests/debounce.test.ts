import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { debounce } from '../src/debounce';

describe('debounce', () => {
  beforeEach(() => {
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('calls once, after the delay, with the last arguments', () => {
    const calls: string[] = [];
    const debounced = debounce((text: string) => calls.push(text), 200);

    debounced.call('a');
    vi.advanceTimersByTime(150);
    debounced.call('ab');
    vi.advanceTimersByTime(150);
    expect(calls).toEqual([]);
    debounced.call('abc');
    vi.advanceTimersByTime(199);
    expect(calls).toEqual([]);
    vi.advanceTimersByTime(1);

    expect(calls).toEqual(['abc']);
  });

  it('calls again for a call after the delay', () => {
    const calls: string[] = [];
    const debounced = debounce((text: string) => calls.push(text), 200);

    debounced.call('a');
    vi.advanceTimersByTime(200);
    debounced.call('b');
    vi.advanceTimersByTime(200);

    expect(calls).toEqual(['a', 'b']);
  });

  it('does not call after cancel', () => {
    const calls: string[] = [];
    const debounced = debounce((text: string) => calls.push(text), 200);

    debounced.call('a');
    debounced.cancel();
    vi.advanceTimersByTime(1000);

    expect(calls).toEqual([]);
  });

  it('can be cancelled with nothing pending and used afterwards', () => {
    const calls: string[] = [];
    const debounced = debounce((text: string) => calls.push(text), 200);

    debounced.cancel();
    debounced.call('a');
    vi.advanceTimersByTime(200);

    expect(calls).toEqual(['a']);
  });
});
