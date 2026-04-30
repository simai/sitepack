# Translation Status

This Docara project uses deterministic translation state tracking. Do not rely on manual inspection or AI memory to decide whether a locale is current.

## State Storage

Local translation state is stored in:

```text
docara/.docara-state/translate-state.php
```

This file is operational metadata and must stay out of git. Legacy state files under `docara/source/docs/.translate.php` are also ignored and should not be committed.

## Current Audit

Checked on 2026-04-30:

- Base locale: `en`
- Target locale: `ru`
- TODO files: `0`
- Missing RU target files: `0`
- Local target changes blocking automation: `0`

The Russian documentation is synchronized with the current English source according to the local translation state.

## Commands

Refresh status:

```bash
php <skill>/scripts/docara-translate-state.php --docs-dir=docara/source/docs --source=en --targets=ru --json
```

Print actionable TODO with reasons:

```bash
php <skill>/scripts/docara-translate-state.php --docs-dir=docara/source/docs --source=en --targets=ru --print-todo-with-size --target=ru --json
```

Check target syntax:

```bash
php <skill>/scripts/docara-translate-state.php --docs-dir=docara/source/docs --source=en --targets=ru --check-targets=ru --json
```

Sync only after a reviewed or translated batch:

```bash
php <skill>/scripts/docara-translate-state.php --docs-dir=docara/source/docs --source=en --targets=ru --sync-targets=ru
```

## Workflow

1. Refresh status and TODO JSON.
2. Translate `.lang.php` and `.settings.php` service files first.
3. Translate missing section `index.md` files so navigation does not point to placeholders.
4. Translate remaining Markdown in size-aware batches.
5. After each batch, run target checks and production build.
6. Run browser smoke for language switcher, search, settings menu, and representative EN/RU pages.
7. Sync only the reviewed batch state.
8. Stop only when TODO is zero and checks pass.
