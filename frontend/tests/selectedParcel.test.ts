import { describe, expect, it } from 'vitest';
import type { ParcelDetail } from '../src/parcelDetail';
import { SELECTED_PARCEL_SOURCE, selectedParcelData } from '../src/selectedParcel';

const detail: ParcelDetail = {
  id: 1753295604,
  parcelNumber: 'st. 72',
  kind: 'building',
  cadastralArea: { code: 659541, name: 'Jičín' },
  municipality: { code: 572659, name: 'Jičín' },
  landTypeCode: 13,
  landUseCode: null,
  areaSquareMetres: 455,
  cadastreViewerUrl: 'https://nahlizenidokn.cuzk.gov.cz/ZobrazObjekt.aspx?typ=parcela&id=1753295604',
  bounds: { west: 15.0, south: 50.0, east: 15.1, north: 50.1 },
  boundary: {
    type: 'Polygon',
    coordinates: [
      [
        [15.0, 50.0],
        [15.1, 50.0],
        [15.1, 50.1],
        [15.0, 50.0],
      ],
    ],
  },
};

describe('selectedParcelData', () => {
  it('is an empty collection when nothing is selected', () => {
    expect(selectedParcelData(null)).toEqual({ type: 'FeatureCollection', features: [] });
  });

  it('is one polygon feature with the parcel id and the boundary', () => {
    expect(selectedParcelData(detail)).toEqual({
      type: 'FeatureCollection',
      features: [{ type: 'Feature', id: 1753295604, properties: {}, geometry: detail.boundary }],
    });
  });

  it('names the map source', () => {
    expect(SELECTED_PARCEL_SOURCE).toBe('selected-parcel');
  });
});
