import { useEffect, useId, useRef, useState } from 'react';

let addressDataPromise;

function normalizeAddress(value) {
  return String(value || '').trim().replace(/\s+/g, ' ').toLocaleLowerCase('en');
}

function loadAddressData() {
  if (!addressDataPromise) {
    addressDataPromise = fetch(`${import.meta.env.BASE_URL}data/psgc-addresses.json`)
      .then((response) => {
        if (!response.ok) throw new Error(`Address list returned HTTP ${response.status}`);
        return response.json();
      })
      .then((source) => {
        const areaByCode = new Map();
        const addressIndex = new Map();
        for (const area of source.areas) {
          const cityByCode = new Map();
          areaByCode.set(area.code, { ...area, cityByCode });
          for (const city of area.cities) {
            const barangayByCode = new Map();
            cityByCode.set(city.code, { ...city, barangayByCode });
            for (const barangay of city.barangays) {
              barangayByCode.set(barangay.code, barangay);
              const address = `${barangay.name}, ${city.name}, ${area.name}`;
              addressIndex.set(normalizeAddress(address), {
                areaCode: area.code,
                cityCode: city.code,
                barangayCode: barangay.code,
              });
            }
          }
        }
        return { ...source, areaByCode, addressIndex };
      })
      .catch((error) => {
        addressDataPromise = null;
        throw error;
      });
  }
  return addressDataPromise;
}

const emptySelection = { areaCode: '', cityCode: '', barangayCode: '' };

function AddressDropdown({ label, placeholder, options = [], value, onChange, disabled, className, wide = false }) {
  const id = useId();
  const root = useRef(null);
  const trigger = useRef(null);
  const searchInput = useRef(null);
  const [open, setOpen] = useState(false);
  const [search, setSearch] = useState('');
  const [activeIndex, setActiveIndex] = useState(0);
  const selected = options.find((option) => option.code === value);
  const filtered = options.filter((option) => normalizeAddress(option.name).includes(normalizeAddress(search)));

  useEffect(() => {
    if (!open) return;
    searchInput.current?.focus();
    const closeOutside = (event) => {
      if (!root.current?.contains(event.target)) setOpen(false);
    };
    document.addEventListener('pointerdown', closeOutside);
    return () => document.removeEventListener('pointerdown', closeOutside);
  }, [open]);

  useEffect(() => {
    if (open) root.current?.querySelector(`[data-option-index="${activeIndex}"]`)?.scrollIntoView({ block: 'nearest' });
  }, [activeIndex, open]);

  const show = () => {
    if (disabled) return;
    setSearch(''); setActiveIndex(Math.max(0, options.findIndex((option) => option.code === value))); setOpen(true);
  };
  const choose = (option) => {
    onChange(option.code); setOpen(false); setSearch(''); trigger.current?.focus();
  };
  const onKeyDown = (event) => {
    if (event.key === 'Escape') {
      event.preventDefault(); setOpen(false); trigger.current?.focus();
    } else if (event.key === 'Tab') {
      setOpen(false);
    } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault();
      setActiveIndex((current) => Math.max(0, Math.min(filtered.length - 1, current + (event.key === 'ArrowDown' ? 1 : -1))));
    } else if (event.key === 'Enter') {
      event.preventDefault();
      if (filtered[activeIndex]) choose(filtered[activeIndex]);
    }
  };

  return <div ref={root} onBlur={(event) => { if (!event.currentTarget.contains(event.relatedTarget)) setOpen(false); }} className={`relative min-w-0 ${wide ? 'sm:col-span-2' : ''}`}>
    <span id={`${id}-label`} className="mb-2 block text-sm font-bold">{label}</span>
    <button ref={trigger} type="button" disabled={disabled} aria-labelledby={`${id}-label ${id}-value`}
      aria-haspopup="listbox" aria-expanded={open && !disabled} aria-controls={open ? `${id}-list` : undefined}
      onClick={() => open ? setOpen(false) : show()}
      onKeyDown={(event) => { if (event.key === 'ArrowDown' || event.key === 'ArrowUp') { event.preventDefault(); show(); } }}
      className={`${className} flex min-w-0 items-center justify-between gap-3 text-left`}>
      <span id={`${id}-value`} className="truncate">{selected?.name || placeholder}</span><span aria-hidden="true" className="text-xs text-slate-500">▾</span>
    </button>
    {open && !disabled && <div className="absolute left-0 right-0 top-full z-50 mt-1 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl dark:border-slate-600 dark:bg-slate-900">
      <div className="border-b border-slate-100 p-2 dark:border-slate-700"><input ref={searchInput} type="search" role="combobox"
        aria-label={`Search ${label}`} aria-autocomplete="list" aria-expanded="true" aria-controls={`${id}-list`}
        aria-activedescendant={filtered[activeIndex] ? `${id}-option-${activeIndex}` : undefined}
        value={search} onChange={(event) => { setSearch(event.target.value); setActiveIndex(0); }} onKeyDown={onKeyDown}
        placeholder={`Search ${label.toLowerCase()}...`} className="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-900 outline-none focus:border-[#7D001D] dark:border-slate-600 dark:bg-slate-800 dark:text-white" /></div>
      <div id={`${id}-list`} role="listbox" aria-labelledby={`${id}-label`} className="max-h-48 overflow-y-auto overscroll-contain p-1">
        {filtered.map((option, index) => <button key={option.code} id={`${id}-option-${index}`} data-option-index={index}
          type="button" role="option" aria-selected={option.code === value} tabIndex={-1}
          onMouseDown={(event) => event.preventDefault()} onMouseEnter={() => setActiveIndex(index)} onClick={() => choose(option)}
          className={`block w-full rounded-lg px-3 py-2 text-left text-sm ${index === activeIndex ? 'bg-[#7D001D]/10 text-[#7D001D] dark:bg-rose-950/50 dark:text-rose-200' : 'text-slate-700 dark:text-slate-200'} ${option.code === value ? 'font-bold' : ''}`}>
          {option.name}{option.code === value && <span aria-hidden="true" className="float-right ml-2">✓</span>}
        </button>)}
        {!filtered.length && <p role="status" className="px-3 py-4 text-sm text-slate-500">No matches found.</p>}
      </div>
    </div>}
  </div>;
}

