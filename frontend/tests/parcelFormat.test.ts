import { describe, expect, it } from 'vitest';
import { formatArea, formatCadastralArea, plotTypeName } from '../src/parcelFormat';
import { textsOf } from './support/texts';

const cs = textsOf('cs');
const en = textsOf('en');

describe('formatArea', () => {
  it('appends square metres', () => {
    expect(formatArea(455, cs)).toBe('455 m²');
  });

  it('groups thousands with a no-break space in Czech', () => {
    expect(formatArea(13077, cs)).toBe('13 077 m²');
  });

  it('groups thousands with a comma in English', () => {
    expect(formatArea(13077, en)).toBe('13,077 m²');
  });
});

describe('formatCadastralArea', () => {
  it('shows the name and the code in brackets', () => {
    expect(formatCadastralArea('Jičín', 659541)).toBe('Jičín (659541)');
  });
});

describe('plotTypeName', () => {
  it('names building and land plots in both languages', () => {
    expect(plotTypeName('building', en)).toBe('Building plot');
    expect(plotTypeName('land', en)).toBe('Land plot');
    expect(plotTypeName('building', cs)).toBe('Stavební parcela');
    expect(plotTypeName('land', cs)).toBe('Pozemková parcela');
  });
});
