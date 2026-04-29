---
extends: _core._layouts.documentation
section: content
title: Документация SitePack
description: Документация SitePack
---

# SitePack

SitePack — открытый стандарт упаковки данных сайта для резервного копирования и миграции между системами.

## Проекты

- `sitepack-spec/` — спецификация, схемы, реестры и примеры.
- `sitepack-tools-node/` — эталонный валидатор на Node.js.
- `sitepack-tools-php/` — эталонный валидатор на PHP.

## Быстрый старт

Проверить примеры через Node-инструмент:

```bash
cd sitepack-tools-node
npm ci
npm run validate-examples -- ../sitepack-spec/examples
```

Проверить один распакованный пакет:

```bash
node bin/sitepack-validate <packageRoot>
```

Проверить заголовок envelope:

```bash
node bin/sitepack-validate envelope <path-to-enc-json>
```

Проверить набор томов:

```bash
node bin/sitepack-validate volumes <path-to-volumes-json>
```

Создать набор томов:

```bash
node bin/sitepack-volumes create <packageRoot> <outDir> --max-part-size 104857600
```

Быстрый старт для PHP:

```bash
cd sitepack-tools-php
composer install
./bin/sitepack-validate --quiet package <packageRoot>
./bin/sitepack-validate envelope <path-to-enc-json>
./bin/sitepack-validate volumes <path-to-volumes-json>
php scripts/validate-examples.php ../sitepack-spec/examples
```

## Структура репозитория

```text
.
├── sitepack-spec/
├── sitepack-tools-node/
└── sitepack-tools-php/
```

## Версионирование

Спецификация и инструменты следуют SemVer. См. changelog соответствующих подпроектов.

## Лицензия

MIT. См. `LICENSE` в корне репозитория и в каждом подпроекте.

## Участие в разработке

См. `CONTRIBUTING.md`.