export default function PsgcAddressSelect({ value, onChange, onValidityChange, className = '' }) {
  const [data, setData] = useState(null);
  const [selection, setSelection] = useState(emptySelection);
  const [error, setError] = useState(false);
  const [retryCount, setRetryCount] = useState(0);
  const lastEmitted = useRef(null);

  useEffect(() => {
    let active = true;
    loadAddressData()
      .then((loaded) => {
        if (active) {
          setData(loaded);
          setError(false);
        }
      })
      .catch(() => {
        if (active) {
          setError(true);
          onValidityChange(false);
        }
      });
    return () => { active = false; };
  }, [retryCount, onValidityChange]);

  useEffect(() => {
    if (!data || value === lastEmitted.current) return;
    const saved = data.addressIndex.get(normalizeAddress(value));
    setSelection(saved || emptySelection);
    onValidityChange(Boolean(saved));
  }, [data, value, onValidityChange]);

  const selectedArea = data?.areaByCode.get(selection.areaCode);
  const selectedCity = selectedArea?.cityByCode.get(selection.cityCode);

  const updateSelection = (next) => {
    setSelection(next);
    const area = data.areaByCode.get(next.areaCode);
    const city = area?.cityByCode.get(next.cityCode);
    const barangay = city?.barangayByCode.get(next.barangayCode);
    const address = barangay ? `${barangay.name}, ${city.name}, ${area.name}` : '';
    lastEmitted.current = address;
    onChange(address);
    onValidityChange(Boolean(barangay));
  };

  return (
    <div className="space-y-3">
      <div className="grid gap-3 sm:grid-cols-2">
        <AddressDropdown label="Province / Area" placeholder={data ? 'Select province or area' : 'Loading provinces...'}
          options={data?.areas} value={selection.areaCode} disabled={!data} className={className}
          onChange={(areaCode) => updateSelection({ areaCode, cityCode: '', barangayCode: '' })} />
        <AddressDropdown key={`city-${selection.areaCode}`} label="Municipality / City" placeholder="Select municipality or city"
          options={selectedArea?.cities} value={selection.cityCode} disabled={!selectedArea} className={className}
          onChange={(cityCode) => updateSelection({ ...selection, cityCode, barangayCode: '' })} />
        <AddressDropdown key={`barangay-${selection.areaCode}-${selection.cityCode}`} label="Barangay" placeholder="Select barangay" wide
          options={selectedCity?.barangays} value={selection.barangayCode} disabled={!selectedCity} className={className}
          onChange={(barangayCode) => updateSelection({ ...selection, barangayCode })} />
      </div>
      {error && <p role="alert" className="text-sm text-rose-600">Unable to load address choices. <button type="button" onClick={() => { setError(false); setRetryCount((count) => count + 1); }} className="font-bold underline">Try again</button></p>}
      {value && selection.barangayCode && <p className="text-xs text-slate-500">Selected address: {value}</p>}
      {value && !selection.barangayCode && data && <p className="text-xs text-slate-500">Current address: {value}. Select a province, municipality/city, and barangay to update it.</p>}
      <p className="text-xs text-slate-500">For a city without a province, choose its region under Province / Area.</p>
    </div>
  );
}
