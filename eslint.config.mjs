import js from "@eslint/js";
import globals from "globals";
export default [
  js.configs.recommended,
  {
    files: ["assets/src/**/*.{js,jsx}", "tests/**/*.mjs"],
    languageOptions: {
      ecmaVersion: 2022,
      sourceType: "module",
      parserOptions: { ecmaFeatures: { jsx: true } },
      globals: { ...globals.browser, ...globals.node, wp: "readonly" },
    },
    rules: {
      "no-unused-vars": [
        "error",
        { varsIgnorePattern: "^[A-Z]", argsIgnorePattern: "^_" },
      ],
    },
  },
];
