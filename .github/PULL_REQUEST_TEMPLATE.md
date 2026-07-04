## What does this change?

<!-- A short description of the change and the problem it solves. Link related issues. -->

## Checklist

- [ ] Tests pass (`composer test`) and new behaviour is covered by a test
- [ ] Static analysis passes (`composer analyse` — PHPStan level 9)
- [ ] Code style passes (`composer lint`)
- [ ] `dist/` rebuilt and committed if anything under `resources/` changed (`npm run prod`)
- [ ] `CHANGELOG.md` updated under `[Unreleased]`
- [ ] No security boundary is widened by default (allow-lists stay opt-in, bash stays off)
