import { describe, expect, it } from 'vitest';
import { formatArea, formatCadastralArea, plotTypeName } from '../src/parcelFormat';

describe('formatArea', () => {
  it('appends square metres', () => {
    expect(formatArea(455)).toBe('455 m²');
  });

  it('groups thousands', () => {
    expect(formatArea(13077)).toBe('13,077 m²');
  });
});

describe('formatCadastralArea', () => {
  it('shows the name and the code in brackets', () => {
    expect(formatCadastralArea('Jičín', 659541)).toBe('Jičín (659541)');
  });
});

describe('plotTypeName', () => {
  it('names building and land plots', () => {
    expect(plotTypeName('building')).toBe('Building plot');
    expect(plotTypeName('land')).toBe('Land plot');
  });
});
