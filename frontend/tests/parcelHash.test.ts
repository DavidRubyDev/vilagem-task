import { describe, expect, it } from 'vitest';
import { hashWithParcel, parcelIdFromHash } from '../src/parcelHash';

describe('parcelIdFromHash', () => {
  it('reads the parcel id', () => {
    expect(parcelIdFromHash('#parcel=1753295604')).toBe(1753295604);
  });

  it('finds the parcel next to other keys', () => {
    expect(parcelIdFromHash('#map=1/2/3&parcel=5')).toBe(5);
  });

  it.each([
    ['an empty hash', ''],
    ['a bare #', '#'],
    ['a non-numeric id', '#parcel=abc'],
    ['zero', '#parcel=0'],
    ['a leading zero', '#parcel=0123'],
    ['16 digits', '#parcel=1234567890123456'],
    ['a negative id', '#parcel=-5'],
    ['another key', '#other=5'],
  ])('ignores %s', (_description, hash) => {
    expect(parcelIdFromHash(hash)).toBeNull();
  });

  it('accepts 15 digits', () => {
    expect(parcelIdFromHash('#parcel=123456789012345')).toBe(123456789012345);
  });
});

describe('hashWithParcel', () => {
  it('sets the parcel on an empty hash', () => {
    expect(hashWithParcel('', 5)).toBe('#parcel=5');
  });

  it('replaces the parcel and keeps other keys', () => {
    expect(hashWithParcel('#map=1/2/3&parcel=4', 5)).toBe('#map=1%2F2%2F3&parcel=5');
  });

  it('keeps other keys readable by parcelIdFromHash after the round trip', () => {
    const hash = hashWithParcel('#map=1/2/3', 5);

    expect(hash).toBe('#map=1%2F2%2F3&parcel=5');
    expect(parcelIdFromHash(hash)).toBe(5);
    expect(new URLSearchParams(hash.slice(1)).get('map')).toBe('1/2/3');
  });

  it('removes the parcel and keeps other keys', () => {
    expect(hashWithParcel('#map=1/2/3&parcel=4', null)).toBe('#map=1%2F2%2F3');
  });

  it('returns an empty string when nothing is left', () => {
    expect(hashWithParcel('#parcel=4', null)).toBe('');
    expect(hashWithParcel('', null)).toBe('');
  });
});
