const { chromium } = require('/opt/node22/lib/node_modules/playwright');
const path = require('path');

// [view, viewportWidth, output, clipSelector|null]
const JOBS = [
  ['premium-dashboard',  1193, 'admin-dashboard.png',     '.pzk-dash'],
  ['settings',           1186, 'admin-settings.png',      'body'],
  ['crm-board',          1186, 'admin-crm.png',           '.pzk-board-wrap'],
  ['seo',                1189, 'admin-seo.png',           'body'],
  ['branches',           1186, 'admin-branches.png',      'body'],
  ['dashboard',          1186, 'admin-stats.png',         'body'],
  ['conversations-list', 1186, 'admin-conversations.png', 'body'],
];

(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
  for (const [view, w, out, sel] of JOBS) {
    const p = await b.newPage({ viewport: { width: w, height: 900 }, deviceScaleFactor: 2 });
    await p.goto('file://' + path.join(__dirname, 'out', view + '.html'), { waitUntil: 'networkidle' });
    await p.waitForTimeout(1600);

    // Match the viewport to the content so fullPage does not pad with blank space.
    const h = await p.evaluate(() => Math.ceil(document.documentElement.scrollHeight));
    await p.setViewportSize({ width: w, height: h });
    await p.waitForTimeout(150);

    const file = path.join(__dirname, 'png', out);
    if (sel) {
      await (await p.$(sel)).screenshot({ path: file });
    } else {
      await p.screenshot({ path: file });
    }
    await p.close();
  }
  await b.close();
  console.log('done');
})();
