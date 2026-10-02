/// <reference types="astro/client" />

interface ImportMetaEnv {
  readonly SITE_ENV: "demo" | "production";
  readonly PUBLIC_SITE_URL?: string;
}

interface ImportMeta {
  readonly env: ImportMetaEnv;
}

