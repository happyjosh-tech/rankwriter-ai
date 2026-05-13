# Contributing & release workflow

## Local development

This repo IS the plugin folder. To work on it against a real WordPress install:

```bash
# Symlink the repo into your local wp-content/plugins so edits hit WP live
ln -s "$(pwd)" /path/to/wordpress/wp-content/plugins/rankwriter-ai
```

Or work in `wp-content/plugins/rankwriter-ai` directly and `git push` from there.

## Day-to-day in VS Code

1. Edit code.
2. Open Source Control (Cmd+Shift+G).
3. Stage → commit → push.

Nothing ships to end-user sites until you tag a release.

## Cutting a release

When `main` is stable and ready to ship to all installs:

1. Bump the version in **two places**:
   - `rankwriter-ai.php` plugin header (`* Version: 1.0.1`)
   - `RWAI_VERSION` constant in the same file
2. Update `CHANGELOG.md` with what changed.
3. Commit:
   ```bash
   git add rankwriter-ai.php CHANGELOG.md
   git commit -m "Release v1.0.1"
   ```
4. Tag and push:
   ```bash
   git tag v1.0.1
   git push origin main --tags
   ```
5. GitHub Actions ([release.yml](.github/workflows/release.yml)) takes over:
   - Lints every PHP file
   - Builds a clean ZIP excluding `.git`, `.github`, build artefacts
   - Creates a GitHub Release for the tag
   - Attaches `rankwriter-ai.zip` and `info.json`
   - Auto-generates release notes from commit messages
6. End-user WordPress installs see the update within 12 hours, or immediately if the admin clicks **Dashboard → Updates → Check again**.

## Rolling back a bad release

```bash
# Delete the bad tag locally and on GitHub
git tag -d v1.0.1
git push origin :refs/tags/v1.0.1
# Then delete the GitHub release in the web UI.
```

User sites will see the previous release as latest on their next 12-hour check.

To ship a fixed version, just tag a new patch release (`v1.0.2`).

## Branch protection (recommended)

In the GitHub repo Settings → Branches:

- Protect `main`: require PRs before merging.
- Require the **Lint PHP** check to pass before merge.

## Tags must follow semver

Use `vMAJOR.MINOR.PATCH` (e.g. `v1.0.1`, `v1.2.0`, `v2.0.0-beta.1`).

The `vX.Y.Z` form is required — the release workflow strips the leading `v` to compute the WordPress-facing version number.

## What never gets shipped to users

The release workflow excludes:

- `.git/`, `.github/`, `.gitignore`
- `node_modules/`, `vendor/`, `build/`
- `*.zip`, `.DS_Store`, `Thumbs.db`
- `.idea/`, `.vscode/`
- `*.log`, `.phpunit.result.cache`
- `composer.lock`, `package-lock.json`
- `CONTRIBUTING.md` and `CHANGELOG.md` ARE shipped (users may want to read them).
