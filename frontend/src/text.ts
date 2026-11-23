/** The language of the interface; a language switch will replace the strings below, not the components. */
export const UI_LOCALE = 'en';

export const TEXT = {
  panelLabel: 'Parcel details',
  heading: (parcelNumber: string): string => `Parcel ${parcelNumber}`,
  closeLabel: 'Close parcel details',
  cadastralArea: 'Cadastral area',
  municipality: 'Municipality',
  plotType: 'Plot type',
  landType: 'Land type',
  landUse: 'Land use',
  area: 'Area',
  viewerLink: 'Show owners in the ČÚZK cadastre viewer',
  buildingPlot: 'Building plot',
  landPlot: 'Land plot',
  loading: 'Loading parcel details…',
  notFound: 'This parcel was not found. If the data has been updated, reload the page.',
  error: 'The parcel details could not be loaded. Please try again.',
  unknownLandType: (code: number): string => `Unknown land type (code ${String(code)})`,
  unknownLandUse: (code: number): string => `Unknown land use (code ${String(code)})`,
} as const;
