---
extends: _core._layouts.documentation
section: content
title: Спецификация
description: Спецификация
---

# SitePack v0.4.0 — спецификация

## Keywords
Ключевые слова **MUST**, **SHOULD** и **MAY** интерпретируются так, как описано в RFC 2119.

## 1. Обзор и цели
SitePack — открытый формат упаковки данных сайта для передачи между системами. Формат определяет обязательные метаданные пакета, каталог артефактов и содержимое артефактов. Цели:

- совместимость экспорта/импорта между платформами;
- предсказуемая структура и безопасный парсинг;
- расширяемость без нарушения базовой совместимости.

## 2. Термины
- **Package**: контейнер с файлами SitePack.
- **Artifact**: файл внутри пакета, описанный в каталоге.
- **Volume Set**: дескриптор поставки разделенного пакета.
- **Catalog**: файл `sitepack.catalog.json` со списком артефактов.
- **Entity**: объект контента: страница, запись, элемент и т. д.
- **Recordset**: табличные данные, записи NDJSON.
- **Asset**: бинарный ресурс: изображение, файл, media.
- **Object Index**: человекочитаемый список объектов со ссылками на паспорта.
- **Object Passport**: метаданные объекта со ссылками на datasets и artifacts.
- **Dataset Selector**: селектор, который ссылается на artifact dataset и опциональный фильтр.
- **Profile**: профиль пакета, определяющий ожидаемые artifacts.
- **Provenance**: метаданные происхождения: source, version, authors; опциональны в metadata.
- **Capabilities**: возможности инструмента: export/import.
- **Transform Plan**: план преобразований, применяемых при import/export.
- **Envelope**: внешний JSON-заголовок для зашифрованной передачи.

## 3. Контейнер и расширения файлов
- Каноническое расширение пакета: `.sitepack`.
- Контейнер по умолчанию: ZIP.
- Реализации **MUST** поддерживать ZIP.
- Поддержка TAR **MAY** быть реализована дополнительно.
- Импортеры **MUST** определять формат контейнера по содержимому, а не по расширению.

### 3.1 Поставка: Volume Sets
Volume Sets описывают разделенный пакет, распространяемый несколькими томами.

- Входной файл: `sitepack.volumes.json` (media type `application/vnd.sitepack.volume-set+json`).
- `maxPartSize` **SHOULD** быть установлен в `104857600` байт (100 MiB), если инструмент не требует другого значения.
- Bootstrap-том **SHOULD** иметь `volumeIndex = 1` и **MUST** содержать `sitepack.manifest.json` и `sitepack.catalog.json`.
- Каждый файл тома — обычный ZIP `.sitepack`. Чтобы собрать пакет, импортер **MUST** распаковать тома по возрастанию индекса в один временный каталог.
- Собранный каталог является корнем пакета; более поздние тома **MAY** перезаписывать более ранние файлы при совпадении путей.
- Перед распаковкой импортеры **MUST** проверить `sha256` и `size` каждого файла тома по дескриптору Volume Set.
- Томы **MAY** шифроваться по отдельности через SitePack envelope + age.
- Если `encryption.scheme = "age"`, файл тома является зашифрованным payload; `envelopeFile` **MUST** указывать на соответствующий заголовок SitePack envelope (`*.sitepack.enc.json`).

## 4. Обязательные файлы
Корень распакованного пакета **MUST** содержать:

- `sitepack.manifest.json`
- `sitepack.catalog.json`

### 4.1 Формат sitepack.manifest.json (минимальные поля)
- `spec.name` = `sitepack`.
- `spec.version` — версия формата (SemVer).
- `package.id` — идентификатор пакета.
- `createdAt` — timestamp создания на верхнем уровне manifest.
- `profiles` — массив имен profiles (strings), описывающих назначение; см. раздел 9.
- `artifacts` — массив `artifact.id`, присутствующих в пакете.
- Дополнительные поля разрешены: extensions, provenance и т. д.

### 4.2 Формат sitepack.catalog.json (минимальные поля)
- `artifacts[]` — список объектов.
- Каждый `artifact` включает: `id`, `mediaType`, `path`, `size`; digest опционален.
- Формат `digest`: `sha256:<hex>`.
- Дополнительные поля разрешены, например `annotations`, schema hints.

### 4.3 Правило интерпретации
- Импортер **MUST** использовать manifest+catalog как источник истины о содержимом пакета; расширения файлов **MUST NOT** считаться источником истины.

## 5. Artifacts
Artifact — файл внутри пакета, описанный в catalog. Catalog содержит массив объектов artifact с полями:

