---
extends: _core._layouts.documentation
section: content
title: Readme
description: Readme
---

# sitepack-tools-php

CLI-валидатор для распакованных пакетов SitePack v0.4.0 на PHP 8.1+. Он проверяет manifest, catalog, artifacts и NDJSON по JSON Schema draft 2020-12 и записывает `reports/validate.json`.

## Требования
- PHP 8.1+
- composer

## Установка
```
composer install
```

## Использование
Проверить распакованный пакет:
```
./bin/sitepack-validate package /path/to/unpacked/sitepack
```

Проверить с profile:
```
./bin/sitepack-validate package /path --profile content+assets
```

Проверить заголовок encrypted envelope:
```
./bin/sitepack-validate envelope /path/to/example.sitepack.enc.json --check-payload-file
```

Проверить набор томов:
```
./bin/sitepack-validate volumes /path/to/sitepack.volumes.json
```

Опции:
- `--schemas=<dir>` — путь к каталогу схем (по умолчанию `./schemas`).
- `--no-digest` — пропустить проверку digest.
- `--strict` — считать warnings ошибками (exit code 1).
- `--check-asset-blobs` — проверять наличие и целостность файлов, указанных в asset-index, включая chunked assets.
- `--format=json|text` — формат консольного вывода.
- `--quiet` — минимальный вывод.

## Отчет
Отчет записывается в:
```
<packageRoot>/reports/validate.json
```

Для envelope mode отчет записывается рядом с header file в `reports/validate.json`.

## Поведение
- Неизвестный `mediaType` не приводит к ошибке валидации: path/size/digest все равно проверяются, content пропускается с warning.
- Пустые NDJSON-строки пропускаются с warning.
- Дескрипторы Volume Set могут проверяться и собираться командой `volumes`.

## Примеры
Запустить проверку примеров для `sitepack-spec/examples`:
```
php scripts/validate-examples.php /path/to/sitepack-spec/examples
```
