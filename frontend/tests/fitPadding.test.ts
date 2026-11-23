import { describe, expect, it } from 'vitest';
import { fitPadding } from '../src/fitPadding';

describe('fitPadding', () => {
  it('keeps the parcel clear of a left panel on a wide screen', () => {
    expect(fitPadding({ right: 332, bottom: 700 }, 1280)).toEqual({ top: 40, right: 40, bottom: 40, left: 356 });
  });

  it('keeps the parcel clear of a top panel on a narrow screen', () => {
    expect(fitPadding({ right: 300, bottom: 250 }, 400)).toEqual({ top: 274, right: 40, bottom: 40, left: 40 });
  });

  it('treats exactly 640 px as narrow', () => {
    expect(fitPadding({ right: 300, bottom: 250 }, 640)).toEqual({ top: 274, right: 40, bottom: 40, left: 40 });
  });
});
