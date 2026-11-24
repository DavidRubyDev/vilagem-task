import { describe, expect, it } from 'vitest';
import { SEARCH_MAX_LENGTH, searchRequestUrl, shouldSearch } from '../src/searchQuery';

describe('shouldSearch', () => {
  it.each([
    ['', false],
    ['   ', false],
    [' a ', false],
    ['ab', true],
    ['st', true],
    ['1', true],
    [' 7 ', true],
    ['  ab  ', true],
  ])('decides %j: %s', (text, expected) => {
    expect(shouldSearch(text)).toBe(expected);
  });

  it('accepts 100 characters and refuses 101', () => {
    expect(SEARCH_MAX_LENGTH).toBe(100);
    expect(shouldSearch('a'.repeat(100))).toBe(true);
    expect(shouldSearch('a'.repeat(101))).toBe(false);
  });

  it('counts the trimmed text', () => {
    expect(shouldSearch(` ${'a'.repeat(100)} `)).toBe(true);
  });

  it('counts characters, not UTF-16 units', () => {
    expect(shouldSearch('č'.repeat(100))).toBe(true);
    expect(shouldSearch('😀'.repeat(100))).toBe(true);
  });
});

describe('searchRequestUrl', () => {
  it('puts the trimmed text and the map centre into the query', () => {
    expect(searchRequestUrl('/api/search/v1', ' st. 72 ', { lng: 15.35166, lat: 50.43704 })).toBe(
      '/api/search/v1?q=st.%2072&near=15.352,50.437',
    );
  });

  it('encodes diacritics, slashes and spaces', () => {
    expect(searchRequestUrl('/api/search/v1', 'Valdštejnovo náměstí 80/2', { lng: 15, lat: 50 })).toBe(
      '/api/search/v1?q=Vald%C5%A1tejnovo%20n%C3%A1m%C4%9Bst%C3%AD%2080%2F2&near=15.000,50.000',
    );
  });

  it('rounds the centre to three decimals and keeps the sign', () => {
    expect(searchRequestUrl('/api/search/v1', 'x1', { lng: -0.0004, lat: 50.0005 })).toBe(
      '/api/search/v1?q=x1&near=-0.000,50.001',
    );
  });
});
