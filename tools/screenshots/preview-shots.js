const { chromium } = require('/opt/node22/lib/node_modules/playwright');
const path = require('path');

// [html, cssWidth, clipSelector, outName, targetW, targetH]
const JOBS = [
  ['premium-dashboard',    1160, '.pzk-dash-inner',  'dashboard.png',     2732, 1700],
  ['crm-board',            1366, '.pzk-board-wrap',  'crm-board.png',     2732, 1700],
  ['conversation-single',  1366, 'body',             'lead-crm.png',      2732, 1700],
  ['seo',                  1366, 'body',             'seo-center.png',    2732, 1700],
  ['settings-integrations',1366, 'body',             'sms-settings.png',  2732, 1700],
  ['widget',                900, 'body',             'widget-chat.png',    920, 1560],
  ['widget-teaser',         680, 'body',             'widget-teaser.png',  680,  400],
  ['admin-notify',         1366, 'body',             'admin-notify.png',  2732,  520],
];

(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
  for (const [view, w, sel, out, tw, th] of JOBS) {
    const p = await b.newPage({ viewport: { width: w, height: 1000 }, deviceScaleFactor: 2 });
    await p.goto('file://' + path.join(__dirname, 'out', view + '.html'), { waitUntil: 'networkidle' });
    await p.waitForTimeout(1400);
    const h = await p.evaluate(() => Math.ceil(document.documentElement.scrollHeight));
    await p.setViewportSize({ width: w, height: Math.max(h, 200) });
    await p.waitForTimeout(150);
    const file = path.join(__dirname, 'raw', out);
    await (await p.$(sel)).screenshot({ path: file });
    console.log(`  ${out.padEnd(22)} raw captured  (target ${tw}x${th})`);
    await p.close();
  }
  await b.close();
})();
