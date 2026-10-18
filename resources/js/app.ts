import { bootSite } from './site/boot';

// The calculator and the tools (three.js, most of the code) come in a chunk of their own: the small entry lets the
// first screen paint without them (docs/AH.md §11, Core Web Vitals). The chunk is fetched at once and booted when the
// document is ready; mpCalcReady resolves when the tools have booted (scripts/check_bundle.mjs).
const calc = import('./calc/boot');
const ready = new Promise<void>((resolve) => document.addEventListener('DOMContentLoaded', () => resolve()));
(window as unknown as { mpCalcReady?: Promise<void> }).mpCalcReady = Promise.all([calc, ready]).then(([m]) => m.bootCalc());

document.addEventListener('DOMContentLoaded', () => { bootSite(); });
