// JavaScript budget of the map page (ADR-0006, E3.1): every script the page loads, gzip-compressed.
// Run after `npm run build`: node scripts/check-map-budget.mjs
import { readFileSync } from 'node:fs';
import { gzipSync } from 'node:zlib';

const BUDGET_KB = 600;
const ENTRY = 'resources/js/map.ts';

const manifest = JSON.parse(readFileSync('public/build/manifest.json', 'utf8'));
const files = new Set();

function collect(key) {
    const chunk = manifest[key];

    files.add(`public/build/${chunk.file}`);
    (chunk.imports ?? []).forEach(collect);
    (chunk.assets ?? []).filter((asset) => /\.m?js$/.test(asset)).forEach((asset) => files.add(`public/build/${asset}`));
}

collect(ENTRY);
files.add('vendor/livewire/livewire/dist/livewire.min.js');

let total = 0;

for (const file of files) {
    const size = gzipSync(readFileSync(file), { level: 9 }).length / 1024;
    total += size;
    console.log(`${size.toFixed(1).padStart(7)} KB  ${file}`);
}

console.log(`${total.toFixed(1).padStart(7)} KB  total, budget ${BUDGET_KB} KB`);

if (total > BUDGET_KB) {
    console.error(`The map page loads ${total.toFixed(1)} KB of JavaScript, over the ${BUDGET_KB} KB budget.`);
    process.exit(1);
}
