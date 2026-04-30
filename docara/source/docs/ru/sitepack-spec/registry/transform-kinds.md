---
extends: _core._layouts.documentation
section: content
title: Виды преобразований
description: Виды преобразований
---

# Реестр Transform Kind

## rewrite.links
Переписывает ссылки внутри контента и assets.

Пример параметров:
```json
{
  "fromHost": "old.example.com",
  "toHost": "new.example.com",
  "rewriteRelative": true
}
```

## entity.map
Сопоставляет типы или атрибуты сущностей.

Пример параметров:
```json
{
  "typeMap": {
    "content.post": "content.article"
  },
  "attributeMap": {
    "content.title": "title"
  }
}
```

## recordset.map
Преобразует recordsets: переименовывает поля, применяет фильтры.

Пример параметров:
```json
{
  "recordset": "example_table",
  "rename": {
    "old_field": "new_field"
  }
}
```

## config.map
Преобразует записи конфигурации.

Пример параметров:
```json
{
  "namespace": "public",
  "keyMap": {
    "site.title": "app.title"
  }
}
```

## snapshot.emitFiles
Создает snapshot-файлы (HTML/JSON).

Пример параметров:
```json
{
  "outputDir": "snapshot/",
  "emitAssets": true
}
```