- `id` (**MUST**): уникальный идентификатор artifact внутри пакета.
- `mediaType` (**MUST**): media type artifact.
- `path` (**MUST**): относительный путь внутри пакета.
- `size` (**MUST**): размер файла в байтах.
- `digest` (**SHOULD** в v0.4): checksum файла.
- `annotations` (**MAY**): объект расширения.

### 5.1 Assets: chunked blobs
Записи asset в asset index могут представлять один blob (`path`) или chunked blob (`chunks`).

- Если присутствует `chunks`, итоговые байты asset являются конкатенацией чанков по возрастанию `index`.
- Импортеры **MUST** проверить `sha256` и `size` каждого чанка, а затем проверить общий `sha256` и `size` asset.
- Экспортеры **SHOULD** держать размеры чанков не выше `maxPartSize` (по умолчанию 100 MiB).

### 5.2 Пути хранения blob
Asset blobs находятся по `path`, записанному в каждой записи asset-index.

- Импортеры **MUST** читать blobs точно из записанного `path` и **MUST NOT** предполагать фиксированную структуру каталогов.
- Каноническая рекомендация: `artifacts/assets/blobs/sha256/<sha256>[.<ext>]`.
- Альтернативные структуры, например `blobs/sha256/...`, **MAY** встречаться в legacy packages; tools **MUST** все равно следовать записанному `path`.

## 6. Entities (portable layer)

### 6.1 Relation links (Link) и кодирование relations

#### A) Поле `relations`
- `relations` — объект, который сопоставляет `relationKey` -> `Link[]`.
- Каждое значение relation **MUST** быть массивом.

#### B) Имена ключей relations
- Ключ relation **SHOULD** быть одним из:
  - well-known key из `registry/entity-types.md`, например `assets`, `parent`, `children`, `categories`, `tags`, `author`, `related`;
  - `property.<CODE>` для CMS-свойств, например `property.BRAND`, `property.RELATED_PRODUCTS`;
  - `field.<CODE>` для CMS-полей;
  - vendor-namespaced keys **MAY** использоваться, например `bitrix.property.BRAND`, но **SHOULD** избегаться в portable layer без необходимости.

#### C) Кодирование Link
Link **MUST** быть:

1. строкой (shorthand) с reference identifier; или
2. объектом:
   `{ "ref": "<string>", "meta"?: { ... } }`

Дополнительные правила:

- `ref` **MUST** быть строкой.
- `meta`, если присутствует, **MUST** быть JSON object.
- `meta` предназначен для non-normative hints, например `sourceField`, `role`, `cardinality`, и **MUST NOT** быть обязательным для корректности.

#### D) Цели reference
- `ref` **MAY** указывать на:
  - SitePack entity id, например `ent_...`;
  - SitePack asset id, например `asset_...`;
  - внешнюю ссылку URN, например `urn:<namespace>:<type>:<id>`.
- Импортеры **MUST NOT** падать на неизвестных URN namespaces; они **MUST** записать warning и продолжить.

#### E) Поведение импортера
- Импортеры **SHOULD** выполнять импорт в два прохода:
  1. создать entities или placeholders и построить mapping `sitepackEntityId -> targetSystemId`;
  2. разрешить и применить relations через mapping.
- Если Link не может быть разрешен:
  - это **MUST NOT** быть fatal;
  - это **MUST** быть записано как warning в import report;
  - это **MUST NOT** блокировать импорт других entities.

#### F) Примеры
Пример 1: shorthand string link.
```json
{
  "relations": {
    "property.BRAND": ["ent_brand_1"]
  }
}
```

Пример 2: link с meta.
```json
{
  "relations": {
    "related": [
      { "ref": "ent_42", "meta": { "role": "upsell" } }
    ]
  }
}
```

Пример 3: external URN link.
```json
{
  "relations": {
    "property.CRM_DEAL": ["urn:bitrix:crm.deal:123"]
  }
}
```

В репозитории есть канонический end-to-end пример `sitepack-spec/examples/cross-relations/`, который демонстрирует связи entity→entity (`property.BRAND`, `property.CITY`) и entity→asset (`assets`) через `artifacts/assets/index.ndjson` и `artifacts/assets/blobs/sha256/9809156062446115f511b5367e69e86c695987b3b90021634a4e059d8f497b45.png`.

## 7. Objects layer
Objects layer — опциональный человекочитаемый индекс поверх artifacts пакета.

