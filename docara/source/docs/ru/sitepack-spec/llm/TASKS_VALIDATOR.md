---
extends: _core._layouts.documentation
section: content
title: Tasks Validator
description: Tasks Validator
---

# Задача: SitePack Validator

## Цель
Реализовать валидатор распакованного пакета SitePack и валидатор заголовка encrypted envelope.

## Входные данные
1. Путь к распакованному пакету (каталогу).
2. Опционально: путь к `*.sitepack.enc.json`.
3. Опционально: путь к `sitepack.volumes.json`.

## Проверки пакета
- Корень MUST содержать `sitepack.manifest.json` и `sitepack.catalog.json`.
- JSON должен быть валидным.
- Проверить `sitepack.manifest.json` по `schemas/manifest.schema.json`.
- Проверить `sitepack.catalog.json` по `schemas/catalog.schema.json`.
- Все `manifest.artifacts` MUST присутствовать в catalog.
- Для каждого артефакта в catalog:
  - файл по `path` существует;
  - `size` совпадает с фактическим размером в байтах;
  - если `digest` указан, он совпадает с file `sha256`.
- NDJSON-артефакты: каждая строка должна быть валидным JSON-объектом.
- Для известных media types проверять каждую запись по соответствующей схеме:
  - entity-graph -> `schemas/entity.schema.json`
  - asset-index -> `schemas/asset-index.schema.json`
  - config-kv -> `schemas/config-kv.schema.json`
  - recordset -> `schemas/recordset.schema.json`
- Проверка asset-index должна поддерживать single-blob и chunked assets:
  - если `path` указан, blob-файл должен существовать и соответствовать `size` и `sha256`;
  - если `chunks` указан, каждый chunk-файл должен существовать и соответствовать своему `size` и `sha256`, а собранный asset должен соответствовать top-level `size` и `sha256`.
- Проверять кодирование relations для сущностей: `relations` MUST быть объектом, который сопоставляет keys с `Link[]`, где Link — строка или объект с `ref` и опциональным `meta`.
- Неизвестный `mediaType` MUST пропускаться, но MUST логироваться.

## Проверки Envelope
Если передан путь к `*.sitepack.enc.json`:
- JSON должен быть валидным.
- Проверить по `schemas/envelope.schema.json`.
- Проверить, что `payload.payloadDigest` существует и соответствует `sha256:<hex>`.

## Проверки Volume Set
Если передан путь к `sitepack.volumes.json`:
- JSON должен быть валидным.
- Проверить по `schemas/volume-set.schema.json`.
- Для каждого тома проверить `sha256` и `size` файла тома.
- Если том зашифрован age, использовать указанный envelope header, чтобы найти и расшифровать payload, либо вернуть понятную ошибку unsupported.
- Распаковать тома по возрастанию индекса во временный каталог и проверить собранный пакет обычным способом.

## Отчет
Записать `reports/validate.json` рядом с пакетом. Минимальная структура:
```json
{
  "ok": true,
  "errors": [],
  "warnings": [],
  "summary": {
    "artifactsTotal": 0,
    "artifactsValidated": 0,
    "unknownMediaTypes": 0,
    "ndjsonLines": 0
  }
}
```
- `ok=false`, если есть errors.
- Использовать machine-readable codes и human-readable messages в `errors` и `warnings`.
