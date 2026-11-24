import type { Locator, Page } from '@playwright/test';
import { FIXTURE_VERSION } from './support/fixture';
import { countHighlightPixels } from './support/highlight';
import { expect, test } from './support/offlineTiles';

const SEARCH_URL = `/api/search/${FIXTURE_VERSION}`;

function searchBox(page: Page): Locator {
  return page.getByRole('combobox', { name: 'Hledat na mapě' });
}

function panelOf(page: Page): Locator {
  return page.getByRole('complementary', { name: 'Podrobnosti o parcele' });
}

function parcelBody(id: number, parcelNumber: string): Record<string, unknown> {
  return {
    id,
    parcelNumber,
    kind: 'land',
    cadastralArea: { code: 659541, name: 'Jičín' },
    municipality: { code: 572659, name: 'Jičín' },
  };
}

async function stubSearch(page: Page, status: number, json: unknown): Promise<void> {
  await page.route(`**${SEARCH_URL}?*`, async (route) => {
    await route.fulfill({ status, json });
  });
}

async function openMap(page: Page): Promise<void> {
  await page.goto('/');
  await expect(page.locator('.maplibregl-canvas')).toBeVisible();
  await expect(searchBox(page)).toBeVisible();
}

test.describe('searching parcels by number', () => {
  test('finds a building parcel by its number and opens it with Enter', async ({ page }) => {
    await openMap(page);

    await searchBox(page).fill('st. 72');

    const option = page.getByRole('option', { name: /Parcela st\. 72/ });
    await expect(option).toBeVisible();
    await expect(option).toContainText('Jičín (659541)');
    await expect(page.getByRole('group', { name: 'Parcely' })).toBeVisible();
    await searchBox(page).press('Enter');

    await expect(panelOf(page).getByRole('heading')).toHaveText('Parcela st. 72');
    await expect.poll(() => new URL(page.url()).hash).toBe('#parcel=1753295604');
    await expect.poll(() => countHighlightPixels(page)).toBeGreaterThan(100);
    await expect(searchBox(page)).toHaveValue('Parcela st. 72');
    await expect(page.getByRole('listbox')).toBeHidden();
    await expect(searchBox(page)).not.toBeFocused();
  });

  test('finds a parcel of a cadastral area by a start of its name and opens it with a click', async ({ page }) => {
    await openMap(page);

    await searchBox(page).fill('867/121 rob');
    await page.getByRole('option', { name: /Parcela 867\/121/ }).click();

    await expect(panelOf(page).getByRole('heading')).toHaveText('Parcela 867/121');
    await expect(panelOf(page)).toContainText('Robousy (740225)');
    await expect.poll(() => new URL(page.url()).hash).toBe('#parcel=99225194010');
  });

  test('shows the municipality next to the cadastral area when they differ', async ({ page }) => {
    await openMap(page);

    await searchBox(page).fill('867/121');

    await expect(page.getByRole('option', { name: /Parcela 867\/121/ })).toContainText('Robousy (740225), Jičín');
  });

  test('moves the active option with the arrow keys, wrapping around, and picks it with Enter', async ({ page }) => {
    await stubSearch(page, 200, {
      places: [],
      parcels: [parcelBody(1, '5'), parcelBody(2, '6'), parcelBody(1753295604, '7')],
    });
    await openMap(page);
    const input = searchBox(page);
    await input.fill('5');
    const options = page.getByRole('option');
    await expect(options).toHaveCount(3);
    await expect(input).toHaveAttribute('aria-expanded', 'true');
    await expect(input).not.toHaveAttribute('aria-activedescendant', /.+/);

    await input.press('ArrowDown');
    await expect(options.nth(0)).toHaveAttribute('aria-selected', 'true');
    await expect(input).toHaveAttribute('aria-activedescendant', (await options.nth(0).getAttribute('id')) ?? '');
    await input.press('ArrowDown');
    await expect(options.nth(1)).toHaveAttribute('aria-selected', 'true');
    await expect(options.nth(0)).toHaveAttribute('aria-selected', 'false');
    await input.press('ArrowUp');
    await input.press('ArrowUp');
    await expect(options.nth(2)).toHaveAttribute('aria-selected', 'true');
    await input.press('ArrowDown');
    await expect(options.nth(0)).toHaveAttribute('aria-selected', 'true');
    await input.press('ArrowDown');
    await input.press('ArrowDown');
    await input.press('Enter');

    await expect.poll(() => new URL(page.url()).hash).toBe('#parcel=1753295604');
  });

  test('picks the first option with Enter when none is active', async ({ page }) => {
    await stubSearch(page, 200, { places: [], parcels: [parcelBody(1753295604, '7'), parcelBody(2, '8')] });
    await openMap(page);

    await searchBox(page).fill('7');
    await expect(page.getByRole('option')).toHaveCount(2);
    await searchBox(page).press('Enter');

    await expect.poll(() => new URL(page.url()).hash).toBe('#parcel=1753295604');
  });

  test('closes the list with Escape and leaves an open panel open', async ({ page }) => {
    await page.goto('/#parcel=1753295604');
    await expect(panelOf(page).getByRole('heading')).toHaveText('Parcela st. 72');
    await searchBox(page).fill('1171');
    await expect(page.getByRole('option')).toHaveCount(1);

    await searchBox(page).press('Escape');

    await expect(page.getByRole('listbox')).toBeHidden();
    await expect(searchBox(page)).toHaveAttribute('aria-expanded', 'false');
    await expect(panelOf(page)).toBeVisible();

    await searchBox(page).press('Escape');

    await expect(panelOf(page)).toBeHidden();
  });

  test('says so when nothing is found', async ({ page }) => {
    await openMap(page);

    await searchBox(page).fill('999999999');
    await searchBox(page).fill('9999');

    await expect(page.getByRole('status')).toHaveText('Nic nenalezeno. Mapa pokrývá okres Jičín.');
    await expect(page.getByRole('option')).toHaveCount(0);
  });

  test('says so when the search fails', async ({ page, allowedConsoleErrors }) => {
    allowedConsoleErrors.push(/status of 500/);
    await stubSearch(page, 500, { error: 'internal_error' });
    await openMap(page);

    await searchBox(page).fill('st. 72');

    await expect(page.getByRole('status')).toHaveText('Vyhledávání se nezdařilo. Zkuste to prosím znovu.');
  });

  test('tells to reload when the data of the page is outdated', async ({ page, allowedConsoleErrors }) => {
    allowedConsoleErrors.push(/status of 404/);
    await stubSearch(page, 404, { error: 'not_found' });
    await openMap(page);

    await searchBox(page).fill('st. 72');

    await expect(page.getByRole('status')).toHaveText('Data byla aktualizována. Načtěte stránku znovu.');
  });

  test('searches nothing for a text that is too short and closes an open list', async ({ page }) => {
    await openMap(page);
    await searchBox(page).fill('st. 72');
    await expect(page.getByRole('option')).toHaveCount(1);
    const requests: string[] = [];
    page.on('request', (request) => {
      if (request.url().includes('/api/search/')) requests.push(request.url());
    });

    await searchBox(page).fill('s');
    await page.waitForTimeout(500);

    expect(requests).toEqual([]);
    await expect(page.getByRole('listbox')).toBeHidden();
  });

  test('sends one request per pause in typing and requests no tile while typing', async ({ page, offlineTiles }) => {
    await openMap(page);
    await page.waitForLoadState('networkidle');
    const tilesBefore = offlineTiles.requested().length;
    const requests: string[] = [];
    page.on('request', (request) => {
      const address = request.url();
      if (address.includes('/api/search/') || address.includes('/tiles/parcels/')) requests.push(address);
    });

    await searchBox(page).pressSequentially('st. 72', { delay: 40 });
    await expect(page.getByRole('option')).toHaveCount(1);
    await page.waitForTimeout(500);

    expect(requests).toHaveLength(1);
    expect(requests[0]).toContain(`${SEARCH_URL}?q=st.%2072&near=`);
    expect(offlineTiles.requested()).toHaveLength(tilesBefore);
  });

  test('sends the map centre as near', async ({ page }) => {
    await openMap(page);
    const request = page.waitForRequest((candidate) => candidate.url().includes('/api/search/'));

    await searchBox(page).fill('st. 72');

    expect((await request).url()).toMatch(/&near=15\.352,50\.437$/);
  });

  test('announces itself as a combobox with a list of results', async ({ page }) => {
    await openMap(page);
    const input = searchBox(page);

    await expect(input).toHaveAttribute('aria-autocomplete', 'list');
    await expect(input).toHaveAttribute('aria-expanded', 'false');
    await expect(input).toHaveAttribute('aria-controls', 'map-search-list');
    await expect(page.getByRole('search', { name: 'Hledat na mapě' })).toBeVisible();
    await expect(input).toHaveAttribute('placeholder', 'Adresa, místo nebo číslo parcely');
  });

  test('renders texts as text, never as markup', async ({ page }) => {
    await stubSearch(page, 200, {
      places: [],
      parcels: [
        { ...parcelBody(3, '<img src=x onerror=alert(1)>'), cadastralArea: { code: 659541, name: '<b>x</b>' } },
      ],
    });
    await openMap(page);

    await searchBox(page).fill('3');

    await expect(page.getByRole('option')).toContainText('<b>x</b>');
    await expect(page.locator('.map-search-list b, .map-search-list img')).toHaveCount(0);
  });

  test('switches to English and re-renders the placeholder and an open list', async ({ page }) => {
    await openMap(page);
    await searchBox(page).fill('st. 72');
    await expect(page.getByRole('option', { name: /Parcela st\. 72/ })).toBeVisible();

    await page.getByRole('button', { name: 'English' }).click();

    const input = page.getByRole('combobox', { name: 'Search the map' });
    await expect(input).toHaveAttribute('placeholder', 'Address, place or parcel number');
    await expect(page.getByRole('option', { name: /Parcel st\. 72/ })).toBeVisible();
    await expect(page.getByRole('group', { name: 'Parcels' })).toBeVisible();
    await expect(page.getByRole('listbox', { name: 'Search results' })).toBeVisible();
  });

  test('is not shown without a search URL', async ({ page }) => {
    await page.route('**/api/map-config', async (route) => {
      const response = await route.fetch();
      const body = (await response.json()) as { parcels: Record<string, unknown> };
      await route.fulfill({ response, json: { ...body, parcels: { ...body.parcels, searchUrl: null } } });
    });

    await page.goto('/');

    await expect(page.locator('.maplibregl-canvas')).toBeVisible();
    await expect(page.locator('.map-search')).toHaveCount(0);
    await expect(page.locator('.maplibregl-ctrl-attrib')).toBeVisible();
  });
});

