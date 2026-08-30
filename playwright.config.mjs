export default {
  testDir: './tests/Visual',
  timeout: 60_000,
  use: {
    browserName: 'chromium',
    baseURL: process.env.VISUAL_BASE_URL ?? 'http://localhost:38515',
    viewport: { width: 1440, height: 900 },
    deviceScaleFactor: 1,
  },
};

