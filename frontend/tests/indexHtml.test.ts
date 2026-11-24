import { describe, expect, it } from 'vitest';
import html from '../index.html?raw';
import { textsOf } from './support/texts';

const cs = textsOf('cs');

describe('index.html', () => {
  it('is Czech, the default language', () => {
    expect(html).toContain('<html lang="cs">');
  });

  it('has the Czech page title', () => {
    expect(/<title>([^<]*)<\/title>/.exec(html)?.[1]).toBe(cs.text('page.title'));
  });

  it('has a hidden fallback error equal to the Czech map error', () => {
    const match = /<p class="map-error" role="alert" hidden>([^<]*)<\/p>/.exec(html);

    expect(match?.[1]).toBe(cs.text('map.error'));
  });
});
