---
extends: _core._layouts.documentation
section: content
title: Readme
description: Readme
---

# sitepack-tools-node

CLI-валидатор для распакованных пакетов SitePack v0.4.0. Он проверяет manifest, catalog, artifacts и content по схемам, а затем записывает `reports/validate.json`.

## Требования
- Node.js >= 18
- npm

Примечание: валидатор использует Ajv 2020-12 (`ajv/dist/2020`), потому что схемы SitePack используют draft 2020-12.

## Установка
Локальное использование:
```
npm i
npm link
```

## Использование
Проверить распакованный пакет:
```
sitepack-validate /path/to/unpacked/sitepack
```

Проверить с profile:
```
sitepack-validate /path --profile content+assets
```

Проверить заголовок encrypted envelope:
```
sitepack-validate envelope /path/to/example.sitepack.enc.json --check-payload-file
```

Проверить набор томов:
```
sitepack-validate volumes /path/to/sitepack.volumes.json
```

Создать набор томов из распакованного пакета:
```
sitepack-volumes create /path/to/unpacked/sitepack /path/to/output --max-part-size 104857600
```

Рекомендуемое значение `--max-part-size` — `104857600` (100 MiB), если не нужны меньшие части.

Извлечь тома в каталог (ZIP overlay):
```
sitepack-volumes extract /path/to/sitepack.volumes.json /path/to/output
```

Опции:
- `--schemas <dir>` — путь к JSON-схемам (по умолчанию `./schemas`).
- `--no-digest` — пропустить проверку digest.
- `--strict` — считать warnings ошибками (exit code 1).
- `--check-asset-blobs` — проверять asset blob files, указанные в asset-index, включая chunked assets.
- `--format text|json` — формат консольного вывода.
- `--quiet` — минимальный консольный вывод.

## Что проверяется
1. Наличие и валидность схем `sitepack.manifest.json` и `sitepack.catalog.json`.
2. Для каждого catalog artifact:
   - безопасный path: без absolute paths и traversal;
   - файл существует;
   - `size` совпадает;
   - `digest` совпадает, если указан.
3. Core NDJSON types проверяются построчно по схемам:
   - entity-graph, asset-index, config-kv, recordset;
   - asset-index поддерживает single-blob и chunked assets.
4. JSON artifacts (capabilities/transform-plan) проверяются как полноценные JSON-документы.
5. Object index/passport artifacts проверяются и сверяются между собой, если object index присутствует.
6. Неизвестный `mediaType` не приводит к ошибке валидации: file/size/digest проверяются, content пропускается с warning.
7. Дескрипторы Volume Set могут проверяться и собираться командой `volumes`.

Примечание по пустым строкам NDJSON: пустая строка пропускается с warning.

## Profile mode
Опция `--profile` проверяет, что profile присутствует в `manifest.profiles`, и валидирует artifacts для этого profile. В v0.4 profiles являются массивом, поэтому валидатор использует fallback к `manifest.artifacts`, если map profile-to-artifact недоступен.

## Отчет
После проверки инструмент записывает:
```
<packageRoot>/reports/validate.json
```

Структура отчета:
```json
{
  "tool": { "name": "sitepack-validate", "version": "0.4.0" },
  "startedAt": "...",
  "finishedAt": "...",
  "target": { "type": "package|envelope|volume-set", "path": "..." },
  "summary": {
    "errors": 0,
    "warnings": 0,
    "artifactsTotal": 0,
    "artifactsValidated": 0,
    "artifactsSkipped": 0,
    "ndjsonLinesValidated": 0
  },
  "artifacts": [],
  "messages": []
}
```

## Exit codes
- `0` — ошибок нет, warnings допустимы.
- `1` — ошибки валидации или warnings в strict mode.
- `2` — ошибка использования CLI.
