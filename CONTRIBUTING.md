# Contributing

Thanks for your interest in improving Nova Command Center!

## Getting started

```bash
git clone https://github.com/farsidev/nova-command-center
cd nova-command-center
```

### PHP dependencies

Nova is a paid, private Composer package. You have two options:

1. **With Nova credentials** (recommended, closest to production):

   ```bash
   composer config repositories.nova composer https://nova.laravel.com
   composer config http-basic.nova.laravel.com "<email>" "<license-key>"
   composer install
   vendor/bin/pest
   ```

2. **Without Nova credentials** — the security-critical core is deliberately
   Nova-independent. Provide a minimal local stub for `laravel/nova` and point a
   throwaway Composer file at it (this is what CI does for the analysis job):

   ```bash
   # create a tiny stub package exposing { "name": "laravel/nova", "version": "5.0.0" }
   # then reference it via a path repository in a composer.testing.json and:
   COMPOSER=composer.testing.json composer install
   COMPOSER=composer.testing.json vendor/bin/pest
   ```

### Frontend

```bash
npm install
npm run dev      # build once (dev)
npm run watch    # rebuild on change
npm run prod     # minified production build committed to dist/
```

## Quality gates

All pull requests must pass:

```bash
composer test      # Pest
composer analyse   # PHPStan (level 6)
composer lint      # Pint (dry run) — run `composer format` to auto-fix
```

Please also rebuild `dist/` (`npm run prod`) when you change any frontend asset,
since the compiled bundle is committed and shipped.

## Guidelines

- Keep the security guarantees in [SECURITY.md](SECURITY.md) intact. Any change
  to command building must preserve the "no shell interpolation" property and be
  covered by tests in `tests/Unit/CommandBuilderTest.php`.
- Add tests for new behaviour.
- Follow the existing code style (enforced by Pint).
- Keep the tool working on **both Nova 4 and Nova 5**.
