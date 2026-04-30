---
extends: _core._layouts.documentation
section: content
title: Tasks Importer
description: Tasks Importer
---

# Задача: SitePack Importer

## Цель
Импортировать данные из распакованного пакета SitePack в целевую систему.

## Входные данные
Путь к распакованному пакету (каталогу).

## Требования
- Прочитать `sitepack.manifest.json` и `sitepack.catalog.json`.
- Определить profile(s) из `manifest.profiles`.
- Обработать артефакты из каталога:
  - для известных `mediaType` выполнить импорт;
  - неизвестные `mediaType` MUST пропускаться и логироваться.
- Неизвестный `entity.type` MUST NOT приводить к падению; MAY импортироваться как opaque.
- Учитывать `applyPolicy` для config, например `never` не применяется автоматически.
- Если существует поддерживаемый `Transform Plan`, MAY применить шаги по порядку.
- Обеспечить безопасность путей: без traversal, absolute paths и null bytes.
- Если передан дескриптор `sitepack.volumes.json`, проверить его, проверить `sha256`/`size` каждого тома и собрать тома во временный каталог перед импортом.
- Если запись тома использует `encryption.scheme = "age"`, импортер MUST использовать указанный envelope header, чтобы найти зашифрованный payload и расшифровать его перед сборкой, либо завершиться понятной ошибкой, если это не поддерживается.

## Chunked assets
- Записи asset index могут быть single-blob (`path`) или chunked (`chunks[]`).
- Для chunked assets восстановить asset конкатенацией чанков по возрастанию `index`.
- Импортеры MUST проверять `sha256`/`size` каждого чанка и общий `sha256`/`size` asset.

## Relations
- Импортеры SHOULD использовать двухпроходную стратегию:
  1. создать сущности или placeholders и построить mapping `sitepackEntityId -> targetSystemId`;
  2. разрешить и применить связи через этот mapping.
- Если Link не удалось разрешить, это MUST быть записано как warning и MUST NOT быть fatal.

## Выходные данные
Создать `reports/import.json` минимум со структурой:
```json
{
  "packageId": "...",
  "profiles": ["..."],
  "imported": {
    "entities": 0,
    "assets": 0,
    "config": 0,
    "recordsets": 0
  },
  "skipped": {
    "unknownMediaTypes": 0,
    "unknownEntityTypes": 0
  },
  "warnings": [],
  "errors": []
}
```
