export interface PsgcItem {
  code: string;
  name: string;
  regionName?: string;
  psgc10DigitCode?: string;
}

const PSGC_BASE = 'https://psgc.gitlab.io/api';

async function psgcGet<T>(path: string): Promise<T> {
  const res = await fetch(`${PSGC_BASE}${path}`, {
    headers: { Accept: 'application/json' },
  });
  if (!res.ok) {
    throw new Error('PSGC lookup failed.');
  }
  return (await res.json()) as T;
}

export function listRegions(): Promise<PsgcItem[]> {
  return psgcGet<PsgcItem[]>('/regions.json');
}

export function listProvinces(regionCode: string): Promise<PsgcItem[]> {
  return psgcGet<PsgcItem[]>(`/regions/${regionCode}/provinces.json`);
}

export function listProvinceCitiesMunicipalities(provinceCode: string): Promise<PsgcItem[]> {
  return psgcGet<PsgcItem[]>(`/provinces/${provinceCode}/cities-municipalities.json`);
}

export function listRegionCitiesMunicipalities(regionCode: string): Promise<PsgcItem[]> {
  return psgcGet<PsgcItem[]>(`/regions/${regionCode}/cities-municipalities.json`);
}

export function listBarangays(cityOrMunicipalityCode: string): Promise<PsgcItem[]> {
  return psgcGet<PsgcItem[]>(
    `/cities-municipalities/${cityOrMunicipalityCode}/barangays.json`,
  );
}
