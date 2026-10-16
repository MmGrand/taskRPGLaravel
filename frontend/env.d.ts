/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_API_URL: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}

// Newer than the ES2022 lib this build targets, and missing in older browsers
declare namespace Intl {
  const supportedValuesOf: ((key: 'timeZone') => string[]) | undefined
}
