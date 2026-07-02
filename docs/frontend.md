# Frontend, theming & dark mode

## How it's built

The UI is a small set of Vue 3 single-file components compiled with **Laravel
Mix** — the build system Nova uses on both v4 and v5 (Vite is not officially
supported for Nova tools). Components use the Composition API (`<script setup>`).

```
resources/
├── js/
│   ├── tool.js                 # registers the Inertia page with Nova
│   ├── pages/Tool.vue          # the command list, output console, history
│   ├── components/             # CommandCard, RunModal, VariableField, …
│   └── util/
│       ├── api.js              # Nova.request wrappers
│       └── translate.js        # __() shim (survives Nova 5 removing global __)
└── css/tool.css                # component styles (see “Theming” below)
```

Build the assets:

```bash
npm install
npm run dev      # or: npm run watch / npm run prod
```

`npm run prod` writes the compiled bundle to `dist/`, which is committed so the
package works straight from `composer require`. Rebuild and commit `dist/`
whenever you change anything under `resources/`.

## Dark mode

The tool follows Nova's light/dark switch automatically. Nova toggles a `.dark`
class on the `<html>` element; the templates use Tailwind `dark:` variants for
layout colours, and `resources/css/tool.css` mirrors every custom component
(buttons, console, cards, badges, progress, modal) with `.dark` rules. There is
nothing to configure — switch the Nova theme and the tool follows.

## Theming

Colours come from two places:

- **Nova's Tailwind utilities** in the templates keep the tool visually
  consistent with the host panel.
- **`resources/css/tool.css`** holds the self-contained pieces. Button and
  progress accents use the `--primary` CSS variable Nova exposes, so they adopt
  your panel's primary colour. Override any `.ncr-*` class in your own Nova
  stylesheet to restyle.

Button styles map to the command `type` (`primary`, `success`, `warning`,
`danger`, `info`, `secondary`, `link`) via `.ncr-btn-{type}` classes.

## Accessibility & motion

Buttons expose focus-visible rings, the modal uses `role="dialog"`/`aria-modal`,
and all animation is disabled under `prefers-reduced-motion: reduce`.

## Internationalisation

All user-facing strings pass through the `__()` helper. Publish the language
file to translate them:

```bash
php artisan vendor:publish --tag=nova-command-center-lang
```
