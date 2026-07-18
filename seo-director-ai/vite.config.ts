import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// Builds the admin SPA into assets/dist with a manifest that
// includes/Admin/Assets.php resolves at enqueue time.
export default defineConfig({
  // Relative base so code-split dynamic-import chunks and their CSS resolve
  // against the JS file's own URL (…/wp-content/plugins/seo-director-ai/assets/
  // dist/assets/), not the site root — otherwise every lazy route 404s on a
  // real WordPress install and the dashboard renders blank.
  base: './',
  plugins: [react()],
  build: {
    outDir: 'assets/dist',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: 'src/main.tsx',
    },
  },
});
