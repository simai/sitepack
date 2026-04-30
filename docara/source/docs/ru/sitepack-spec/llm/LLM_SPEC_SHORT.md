---
extends: _core._layouts.documentation
section: content
title: Llm Spec Short
description: Llm Spec Short
---

# SitePack v0.4.0 — краткая спецификация

## 1. Назначение
SitePack — формат упаковки данных сайта для экспорта и импорта. Пакет включает обязательные метаданные и набор артефактов.

## 2. Контейнер
- Каноническое расширение: `.sitepack`.
- Контейнер по умолчанию: ZIP.
- Импортер MUST определять формат контейнера по содержимому.
- TAR MAY поддерживаться дополнительно.

## 2.1 Volume Sets (поставка)
- Входной файл: `sitepack.volumes.json` (`application/vnd.sitepack.volume-set+json`).
- `maxPartSize` SHOULD быть `104857600` байт (100 MiB).
- Томы являются ZIP-файлами `.sitepack`; распаковываются по возрастанию индекса в один каталог.
- Перед распаковкой проверяйте `sha256` и `size` каждого тома.
- Если `encryption.scheme = "age"`, файл тома зашифрован, а `envelopeFile` указывает на заголовок SitePack envelope.

## 3. Обязательные файлы
Корень распакованного пакета MUST включать:
- `sitepack.manifest.json`
- `sitepack.catalog.json`

## 4. Manifest (кратко)
`sitepack.manifest.json` включает:
- `spec`: `{ name: "sitepack", version: "0.4.0" }`
- `package.id`
- `createdAt` (date-time)
- `profiles` (массив строк)
- `artifacts` (массив идентификаторов артефактов)

## 5. Catalog (кратко)
`sitepack.catalog.json` включает массив артефактов, каждый содержит:
- `id`
- `mediaType`
- `path`
- `size`
- `digest` (SHOULD, sha256)

## 5.1 Chunked assets
- Записи asset index бывают single-blob (`path`) или chunked (`chunks[]`).
- Chunked assets собираются конкатенацией чанков по возрастанию `index`.
- Импортеры MUST проверять hash/size каждого чанка и общий hash/size asset.

## 6. Core media types (MUST understand)
- `application/vnd.sitepack.entity-graph+ndjson`
- `application/vnd.sitepack.asset-index+ndjson`
- `application/vnd.sitepack.config-kv+ndjson`
- `application/vnd.sitepack.recordset+ndjson`

## 7. Profiles
- `config-only`
- `content-only`
- `content+assets`
- `full`
- `full+code`
- `snapshot` (описательный profile)

## 8. Relations и кодирование Link
- `relations` — объект, который сопоставляет ключи связей с `Link[]`.
- Link — строка или `{ "ref": "<string>", "meta"?: { ... } }`.
- Рекомендуемые ключи включают `property.<CODE>` и `field.<CODE>`.
- Внешние ссылки используют формат URN, например `urn:<namespace>:<type>:<id>`.
- Неразрешенные ссылки MUST фиксироваться как warnings и MUST NOT быть fatal.

## 9. Обработка неизвестного
- Неизвестный `mediaType`: MUST пропустить и залогировать.
- Неизвестный `entity.type`: MUST NOT приводить к ошибке; MAY быть пропущен или импортирован как opaque.

## 10. Digest и size
- Формат `digest`: `sha256:<hex>`.
- В v0.4 `digest` SHOULD, но `size` MUST.

## 11. Безопасность импорта
- Блокировать path traversal, absolute paths, null bytes.
- MUST задаваться лимиты размера и количества файлов.
- Не выполнять код автоматически.
- Не применять секретную конфигурацию автоматически.

## 12. Encrypted Envelope (optional)
Для передачи:
- `*.sitepack.enc` — age-зашифрованные байты исходного `.sitepack`.
- `*.sitepack.enc.json` — публичный JSON-заголовок.

Заголовок MUST соответствовать `schemas/envelope.schema.json` и включать `payload.payloadDigest` (sha256).
После расшифровки пакет MUST проверяться как обычный SitePack.

## 13. Optional extensions
- `Capabilities`: `application/vnd.sitepack.capabilities+json`
- `Transform Plan`: `application/vnd.sitepack.transform-plan+json`
