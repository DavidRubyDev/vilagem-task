import { describe, expect, it } from 'vitest';
import { zoomHintVisible } from '../src/zoomHint';

describe('zoomHintVisible', () => {
  it('shows the hint below zoom 14 while the panel is closed', () => {
    expect(zoomHintVisible(12, false)).toBe(true);
  });

  it('hides the hint while the panel is open', () => {
    expect(zoomHintVisible(12, true)).toBe(false);
  });

  it('hides the hint from zoom 14, whether or not the panel is open', () => {
    expect(zoomHintVisible(14, false)).toBe(false);
    expect(zoomHintVisible(14, true)).toBe(false);
    expect(zoomHintVisible(18, false)).toBe(false);
    expect(zoomHintVisible(18, true)).toBe(false);
  });
});
