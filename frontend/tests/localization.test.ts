import { afterEach, describe, expect, it, vi } from 'vitest';
import { Localization } from '../src/localization';
import type { Texts } from '../src/texts';
import { textsOf } from './support/texts';

const cs = textsOf('cs');
const en = textsOf('en');

interface Pending {
  readonly language: string;
  resolve(texts: Texts): void;
  reject(error: unknown): void;
}

function setup(): { localization: Localization; loads: Pending[]; notified: Texts[] } {
  const loads: Pending[] = [];
  const localization = new Localization(cs, (language) => {
    return new Promise<Texts>((resolve, reject) => {
      loads.push({ language, resolve, reject });
    });
  });
  const notified: Texts[] = [];
  localization.onChange((texts) => notified.push(texts));
  return { localization, loads, notified };
}

afterEach(() => {
  vi.restoreAllMocks();
});

describe('Localization', () => {
  it('starts with the initial texts', () => {
    expect(setup().localization.current()).toBe(cs);
  });

  it('loads a new language once, makes it current and notifies every listener once', async () => {
    const { localization, loads, notified } = setup();
    const second: Texts[] = [];
    localization.onChange((texts) => second.push(texts));

    const switching = localization.switchTo('en');
    loads[0]?.resolve(en);
    await switching;

    expect(loads.map((load) => load.language)).toEqual(['en']);
    expect(localization.current()).toBe(en);
    expect(notified).toEqual([en]);
    expect(second).toEqual([en]);
  });

  it('switches back to a cached language without loading it', async () => {
    const { localization, loads, notified } = setup();
    const first = localization.switchTo('en');
    loads[0]?.resolve(en);
    await first;

    await localization.switchTo('cs');

    expect(loads).toHaveLength(1);
    expect(localization.current()).toBe(cs);
    expect(notified).toEqual([en, cs]);
  });

  it('does nothing when switching to the current language', async () => {
    const { localization, loads, notified } = setup();

    await localization.switchTo('cs');

    expect(loads).toHaveLength(0);
    expect(notified).toEqual([]);
    expect(localization.current()).toBe(cs);
  });

  it('applies only the last request: an earlier pending load is cached but not applied', async () => {
    const { localization, loads, notified } = setup();
    const toEnglish = localization.switchTo('en');
    const backToCzech = localization.switchTo('cs');

    loads[0]?.resolve(en);
    await Promise.all([toEnglish, backToCzech]);

    expect(localization.current()).toBe(cs);
    expect(notified).toEqual([]);
    await localization.switchTo('en');
    expect(loads).toHaveLength(1);
    expect(localization.current()).toBe(en);
    expect(notified).toEqual([en]);
  });

  it('keeps the current language, notifies nobody and warns when a load fails', async () => {
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => undefined);
    const { localization, loads, notified } = setup();

    const switching = localization.switchTo('en');
    loads[0]?.reject(new Error('down'));
    await expect(switching).resolves.toBeUndefined();

    expect(localization.current()).toBe(cs);
    expect(notified).toEqual([]);
    expect(warn).toHaveBeenCalledWith('The texts could not be loaded.', expect.any(Error));
  });

  it('does not cache a failure: a new switch loads again', async () => {
    vi.spyOn(console, 'warn').mockImplementation(() => undefined);
    const { localization, loads } = setup();
    const failing = localization.switchTo('en');
    loads[0]?.reject(new Error('down'));
    await failing;

    const retry = localization.switchTo('en');
    loads[1]?.resolve(en);
    await retry;

    expect(loads).toHaveLength(2);
    expect(localization.current()).toBe(en);
  });
});
