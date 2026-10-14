import { fileURLToPath } from 'node:url'
import { mergeConfig, defineConfig, configDefaults } from 'vitest/config'
import viteConfig from './vite.config.ts'

export default mergeConfig(
  viteConfig,
  defineConfig({
    test: {
      environment: 'jsdom',
      exclude: [...configDefaults.exclude, 'e2e/**'],
      root: fileURLToPath(new URL('./', import.meta.url)),
      setupFiles: ['./src/__tests__/setup.ts'],
      // Tests must not depend on a developer's local .env file (CI has none)
      env: { VITE_API_URL: 'http://localhost:8000/api' },
      coverage: {
        // Every source file counts, not only those some test imports
        include: ['src/**/*.{ts,vue}'],
        exclude: ['src/**/__tests__/**', 'src/main.ts'],
        thresholds: { lines: 80, functions: 80, branches: 80, statements: 80 },
      },
    },
  }),
)
