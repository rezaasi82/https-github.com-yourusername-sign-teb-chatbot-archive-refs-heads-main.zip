/**
 * Server-rendered admin dashboard (navy/gold, glassmorphic) with a realtime
 * event feed over Server-Sent Events. Self-contained HTML — no external assets.
 */
export function renderDashboard(store, token) {
  const t = store.totals();
  const installs = store.installs().slice(0, 100);
  const events = store.events(40);
  const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const countryRows = Object.entries(t.countries).sort((a, b) => b[1] - a[1]).slice(0, 8)
    .map(([c, n]) => `<tr><td>${esc(c)}</td><td>${n}</td></tr>`).join('');

  const installRows = installs.map((i) => `
    <tr>
      <td><code>${esc((i.domain || '').slice(0, 12))}…</code></td>
      <td>${esc(i.plugin_version || '-')}</td>
      <td>${esc(i.php_version || '-')}</td>
      <td>${esc(i.timezone || '-')}</td>
      <td>${esc((i.last_seen || '').replace('T', ' ').slice(0, 16))}</td>
    </tr>`).join('');

  const eventRows = events.map((e) => `<li><span class="dot ${esc(e.severity || 'info')}"></span><b>${esc(e.type)}</b> — ${esc(e.detail || '')} <em>${esc((e.at || '').replace('T', ' ').slice(11, 19))}</em></li>`).join('');

  return `<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Medora Cloud — Monitor</title>
<style>
  :root{--navy:#0f1f3d;--navy2:#1c3a6e;--gold:#c8a04e;--gold2:#e6c476;--ink:#eaf0fb}
  *{box-sizing:border-box}body{margin:0;font-family:system-ui,Segoe UI,Roboto,sans-serif;background:linear-gradient(160deg,#0b1730,#12244a);color:var(--ink);padding:26px}
  h1{margin:0 0 4px;font-size:22px}.sub{color:#93a4c6;font-size:13px;margin-bottom:22px}
  .grid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:22px}
  @media(max-width:900px){.grid{grid-template-columns:repeat(2,1fr)}}
  .card{background:rgba(255,255,255,.05);border:1px solid rgba(200,160,78,.25);border-radius:16px;padding:18px}
  .card .n{font-size:30px;font-weight:800;color:var(--gold2)}.card .l{font-size:12px;color:#9fb0d2;margin-top:4px}
  .cols{display:flex;gap:18px;flex-wrap:wrap}.col{flex:1 1 380px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:16px}
  h2{font-size:14px;color:var(--gold2);margin:0 0 12px}
  table{width:100%;border-collapse:collapse;font-size:12.5px}td,th{padding:7px 8px;border-bottom:1px solid rgba(255,255,255,.07);text-align:start}
  code{color:#9fd0ff}
  ul.feed{list-style:none;margin:0;padding:0;max-height:420px;overflow:auto}ul.feed li{padding:8px 4px;border-bottom:1px solid rgba(255,255,255,.06);font-size:12.5px}
  ul.feed em{color:#8296bd;float:inline-end}
  .dot{display:inline-block;width:8px;height:8px;border-radius:50%;margin-inline-end:7px;background:#6ee7b7}
  .dot.warning{background:var(--gold2)}.dot.critical{background:#f87171}
</style></head><body>
  <h1>Medora Cloud — Realtime Monitor</h1>
  <div class="sub">Live fleet telemetry · updates without refresh</div>
  <div class="grid">
    <div class="card"><div class="n" id="m-installs">${t.installs}</div><div class="l">Installations</div></div>
    <div class="card"><div class="n">${t.messages.toLocaleString()}</div><div class="l">Messages</div></div>
    <div class="card"><div class="n">${t.leads.toLocaleString()}</div><div class="l">Leads</div></div>
    <div class="card"><div class="n">${t.bookings.toLocaleString()}</div><div class="l">Booking clicks</div></div>
    <div class="card"><div class="n">${t.active_licenses}</div><div class="l">Active licenses</div></div>
  </div>
  <div class="cols">
    <div class="col"><h2>Installations</h2><table><thead><tr><th>Domain</th><th>Ver</th><th>PHP</th><th>TZ</th><th>Last seen</th></tr></thead><tbody>${installRows || '<tr><td colspan=5>No installs yet.</td></tr>'}</tbody></table></div>
    <div class="col"><h2>Realtime feed</h2><ul class="feed" id="feed">${eventRows || '<li>Waiting for events…</li>'}</ul></div>
    <div class="col" style="flex:0 1 240px"><h2>Countries</h2><table><tbody>${countryRows || '<tr><td>—</td></tr>'}</tbody></table></div>
  </div>
  <script>
    const src = new EventSource('/admin/stream?token=${encodeURIComponent(token)}');
    const feed = document.getElementById('feed');
    src.onmessage = (m) => {
      try {
        const e = JSON.parse(m.data);
        const li = document.createElement('li');
        li.innerHTML = '<span class="dot '+(e.severity||'info')+'"></span><b>'+e.type+'</b> — '+(e.detail||'')+' <em>'+(e.at||'').slice(11,19)+'</em>';
        feed.prepend(li);
        if (e.type === 'install') { const el=document.getElementById('m-installs'); el.textContent = (+el.textContent+1); }
      } catch {}
    };
  </script>
</body></html>`;
}
