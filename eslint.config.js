/* =======================================
 * ストラテジックアライアンス採用 ESLint設定
 * URL: /eslint.config.js
 * Referenced in: /package.json
 * Created: 2026-10-05
 * Last updated: 2026-10-05
 * ======================================= */
import js from "@eslint/js";
import eslintPluginAstro from "eslint-plugin-astro";
import tseslint from "typescript-eslint";

export default [
  {
    ignores: [".astro/**", "dist/**", "node_modules/**"],
  },
  js.configs.recommended,
  ...tseslint.configs.recommended,
  ...eslintPluginAstro.configs.recommended,
  {
    files: ["**/*.astro"],
    languageOptions: {
      parserOptions: {
        parser: tseslint.parser,
      },
    },
  },
];
