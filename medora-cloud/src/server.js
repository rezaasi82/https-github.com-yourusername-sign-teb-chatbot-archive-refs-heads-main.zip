/**
 * Medora Cloud Platform — Level 2 backend (zero external dependencies).
 *
 * Pairs with the plugin's SWC_Cloud_Client:
 *   POST /v1/install          register an installation
 *   POST /v1/heartbeat        daily telemetry
 *   GET  /v1/license/:domain  signed license status
 *   GET  /v1/update/latest    auto-update feed
 *   POST /v1/alert            internal alert relay (ADMIN_TOKEN)
 *   GET  /admin               realtime monitor (ADMIN_TOKEN)
 *   GET  /admin/stream        SSE event feed (ADMIN_TOKEN)
 *   GET  /healthz
 *
 * Inbound install/heartbeat bodies are HMAC-verified against MEDORA_SECRET
 * (header `X-Medora-Sign: sha256=…`, matching the plugin exactly).
 */
import http from 'node:http';
import { URL } from 'node:url';
import fs from 'node:fs';
import { store } from './store.js';
import { verify, signObject, freshTimestamp } from './sign.js';
import { notify } from './telegram.js';
import { renderDashboard } from './dashboard.js';

// --- minimal .env loader (no dependency) ---
try {
  for (const line of fs.readFileSync(new URL('../.env', import.meta.url), 'utf8').split('\n')) {
    const m = line.match(/^\s*([A-Z0-9_]+)\s*=\s*(.*)\s*$/);
    if (m && !process.env[m[1]]) process.env[m[1]] = m[2].replace(/^["']|["']$/g, '');
  }
} catch { /* no .env file — use real env */ }

const PORT = parseInt(process.env.PORT || '8787', 10);
const SECRET = process.env.MEDORA_SECRET || '';
const ADMIN_TOKEN = process.env.ADMIN_TOKEN || '';
const LATEST_VERSION = process.env.MEDORA_LATEST_VERSION || '3.7.0';
const UPDATE_URL = process.env.MEDORA_UPDATE_URL || '';
const UPDATE_SHA = process.env.MEDORA_UPDATE_SHA256 || '';

// --- realtime SSE bus ---
const sseClients = new Set();
function broadcast(evt) {
  const e = store.pushEvent(evt);
  const line = `data: ${JSON.stringify(e)}\n\n`;
  for (const res of sseClients) { try { res.write(line); } catch {} }
  return e;
}

function readBody(req) {
  return new Promise((resolve) => {
    let raw = '';
    req.on('data', (c) => { raw += c; if (raw.length > 1e6) req.destroy(); });
    req.on('end', () => resolve(raw));
    req.on('error', () => resolve(raw));
  });
}
const json = (res, code, obj) => {
  const body = JSON.stringify(obj);
  res.writeHead(code, { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(body) });
  res.end(body);
};
const adminOk = (u) => ADMIN_TOKEN && u.searchParams.get('token') === ADMIN_TOKEN;

const server = http.createServer(async (req, res) => {
  const u = new URL(req.url, `http://${req.headers.host || 'localhost'}`);
  const p = u.pathname;

  try {
    if (p === '/healthz') return json(res, 200, { ok: true, version: LATEST_VERSION });

    // ---- inbound telemetry (signed) ----
    if (req.method === 'POST' && (p === '/v1/install' || p === '/v1/heartbeat')) {
      const raw = await readBody(req);
      if (!verify(raw, req.headers['x-medora-sign'], SECRET) || !freshTimestamp(req.headers['x-medora-time'])) {
        return json(res, 401, { ok: false, error: 'bad_signature' });
      }
      let payload;
      try { payload = JSON.parse(raw); } catch { return json(res, 400, { ok: false, error: 'bad_json' }); }
      const domain = String(payload.domain || '').slice(0, 64);
      if (!domain) return json(res, 400, { ok: false, error: 'no_domain' });

      if (p === '/v1/install') {
        const isNew = store.upsertInstall(domain, payload);
        const e = broadcast({ type: 'install', severity: 'info', detail: `${domain.slice(0, 10)}… v${payload.plugin_version || '?'}` });
        if (isNew) notify(`🟢 <b>New installation</b>\nDomain: <code>${domain.slice(0, 12)}…</code>\nVersion: ${payload.plugin_version || '?'} · PHP ${payload.php_version || '?'}\nTZ: ${payload.timezone || '-'}`);
        return json(res, 200, { ok: true, registered: true, new: isNew });
      }

      store.recordHeartbeat(domain, payload);
      broadcast({ type: 'heartbeat', severity: 'info', detail: `${domain.slice(0, 10)}… msg:${payload?.counts?.messages ?? 0} leads:${payload?.counts?.leads ?? 0}` });
      return json(res, 200, { ok: true });
    }

    // ---- signed license status ----
    if (req.method === 'GET' && p.startsWith('/v1/license/')) {
      const domain = decodeURIComponent(p.slice('/v1/license/'.length));
      const lic = store.getLicense(domain) || { status: 'trial', plan: 'starter', expires: null, features: ['chat', 'export'], grace: 14 };
      const body = { domain, status: lic.status, plan: lic.plan, expires: lic.expires, features: lic.features, grace: lic.grace, ts: Math.floor(Date.now() / 1000) };
      return json(res, 200, { ...body, signature: SECRET ? signObject(body, SECRET) : '' });
    }

    // ---- auto-update feed ----
    if (req.method === 'GET' && p === '/v1/update/latest') {
      return json(res, 200, { version: LATEST_VERSION, url: UPDATE_URL, sha256: UPDATE_SHA });
    }

    // ---- internal alert relay ----
    if (req.method === 'POST' && p === '/v1/alert') {
      if (!adminOk(u)) return json(res, 403, { ok: false });
      const raw = await readBody(req);
      let a = {}; try { a = JSON.parse(raw); } catch {}
      notify(String(a.text || 'Medora alert'));
      broadcast({ type: a.type || 'alert', severity: a.severity || 'warning', detail: String(a.text || '').slice(0, 120) });
      return json(res, 200, { ok: true });
    }

    // ---- admin dashboard ----
    if (req.method === 'GET' && p === '/admin') {
      if (!adminOk(u)) { res.writeHead(403); return res.end('forbidden'); }
      const html = renderDashboard(store, ADMIN_TOKEN);
      res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
      return res.end(html);
    }
    if (req.method === 'GET' && p === '/admin/stream') {
      if (!adminOk(u)) { res.writeHead(403); return res.end(); }
      res.writeHead(200, { 'Content-Type': 'text/event-stream', 'Cache-Control': 'no-cache', Connection: 'keep-alive' });
      res.write('retry: 5000\n\n');
      sseClients.add(res);
      req.on('close', () => sseClients.delete(res));
      return;
    }

    res.writeHead(404, { 'Content-Type': 'application/json' });
    res.end('{"ok":false,"error":"not_found"}');
  } catch (e) {
    console.error('request error:', e.message);
    json(res, 500, { ok: false, error: 'server_error' });
  }
});

// ---- daily digest to Telegram ----
setInterval(() => {
  const t = store.totals();
  notify(`📊 <b>Medora daily digest</b>\nInstalls: ${t.installs} · Active licenses: ${t.active_licenses}\nMessages: ${t.messages} · Leads: ${t.leads} · Bookings: ${t.bookings}`);
}, 24 * 60 * 60 * 1000).unref?.();

server.listen(PORT, () => {
  console.log(`Medora Cloud listening on :${PORT}  (secret ${SECRET ? 'set' : 'NOT set — dev mode'})`);
});
