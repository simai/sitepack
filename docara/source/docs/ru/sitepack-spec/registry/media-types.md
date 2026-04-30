---
extends: _core._layouts.documentation
section: content
title: Типы медиа
description: Типы медиа
---

# Реестр Media Types

## Core (MUST understand)
- `application/vnd.sitepack.entity-graph+ndjson` — граф сущностей (NDJSON).
- `application/vnd.sitepack.asset-index+ndjson` — индекс assets (NDJSON).
- `application/vnd.sitepack.config-kv+ndjson` — key-value конфигурация (NDJSON).
- `application/vnd.sitepack.recordset+ndjson` — данные recordset (NDJSON).

## Рекомендуемые
- `application/vnd.sitepack.volume-set+json` — дескриптор набора томов (`sitepack.volumes.json`).
- `application/vnd.sitepack.object-index+json` — индекс объектов (`objects/index.json`).
- `application/vnd.sitepack.object-passport+json` — паспорт объекта (`objects/<objectId>/passport.json`).
- `application/vnd.sitepack.capabilities+json` — capabilities инструмента.
- `application/vnd.sitepack.transform-plan+json` — план преобразований.
- `application/vnd.sitepack.software-manifest+json` — метаданные кода/ПО.
- `text/markdown` — текстовые документы в `artifacts/code/`.
