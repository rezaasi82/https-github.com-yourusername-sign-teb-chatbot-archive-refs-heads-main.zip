// @ts-check
const { defineConfig, devices } = require('@playwright/test');

module.exports = defineConfig({
    testDir: './tests/e2e',
    timeout: 30_000,
    expect:  { timeout: 5_000 },
    fullyParallel: false,
    reporter: 'list',
    use: {
        browserName:     'chromium',
        launchOptions:   { executablePath: '/opt/pw-browsers/chromium' },
        headless:        true,
        viewport:        { width: 480, height: 900 },
    },
});
