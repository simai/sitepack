---
extends: _core._layouts.documentation
section: content
title: Tasks Exporter
description: Tasks Exporter
---

# Задача: SitePack Exporter

## Цель
Сгенерировать распакованный пакет SitePack и, опционально, упаковать его в `.sitepack` (ZIP).

## Входные данные
Любая модель данных сайта: контент, assets, config, таблицы. Экспортер сам определяет применимые profiles.

## Требования
- Создать `sitepack.manifest.json` и `sitepack.catalog.json` в корне.
- Заполнить `manifest.spec` и `manifest.package.id`.
- `manifest.createdAt` — UTC date-time (ISO 8601).
- `manifest.profiles` — выбранные profiles.
- `manifest.artifacts` — список artifact IDs.
- `catalog.artifacts[]` — полный список артефактов с `id`, `mediaType`, `path`, `size` и, желательно, `digest`.
- `size` — длина файла в байтах.
- `digest` — `sha256:<hex>`.
- `relations` MUST выводиться как объект, который сопоставляет relation keys с `Link[]`.
- Link MUST быть строкой или объектом с `ref` и опциональным `meta`.
- Если создается разделенная поставка, сгенерировать `sitepack.volumes.json` со `spec.version = 0.4.0`, `kind = volume-set` и списком `volumes[]`.
- `maxPartSize` SHOULD быть `104857600` байт (100 MiB), если пользователь не указал иначе.

## NDJSON
- Один JSON-объект на строку.
- Без лишних пробелов и пустых строк.

## Chunked assets
- Если asset разбит на чанки, вывести `chunks[]` с `index`, `size`, `sha256` и `path` для каждого чанка.
- Для chunked assets не указывать `path` на уровне asset и включать `chunks`.
- Вычислять и хранить как `sha256` каждого чанка, так и общий `sha256`/`size` asset.

## Profiles (рекомендации)
- `config-only`: только config.
- `content-only`: сущности без assets.
- `content+assets`: сущности + asset index.
- `full`: content + assets + config + recordsets.
- `full+code`: `full` + code/software manifest.

## Выходные данные
- Распакованный каталог пакета.
- Опционально: ZIP-файл с расширением `.sitepack`.
- Сводка `reports/export.json`:
```json
{
  "packageId": "...",
  "profiles": ["..."],
  "artifacts": 0,
  "files": 0,
  "bytes": 0,
  "digests": "sha256"
}
```
