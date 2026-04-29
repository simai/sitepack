---
extends: _core._layouts.documentation
section: content
title: SitePack v0.4.0
description: SitePack v0.4.0
---

# SitePack v0.4.0

SitePack — открытый формат упаковки данных сайта для экспорта и импорта между системами. Пакет содержит два обязательных файла, manifest и catalog, а также набор артефактов: entities, assets, config, recordsets и другие.

Ключевые решения:

- Каноническое расширение пакета: `.sitepack`.
- Контейнер по умолчанию: ZIP. Формат контейнера определяется по содержимому, а не по расширению.
- Безопасная передача использует внешний age envelope: `*.sitepack.enc` + `*.sitepack.enc.json`.
- Связи стандартизированы через Link encoding с массивами в `relations`.
- Volume Sets описывают передачу разделенных пакетов (`sitepack.volumes.json`).
- Asset index поддерживает chunked blobs (`chunks[]`).

Материалы документации:

- Спецификация: `SPEC.md`.
- JSON-схемы: `schemas/`.
- Реестры: `registry/`.
- Примеры пакетов: `examples/`.
- Материалы для LLM: `llm/`.

Примеры:

- `cross-relations`: демонстрирует связи entity-to-entity и entity-to-asset через Link[] (`property.BRAND`, `property.CITY`, `assets`), asset index и blob.
- `chunked-assets`: демонстрирует chunked asset blobs через `chunks[]` в asset index.
- `objects-two-objects`: демонстрирует object index, passports и dataset selectors.
- `volume-set-real`: реальный multi-volume пример с descriptor и `.sitepack` volume files.
- `volume-set-index-only`: descriptor-only Volume Set без volume files.
