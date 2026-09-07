import {
  IonButton,
  IonInput,
  IonItem,
  IonLabel,
  IonList,
  IonSelect,
  IonSelectOption,
  IonSpinner,
  IonText,
  IonTextarea,
} from '@ionic/react';
import { useEffect, useState } from 'react';
import {
  listBarangays,
  listProvinceCitiesMunicipalities,
  listProvinces,
  listRegionCitiesMunicipalities,
  listRegions,
  type PsgcItem,
} from '../../api/psgc';
import type { CivilStatus, MemberProfile, Sex, StoreMemberProfilePayload } from '../../api/types';

const CIVIL_STATUS_OPTIONS: { value: CivilStatus; label: string }[] = [
  { value: 'single', label: 'Single' },
  { value: 'married', label: 'Married' },
  { value: 'widowed', label: 'Widowed' },
  { value: 'separated', label: 'Separated' },
  { value: 'divorced', label: 'Divorced' },
];

const SEX_OPTIONS: { value: Sex; label: string }[] = [
  { value: 'male', label: 'Male' },
  { value: 'female', label: 'Female' },
];

const DEFAULT_REGION = 'Caraga';
const DEFAULT_PROVINCE = 'Agusan del Sur';

interface PersonalInformationFormProps {
  initial?: MemberProfile | null;
  contactFallback?: string | null;
  submitting: boolean;
  error: string | null;
  fieldErrors: Record<string, string[]>;
  onBack?: () => void;
  onSubmit: (payload: StoreMemberProfilePayload) => void;
}

function sortByName(items: PsgcItem[]): PsgcItem[] {
  return [...items].sort((a, b) => a.name.localeCompare(b.name));
}

