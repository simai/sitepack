---
extends: _core._layouts.documentation
section: content
title: SitePack 1.2
description: Универсальный формат переноса и резервного копирования сайтов
---

# SitePack 1.2

SitePack — открытый формат упаковки данных сайта для экспорта, резервного копирования, просмотра и импорта между системами. Каноническая спецификация хранится в `sitepack-spec/`.

Ключевые решения:

- Каноническое расширение пакета: `.sitepack`.
- Контейнер по умолчанию: ZIP. Формат контейнера определяется по содержимому, а не по расширению.
- Безопасная передача использует внешний age envelope: `*.sitepack.enc` + `*.sitepack.enc.json`.
- Связи стандартизированы через Link encoding с массивами в `relations`.
- Volume Sets описывают передачу разделенных пакетов (`sitepack.volumes.json`).
- Asset index поддерживает chunked blobs (`chunks[]`).
- Компактные и полные снимки различают внешние и встроенные файлы.
- Отделённая подпись Ed25519 защищает целостность пакета, не подменяя локальную модель доверия.
- Семантические профили и декларативные рецепты обеспечивают перенос без попарных матриц платформ.

Материалы документации:

- Спецификация: `SPEC.md`.
- Семантическое сопоставление и совместимость: `MAPPING.md`.
- JSON-схемы: `schemas/`.
- Реестры: `registry/`.
- Примеры пакетов: `examples/`.
- Release notes: `releases/`.
- Материалы для LLM: `llm/`.

Примеры:

- `cross-relations`: демонстрирует связи entity-to-entity и entity-to-asset через Link[] (`property.BRAND`, `property.CITY`, `assets`), asset index и blob.
- `chunked-assets`: демонстрирует chunked asset blobs через `chunks[]` в asset index.
- `objects-two-objects`: демонстрирует object index, passports и dataset selectors.
- `volume-set-real`: реальный multi-volume пример с descriptor и `.sitepack` volume files.
- `volume-set-index-only`: descriptor-only Volume Set без volume files.
- `semantic-mapping`: исходный raw-слой и переносимый семантический слой в одном пакете.
