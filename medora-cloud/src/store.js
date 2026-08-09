/**
 * Tiny persistent JSON store (zero dependencies).
 *
 * Fine for thousands of installs. Swap this module for Postgres/Redis when the
 * fleet grows — every other file talks to it only through the exported API.
 */
import fs from 'node:fs';
import path from 'node:path';

const FILE = process.env.MEDORA_DATA || path.join(process.cwd(), 'data', 'store.json');
const MAX_EVENTS = 500;

let data = { installs: {}, heartbeats: {}, licenses: {}, events: [] };

(function load() {
  try {
    data = JSON.parse(fs.readFileSync(FILE, 'utf8'));
  } catch { /* first run */ }
  data.installs ||= {};
  data.heartbeats ||= {};
  data.licenses ||= {};
  data.events ||= [];
})();

let saveTimer = null;
function persist() {
  clearTimeout(saveTimer);
  saveTimer = setTimeout(() => {
    try {
      fs.mkdirSync(path.dirname(FILE), { recursive: true });
      fs.writeFileSync(FILE, JSON.stringify(data));
    } catch (e) { console.error('store persist failed:', e.message); }
  }, 300);
}

export const store = {
  upsertInstall(domain, info) {
    const existing = data.installs[domain];
    data.installs[domain] = {
      ...existing,
      ...info,
      domain,
      first_seen: existing?.first_seen || new Date().toISOString(),
      last_seen: new Date().toISOString(),
    };
    persist();
    return !existing; // true if brand new
  },

  recordHeartbeat(domain, telemetry) {
    data.heartbeats[domain] = { ...telemetry, at: new Date().toISOString() };
    if (data.installs[domain]) {
      data.installs[domain].last_seen = new Date().toISOString();
    }
    persist();
  },

  getLicense(domain) {
    return data.licenses[domain] || null;
  },
  setLicense(domain, license) {
    data.licenses[domain] = { ...license, domain };
    persist();
  },

  pushEvent(evt) {
    const e = { ...evt, at: new Date().toISOString() };
    data.events.unshift(e);
    if (data.events.length > MAX_EVENTS) data.events.length = MAX_EVENTS;
    persist();
    return e;
  },

  totals() {
    const installs = Object.values(data.installs);
    const hbs = Object.values(data.heartbeats);
    const sum = (k) => hbs.reduce((n, h) => n + Number(h?.counts?.[k] || 0), 0);
    const countries = {};
    for (const i of installs) {
      const c = i.country || i.timezone || 'unknown';
      countries[c] = (countries[c] || 0) + 1;
    }
    return {
      installs: installs.length,
      active_licenses: Object.values(data.licenses).filter((l) => l.status === 'active').length,
      messages: sum('messages'),
      leads: sum('leads'),
      bookings: sum('bookings'),
      countries,
    };
  },

  installs() { return Object.values(data.installs).sort((a, b) => (b.last_seen || '').localeCompare(a.last_seen || '')); },
  events(limit = 100) { return data.events.slice(0, limit); },
  heartbeat(domain) { return data.heartbeats[domain] || null; },
};