const PersonalInformationForm: React.FC<PersonalInformationFormProps> = ({
  initial,
  contactFallback,
  submitting,
  error,
  fieldErrors,
  onBack,
  onSubmit,
}) => {
  const [psgcOnline, setPsgcOnline] = useState(true);
  const [loadingGeo, setLoadingGeo] = useState(true);
  const [regions, setRegions] = useState<PsgcItem[]>([]);
  const [provinces, setProvinces] = useState<PsgcItem[]>([]);
  const [cities, setCities] = useState<PsgcItem[]>([]);
  const [barangays, setBarangays] = useState<PsgcItem[]>([]);
  const [regionCode, setRegionCode] = useState(initial?.region_code ?? '');
  const [regionName, setRegionName] = useState(initial?.region_name ?? '');
  const [provinceCode, setProvinceCode] = useState(initial?.province_code ?? '');
  const [provinceName, setProvinceName] = useState(initial?.province_name ?? '');
  const [cityCode, setCityCode] = useState(initial?.city_municipality_code ?? '');
  const [cityName, setCityName] = useState(initial?.city_municipality_name ?? '');
  const [barangayCode, setBarangayCode] = useState(initial?.barangay_code ?? '');
  const [barangayName, setBarangayName] = useState(initial?.barangay_name ?? '');
  const [street, setStreet] = useState(initial?.street ?? '');
  const [sitio, setSitio] = useState(initial?.sitio ?? '');
  const [civilStatus, setCivilStatus] = useState<CivilStatus | ''>(initial?.civil_status ?? '');
  const [sex, setSex] = useState<Sex | ''>(initial?.sex ?? '');
  const [contactNo, setContactNo] = useState(initial?.contact_no ?? contactFallback ?? '');
  const [seminarDate, setSeminarDate] = useState(initial?.date_of_seminar ?? '');
  const [remarks, setRemarks] = useState(initial?.remarks ?? '');
  const [localError, setLocalError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    (async () => {
      try {
        const nextRegions = sortByName(await listRegions());
        if (cancelled) {
          return;
        }
        setRegions(nextRegions);
        setPsgcOnline(true);

        const preferred =
          nextRegions.find((r) => r.code === initial?.region_code) ??
          nextRegions.find((r) => r.name === DEFAULT_REGION || r.regionName === 'Region XIII');
        if (preferred && !initial?.region_code) {
          setRegionCode(preferred.code);
          setRegionName(preferred.name);
        } else if (preferred && initial?.region_code) {
          setRegionCode(preferred.code);
          setRegionName(preferred.name);
        }
      } catch {
        if (!cancelled) {
          setPsgcOnline(false);
        }
      } finally {
        if (!cancelled) {
          setLoadingGeo(false);
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [initial?.region_code]);

  useEffect(() => {
    if (!psgcOnline || !regionCode) {
      return;
    }

    let cancelled = false;
    setProvinces([]);
    setCities([]);
    setBarangays([]);

    (async () => {
      try {
        const nextProvinces = sortByName(await listProvinces(regionCode));
        if (cancelled) {
          return;
        }
        setProvinces(nextProvinces);

        if (nextProvinces.length === 0) {
          const nextCities = sortByName(await listRegionCitiesMunicipalities(regionCode));
          if (cancelled) {
            return;
          }
          setCities(nextCities);
          setProvinceCode('');
          setProvinceName('');
          return;
        }

        const preferred =
          nextProvinces.find((p) => p.code === initial?.province_code) ??
          nextProvinces.find((p) => p.name === DEFAULT_PROVINCE);
        if (preferred && (!provinceCode || provinceCode === initial?.province_code)) {
          setProvinceCode(preferred.code);
          setProvinceName(preferred.name);
        }
      } catch {
        if (!cancelled) {
          setPsgcOnline(false);
        }
      }
    })();

    return () => {
      cancelled = true;
    };
    // provinceCode is intentionally omitted so region change reloads children.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [psgcOnline, regionCode]);

  useEffect(() => {
    if (!psgcOnline || !provinceCode) {
      return;
    }

    let cancelled = false;
    setCities([]);
    setBarangays([]);

    (async () => {
      try {
        const nextCities = sortByName(await listProvinceCitiesMunicipalities(provinceCode));
        if (cancelled) {
          return;
        }
        setCities(nextCities);
        if (initial?.city_municipality_code) {
          const match = nextCities.find((c) => c.code === initial.city_municipality_code);
          if (match) {
            setCityCode(match.code);
            setCityName(match.name);
          }
        }
      } catch {
        if (!cancelled) {
          setPsgcOnline(false);
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [psgcOnline, provinceCode, initial?.city_municipality_code]);

  useEffect(() => {
    if (!psgcOnline || !cityCode) {
      return;
    }

    let cancelled = false;
    setBarangays([]);

    (async () => {
      try {
        const nextBarangays = sortByName(await listBarangays(cityCode));
        if (cancelled) {
          return;
        }
        setBarangays(nextBarangays);
        if (initial?.barangay_code) {
          const match = nextBarangays.find((b) => b.code === initial.barangay_code);
          if (match) {
            setBarangayCode(match.code);
            setBarangayName(match.name);
          }
        }
      } catch {
        if (!cancelled) {
          setPsgcOnline(false);
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [psgcOnline, cityCode, initial?.barangay_code]);

  const onPickRegion = (code: string) => {
    const match = regions.find((r) => r.code === code);
    setRegionCode(code);
    setRegionName(match?.name ?? '');
    setProvinceCode('');
    setProvinceName('');
    setCityCode('');
    setCityName('');
    setBarangayCode('');
    setBarangayName('');
  };

  const onPickProvince = (code: string) => {
    const match = provinces.find((p) => p.code === code);
    setProvinceCode(code);
    setProvinceName(match?.name ?? '');
    setCityCode('');
    setCityName('');
    setBarangayCode('');
    setBarangayName('');
  };

  const onPickCity = (code: string) => {
    const match = cities.find((c) => c.code === code);
    setCityCode(code);
    setCityName(match?.name ?? '');
    setBarangayCode('');
    setBarangayName('');
  };

  const onPickBarangay = (code: string) => {
    const match = barangays.find((b) => b.code === code);
    setBarangayCode(code);
    setBarangayName(match?.name ?? '');
  };

  const handleSubmit = () => {
    if (!regionName.trim() || !cityName.trim() || !barangayName.trim()) {
      setLocalError('Please complete region, municipality/city, and barangay.');
      return;
    }
    if (!civilStatus) {
      setLocalError('Please select civil status.');
      return;
    }
    if (!sex) {
      setLocalError('Please select sex.');
      return;
    }
    if (!contactNo.trim()) {
      setLocalError('Contact number is required.');
      return;
    }

    setLocalError(null);
    onSubmit({
      region_code: regionCode || null,
      region_name: regionName.trim(),
      province_code: provinceCode || null,
      province_name: provinceName.trim() || null,
      city_municipality_code: cityCode || null,
      city_municipality_name: cityName.trim(),
      barangay_code: barangayCode || null,
      barangay_name: barangayName.trim(),
      street: street.trim() || null,
      sitio: sitio.trim() || null,
      civil_status: civilStatus,
      sex,
      contact_no: contactNo.trim(),
      date_of_seminar: seminarDate.trim() || null,
      remarks: remarks.trim() || null,
    });
  };

  const geoSelect = psgcOnline && !loadingGeo;

  return (
    <>
      <h2 className="membership-step-title">Personal information</h2>
      <p className="membership-hint">
        From the ASELCO membership application. Address uses the Philippine Standard Geographic
        Code. Street and sitio are typed in when they are not in that directory.
      </p>
      {!psgcOnline && (
        <p className="membership-hint">
          Geographic lookup is unavailable. Enter region, province, municipality/city, and barangay
          manually.
        </p>
      )}
      {loadingGeo && psgcOnline && (
        <div className="membership-geo-loading">
          <IonSpinner name="crescent" />
          <span>Loading address directory…</span>
        </div>
      )}

      <IonList lines="full" className="auth-list">
        {geoSelect ? (
          <>
            <IonItem>
              <IonLabel position="stacked">Region *</IonLabel>
              <IonSelect
                interface="alert"
                placeholder="Select region"
                value={regionCode}
                onIonChange={(e) => onPickRegion(String(e.detail.value ?? ''))}
              >
                {regions.map((item) => (
                  <IonSelectOption key={item.code} value={item.code}>
                    {item.name}
                  </IonSelectOption>
                ))}
              </IonSelect>
            </IonItem>
            {provinces.length > 0 && (
              <IonItem>
                <IonLabel position="stacked">Province *</IonLabel>
                <IonSelect
                  interface="alert"
                  placeholder="Select province"
                  value={provinceCode}
                  onIonChange={(e) => onPickProvince(String(e.detail.value ?? ''))}
                >
                  {provinces.map((item) => (
                    <IonSelectOption key={item.code} value={item.code}>
                      {item.name}
                    </IonSelectOption>
                  ))}
                </IonSelect>
              </IonItem>
            )}
            <IonItem>
              <IonLabel position="stacked">Municipality / City *</IonLabel>
              <IonSelect
                interface="alert"
                placeholder="Select municipality or city"
                value={cityCode}
                disabled={!regionCode || (provinces.length > 0 && !provinceCode)}
                onIonChange={(e) => onPickCity(String(e.detail.value ?? ''))}
              >
                {cities.map((item) => (
                  <IonSelectOption key={item.code} value={item.code}>
                    {item.name}
                  </IonSelectOption>
                ))}
              </IonSelect>
            </IonItem>
            <IonItem>
              <IonLabel position="stacked">Barangay *</IonLabel>
              <IonSelect
                interface="alert"
                placeholder="Select barangay"
                value={barangayCode}
                disabled={!cityCode}
                onIonChange={(e) => onPickBarangay(String(e.detail.value ?? ''))}
              >
                {barangays.map((item) => (
                  <IonSelectOption key={item.code} value={item.code}>
                    {item.name}
                  </IonSelectOption>
                ))}
              </IonSelect>
            </IonItem>
          </>
        ) : (
          <>
            <IonItem>
              <IonLabel position="stacked">Region *</IonLabel>
              <IonInput
                value={regionName}
                placeholder="e.g. Caraga"
                onIonInput={(e) => setRegionName(e.detail.value ?? '')}
              />
            </IonItem>
            <IonItem>
              <IonLabel position="stacked">Province</IonLabel>
              <IonInput
                value={provinceName}
                placeholder="e.g. Agusan del Sur"
                onIonInput={(e) => setProvinceName(e.detail.value ?? '')}
              />
            </IonItem>
            <IonItem>
              <IonLabel position="stacked">Municipality / City *</IonLabel>
              <IonInput
                value={cityName}
                placeholder="e.g. San Francisco"
                onIonInput={(e) => setCityName(e.detail.value ?? '')}
              />
            </IonItem>
            <IonItem>
              <IonLabel position="stacked">Barangay *</IonLabel>
              <IonInput
                value={barangayName}
                placeholder="e.g. Poblacion"
                onIonInput={(e) => setBarangayName(e.detail.value ?? '')}
              />
            </IonItem>
          </>
        )}

        <IonItem>
          <IonLabel position="stacked">Street</IonLabel>
          <IonInput
            value={street}
            placeholder="House no. / street"
            onIonInput={(e) => setStreet(e.detail.value ?? '')}
          />
        </IonItem>
        <IonItem>
          <IonLabel position="stacked">Sitio</IonLabel>
          <IonInput
            value={sitio}
            placeholder="Sitio / purok"
            onIonInput={(e) => setSitio(e.detail.value ?? '')}
          />
        </IonItem>
        <IonItem>
          <IonLabel position="stacked">Civil status *</IonLabel>
          <IonSelect
            interface="alert"
            placeholder="Select civil status"
            value={civilStatus}
            onIonChange={(e) => setCivilStatus((e.detail.value ?? '') as CivilStatus)}
          >
            {CIVIL_STATUS_OPTIONS.map((opt) => (
              <IonSelectOption key={opt.value} value={opt.value}>
                {opt.label}
              </IonSelectOption>
            ))}
          </IonSelect>
        </IonItem>
        {fieldErrors.civil_status && (
          <IonText color="danger" className="auth-field-error">
            <p>{fieldErrors.civil_status[0]}</p>
          </IonText>
        )}
        <IonItem>
          <IonLabel position="stacked">Sex *</IonLabel>
          <IonSelect
            interface="alert"
            placeholder="Select sex"
            value={sex}
            onIonChange={(e) => setSex((e.detail.value ?? '') as Sex)}
          >
            {SEX_OPTIONS.map((opt) => (
              <IonSelectOption key={opt.value} value={opt.value}>
                {opt.label}
              </IonSelectOption>
            ))}
          </IonSelect>
        </IonItem>
        {fieldErrors.sex && (
          <IonText color="danger" className="auth-field-error">
            <p>{fieldErrors.sex[0]}</p>
          </IonText>
        )}
        <IonItem>
          <IonLabel position="stacked">Contact # *</IonLabel>
          <IonInput
            type="tel"
            inputmode="tel"
            value={contactNo}
            placeholder="09XXXXXXXXX"
            onIonInput={(e) => setContactNo(e.detail.value ?? '')}
          />
        </IonItem>
        {fieldErrors.contact_no && (
          <IonText color="danger" className="auth-field-error">
            <p>{fieldErrors.contact_no[0]}</p>
          </IonText>
        )}
        <IonItem>
          <IonLabel position="stacked">Date of seminar (optional)</IonLabel>
          <IonInput
            type="date"
            value={seminarDate}
            onIonInput={(e) => setSeminarDate(e.detail.value ?? '')}
          />
        </IonItem>
        <IonItem>
          <IonLabel position="stacked">Remarks</IonLabel>
          <IonTextarea
            autoGrow
            rows={2}
            value={remarks}
            placeholder="Optional notes"
            onIonInput={(e) => setRemarks(e.detail.value ?? '')}
          />
        </IonItem>
      </IonList>

      {(localError || error) && (
        <IonText color="danger" className="auth-error">
          <p>{localError || error}</p>
        </IonText>
      )}

      <div className={onBack ? 'membership-actions' : undefined}>
        {onBack && (
          <IonButton fill="outline" onClick={onBack} disabled={submitting}>
            Back
          </IonButton>
        )}
        <IonButton
          expand={onBack ? undefined : 'block'}
          className={onBack ? undefined : 'auth-submit'}
          onClick={handleSubmit}
          disabled={submitting || (psgcOnline && loadingGeo)}
        >
          {submitting ? <IonSpinner name="crescent" /> : 'Save & continue'}
        </IonButton>
      </div>
    </>
  );
};

export default PersonalInformationForm;