test.describe('searching places and addresses', () => {
  test('shows the municipality first with its kind, and moves the map there closing an open panel', async ({
    page,
    offlineTiles,
  }) => {
    await page.goto('/#parcel=1753295604');
    await expect(panelOf(page).getByRole('heading')).toHaveText('Parcela st. 72');
    await page.waitForLoadState('networkidle');
    const tilesBefore = offlineTiles.requested().length;

    await searchBox(page).fill('jicin');

    const first = page.getByRole('option').first();
    await expect(first.locator('.map-search-option-name')).toHaveText('Jičín');
    await expect(first.locator('.map-search-option-context')).toHaveText('Obec');
    await expect(page.getByRole('group', { name: 'Místa' })).toBeVisible();
    await first.click();

    await expect(panelOf(page)).toBeHidden();
    await expect.poll(() => new URL(page.url()).hash).toBe('');
    await expect.poll(() => offlineTiles.requested().length).toBeGreaterThan(tilesBefore);
  });

  test('opens the parcel of an address', async ({ page }) => {
    await page.goto('/');
    await expect(searchBox(page)).toBeVisible();

    await searchBox(page).fill('Valdštejnovo náměstí 58');
    const option = page.getByRole('option', { name: /Valdštejnovo náměstí 58/ });
    await expect(option).toContainText('Adresa · Staré Město, Jičín');
    await searchBox(page).press('Enter');

    await expect(panelOf(page).getByRole('heading')).toHaveText('Parcela st. 72');
    await expect.poll(() => new URL(page.url()).hash).toBe('#parcel=1753295604');
  });

  test('opens the parcel of an address of a cadastral area without a street', async ({ page }) => {
    await page.goto('/');
    await expect(searchBox(page)).toBeVisible();

    await searchBox(page).fill('robousy 239');
    await page.getByRole('option', { name: /Robousy 239/ }).click();

    await expect.poll(() => new URL(page.url()).hash).toBe('#parcel=95522753010');
  });

  test('shows a part of a municipality with the municipality as context', async ({ page }) => {
    await page.goto('/');
    await expect(searchBox(page)).toBeVisible();

    await searchBox(page).fill('stare');

    const first = page.getByRole('option').first();
    await expect(first.locator('.map-search-option-name')).toHaveText('Staré Město');
    await expect(first.locator('.map-search-option-context')).toHaveText('Část obce · Jičín');
  });

  test('names the kinds in English after a language switch', async ({ page }) => {
    await page.goto('/');
    await expect(searchBox(page)).toBeVisible();
    await searchBox(page).fill('stare');
    await expect(page.getByRole('option').first()).toBeVisible();

    await page.getByRole('button', { name: 'English' }).click();

    await expect(page.getByRole('option').first().locator('.map-search-option-context')).toHaveText(
      'Part of a municipality · Jičín',
    );
    await expect(page.getByRole('group', { name: 'Places' })).toBeVisible();
  });

  test('eases to the centre of a place without bounds at zoom 17', async ({ page, offlineTiles }) => {
    await stubSearch(page, 200, {
      places: [
        {
          kind: 'municipalityPart',
          code: 1,
          name: 'Podhrad',
          context: 'Jičín',
          center: { longitude: 15.4, latitude: 50.4 },
          bounds: null,
          parcelId: null,
        },
      ],
      parcels: [],
    });
    await openMap(page);

    await searchBox(page).fill('podhrad');
    await page.getByRole('option', { name: /Podhrad/ }).click();

    await expect.poll(() => offlineTiles.requested().some((tile) => tile.startsWith('17/'))).toBe(true);
  });

  test('does not select a parcel for an address without one', async ({ page }) => {
    await stubSearch(page, 200, {
      places: [
        {
          kind: 'address',
          code: 5,
          name: 'Nikde 1',
          context: 'Jičín',
          center: { longitude: 15.35, latitude: 50.43 },
          bounds: null,
          parcelId: null,
        },
      ],
      parcels: [],
    });
    await openMap(page);

    await searchBox(page).fill('nikde');
    await page.getByRole('option', { name: /Nikde 1/ }).click();

    await expect(panelOf(page)).toBeHidden();
    expect(new URL(page.url()).hash).toBe('');
  });
});
