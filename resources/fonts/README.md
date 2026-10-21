# Fonts

Cairo (Arabic and Latin, 400 to 700) and Instrument Sans (Latin, 400 to 600), as woff2 files copied from the Fontsource npm packages (5.3.0), in Fontsource's own layout: one CSS file per weight and the files it names. `vite.config.ts` points laravel-vite-plugin's `fontsource` provider at these folders, so a build downloads no font. Both fonts are under the SIL Open Font License 1.1 (`LICENSE` in each folder).

Only the woff2 files are kept: the plugin writes each format as its own `@font-face` rule, and browsers then use the last one, the larger woff.

To refresh or add a weight, install the packages without saving them (`npm install --no-save @fontsource/cairo @fontsource/instrument-sans`), copy the weight's CSS blocks and woff2 files the same way, and keep only the woff2 `src`.
