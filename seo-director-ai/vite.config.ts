import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// Builds the admin SPA into assets/dist with a manifest that
// includes/Admin/Assets.php resolves at enqueue time.
export default defineConfig({
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
