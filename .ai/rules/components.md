---
paths:
  - '{public/{favicon,logo}*,public/*icon*,public/site.webmanifest,resources/js/components/AppLogo*.vue}'
  - '{public/{favicon,logo,mark}*,public/*icon*,public/site.webmanifest,resources/js/components/AppLogo*.vue}'
---

# Components

## Brand assets: M€ monogram, outlines only
The MoneyCloud mark is an "M€" monogram (the € stands in for the C) set in Instrument Serif (SIL OFL) and shipped as SVG **outlines** — never as live text or a webfont. AppLogoIcon.vue (medallion) and AppLogoWordmark.vue (wordmark) each hold one `<path>`; only logo.svg's "OSEBNE FINANCE" strapline stays a `<text>` node, in a system-ui stack. Tests in tests/Feature/AppLayoutFontTest.php enforce this.

Optical-size system — do not mix these up:
- circle medallion + M€ → in-app mark, logo.svg lockup (≥48 px)
- full-bleed ink square + M€ → apple-touch-icon (180) and maskable manifest icons (192/512, glyph inside a 0.72 safe-zone scale)
- rounded ink tile + € alone, emboldened via stroke ≈2.2/64 → favicon.svg, favicon.ico (16/32/48), favicon-96x96.png. The serif € is unreadable at 16 px without both the tile and the emboldening.

Colours are tokens in resources/css/app.css: --brand-ink / --brand-cream, with --brand-mark and --brand-mark-foreground swapping in .dark so the medallion inverts. Use `fill-brand-mark` / `fill-brand-mark-foreground`; AppLogoIcon takes an `inverted` prop for permanently dark surfaces (e.g. AuthSplitLayout).

To regenerate outlines: download InstrumentSerif-Regular.ttf from Google Fonts, then use fontTools (SVGPathPen + BoundsPen) to emit paths normalised into a 0 0 64 64 box, ink centred on (32,32). PNGs are rendered with headless Chrome — the SVG's width/height attributes must be set to the target pixel size or Chrome renders it at its intrinsic 64 px and crops.

## Sidebar logo is four static SVGs, swapped by CSS
AppLogo.vue renders `<img>` tags, not inline SVG. Four files in public/: logo-light.svg / logo-dark.svg (full lockup, viewBox 143x40, drawn 1:1 for `h-10`) and mark-light.svg / mark-dark.svg (medallion only, rendered `size-8`).

Two independent swaps, deliberately kept on different elements so no element carries two utilities fighting over `display`:
- the WRAPPER `<span>` picks the sidebar state (`group-data-[collapsible=icon]:hidden` / `:flex`) — the sidebar is `collapsible="icon"` and SidebarTrigger makes that state reachable, so the collapsed mark is not optional;
- the `<img>` picks the theme (`dark:hidden` / `hidden dark:block`), riding the `dark` class on <html> that useAppearance maintains. Do NOT use `prefers-color-scheme` inside these files: the app has a manual light/dark/system toggle, so the OS query would disagree with an explicit choice. public/logo.svg (the README/general-purpose lockup) does carry the media query — that one is not used in-app.

The lockup's "OSEBNE FINANCE" strapline is a live `<text>` pinned with `textLength` + `lengthAdjust="spacing"` to the wordmark's width (96.94). Keep that: it makes the `<img>` intrinsic width independent of whichever font the SVG resolves, and it justifies the strapline to the wordmark instead of overhanging it.

Beware: SidebarMenuButton sets `[&>svg]:size-4`, which silently shrinks any direct `svg` child to 16px. Anything logo-sized must sit inside a wrapper element.
