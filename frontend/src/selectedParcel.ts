import type { FeatureCollection } from 'geojson';
import type { ParcelDetail } from './parcelDetail';

export const SELECTED_PARCEL_SOURCE = 'selected-parcel';

/** The one-feature collection of the selected parcel's boundary; empty when nothing is selected. */
export function selectedParcelData(detail: ParcelDetail | null): FeatureCollection {
  if (detail === null) {
    return { type: 'FeatureCollection', features: [] };
  }
  return {
    type: 'FeatureCollection',
    features: [{ type: 'Feature', id: detail.id, properties: {}, geometry: detail.boundary }],
  };
}
