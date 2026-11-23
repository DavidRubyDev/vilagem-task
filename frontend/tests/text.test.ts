import { describe, expect, it } from 'vitest';
import { TEXT, UI_LOCALE } from '../src/text';

describe('TEXT', () => {
  it('words the unknown codes with the code', () => {
    expect(TEXT.unknownLandType(99)).toBe('Unknown land type (code 99)');
    expect(TEXT.unknownLandUse(31)).toBe('Unknown land use (code 31)');
  });

  it('puts the parcel number into the heading', () => {
    expect(TEXT.heading('st. 72')).toBe('Parcel st. 72');
  });

  it('words the zoom hint and the notice about missing parcel details', () => {
    expect(TEXT.zoomHint).toBe('Zoom in to select a parcel.');
    expect(TEXT.detailsNotice).toBe('Parcel details have not been imported yet. Run make data, then reload this page.');
  });

  it('uses English', () => {
    expect(UI_LOCALE).toBe('en');
  });
});