### 7.1 Object index
- File path: `objects/index.json`.
- Media type: `application/vnd.sitepack.object-index+json`.
- `kind` **MUST** быть `object-index`.
- Каждая object entry **MUST** включать `id`, `type` и `passportPath`; title опционален.
- `passportPath` **MUST** быть package-relative path к JSON-паспорту объекта.
- `passportPath` **MUST** совпадать с `artifact.path` в catalog.

### 7.2 Object passports
- Media type: `application/vnd.sitepack.object-passport+json`.
- `kind` **MUST** быть `object-passport`.
- `id` **MUST** совпадать с `objectRef.id` и соответствующим `id` записи object index.
- Если присутствует `artifacts[]`, каждая запись **MUST** быть `artifact.id` из catalog.
- Если присутствует `datasets[]`, каждый `datasetSelector.artifactId` **MUST** быть `artifact.id` из catalog.

### 7.3 Dataset selectors
Dataset selectors ссылаются на shared datasets и опциональные filters:

- `artifactId` (**MUST**): `artifact.id` из catalog.
- `where` (**MAY**): массив conditions, объединенных логическим AND.
- Каждое condition:
  - `field` (**MUST**): JSON Pointer string, начинающаяся с `/`.
  - `op` (**MUST**): `=` или `in`.
  - `value` (**MUST**): primitive для `=`; массив primitives для `in`.

## 8. Core media types
Совместимый tool **MUST** понимать:

- `application/vnd.sitepack.entity-graph+ndjson`
- `application/vnd.sitepack.asset-index+ndjson`
- `application/vnd.sitepack.config-kv+ndjson`
- `application/vnd.sitepack.recordset+ndjson`

## 9. Profiles
Profiles определяют ожидаемые artifacts и назначение пакета:

- `config-only`: только configuration (key-value).
- `content-only`: content entities без assets.
- `content+assets`: content entities + asset index.
- `full`: content + assets + config + recordsets.
- `full+code`: `full` плюс code artifacts и software manifest.
- `snapshot`: полный static export/snapshot, описательный profile без implementation requirements в v0.4.

## 10. Unknown handling
- Неизвестный `mediaType`: импортер **MUST** пропустить artifact и **MUST** залогировать событие.
- Неизвестный `entity.type`: импортер **MUST NOT** падать; он **MAY** пропустить или импортировать как opaque.

## 11. Integrity and digest
- Формат digest: `sha256:<hex>`.
- В v0.4 `digest` в catalog **SHOULD** быть предоставлен, но не обязателен.
- `size` для каждого artifact **MUST** быть предоставлен.

## 12. Безопасность импорта
Импортеры **MUST** защищаться от:

- path traversal (`..`), absolute paths и null bytes в путях;
- чрезмерных размеров файлов и количества файлов: limits **MUST** enforced, конкретные значения **MAY** быть implementation-defined.

Дополнительные требования:

- Code artifacts **MUST NOT** устанавливаться или выполняться автоматически.
- Secret configs **MUST NOT** применяться автоматически.

## 13. Capabilities (optional extension)
Расширение для описания возможностей export/import инструмента. Media type:
`application/vnd.sitepack.capabilities+json`.

## 14. Transform Plan (optional extension)
Расширение для описания шагов преобразования. Media type:
`application/vnd.sitepack.transform-plan+json`.

## 15. Encrypted Envelope (optional extension)
Для безопасной передачи допускается внешний envelope:

- `*.sitepack.enc` — age-зашифрованный файл с байтами исходного `.sitepack`.
- `*.sitepack.enc.json` — публичный JSON-заголовок.

Требования:

- Заголовок **MUST** соответствовать `schemas/envelope.schema.json`.
- Заголовок **MUST** включать `payload.payloadDigest` для исходного `.sitepack`.

Пример расшифровки:
```
age -d input.sitepack.enc > output.sitepack
```

После расшифровки пакет **MUST** проверяться как обычный `.sitepack`.

## 16. Совместимость версий
SitePack использует SemVer.

- Несовпадение **major** означает несовместимость; импортер **MUST** отклонить такие packages.
- При совпадающем **major** импортер **MUST** принимать packages с **minor**, меньшим или равным поддерживаемой версии.
- Импортер **MAY** принять более высокий **minor**, если он может безопасно игнорировать неизвестные fields/artifacts.

## 17. Appendix: рекомендуемая структура каталогов
Рекомендуемая распакованная структура:
```
sitepack.manifest.json
sitepack.catalog.json
artifacts/
  entities/
  assets/
    blobs/
      sha256/
  config/
  recordsets/
  code/
```

Конкретные имена каталогов и наборы artifacts определяются profile и catalog.
