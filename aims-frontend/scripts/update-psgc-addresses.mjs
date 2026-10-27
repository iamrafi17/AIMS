import { mkdir, writeFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';

// MIT-licensed PSGC data: https://github.com/bendlikeabamboo/barangay
// Its source is the Philippine Statistics Authority PSGC masterlist.
const sourceBase = 'https://raw.githubusercontent.com/bendlikeabamboo/barangay/main/barangay/data';
const output = fileURLToPath(new URL('../public/data/psgc-addresses.json', import.meta.url));
const cityTypes = new Set(['municipality', 'component_city', 'independent_component_city', 'highly_urbanized_city']);
const areaTypes = new Set(['province', 'special_geographic_area', 'region']);

async function download(name) {
  const response = await fetch(`${sourceBase}/${name}`);
  if (!response.ok) throw new Error(`Could not download ${name}: HTTP ${response.status}`);
  return name.endsWith('.json') ? response.json() : (await response.text()).trim();
}

const [version, records] = await Promise.all([
  download('CURRENT_VERSION'),
  download('barangay_flat.json'),
]);
const byCode = new Map(records.map((record) => [record.psgc_id, record]));
const areas = new Map();
const cities = new Map();

function ancestor(record, acceptedTypes) {
  let current = record;
  while (current && !acceptedTypes.has(current.type)) {
    current = byCode.get(current.parent_psgc_id);
  }
  return current;
}

for (const record of records) {
  if (record.type !== 'barangay') continue;

  const city = ancestor(byCode.get(record.parent_psgc_id), cityTypes);
  const area = city && ancestor(byCode.get(city.parent_psgc_id), areaTypes);
  if (!city || !area) throw new Error(`Missing PSGC ancestor for barangay ${record.psgc_id}`);

  if (!areas.has(area.psgc_id)) {
    areas.set(area.psgc_id, { code: area.psgc_id, name: area.name.trim(), type: area.type, cities: [] });
  }
  if (!cities.has(city.psgc_id)) {
    const entry = { code: city.psgc_id, name: city.name.trim(), barangays: [] };
    areas.get(area.psgc_id).cities.push(entry);
    cities.set(city.psgc_id, entry);
  }

  const parent = byCode.get(record.parent_psgc_id);
  const name = parent?.type === 'submunicipality'
    ? `${record.name.trim()} (${parent.name.trim()})`
    : record.name.trim();
  cities.get(city.psgc_id).barangays.push({ code: record.psgc_id, name });
}

const sortByName = (a, b) => a.name.localeCompare(b.name, 'en');
const sortedAreas = [...areas.values()].sort(sortByName);
for (const area of sortedAreas) {
  area.cities.sort(sortByName);
  for (const city of area.cities) city.barangays.sort(sortByName);
}

const barangayCount = sortedAreas.reduce(
  (count, area) => count + area.cities.reduce((cityCount, city) => cityCount + city.barangays.length, 0),
  0,
);
const sourceBarangayCount = records.filter((record) => record.type === 'barangay').length;
if (barangayCount !== sourceBarangayCount) {
  throw new Error(`Expected ${sourceBarangayCount} barangays, found ${barangayCount}`);
}

await mkdir(fileURLToPath(new URL('../public/data/', import.meta.url)), { recursive: true });
await writeFile(output, JSON.stringify({ version, areas: sortedAreas }));
console.log(`Saved ${sortedAreas.length} areas, ${cities.size} cities/municipalities, and ${barangayCount} barangays from PSGC ${version}.`);
