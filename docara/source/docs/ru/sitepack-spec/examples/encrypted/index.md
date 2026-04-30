---
extends: _core._layouts.documentation
section: content
title: Readme
description: Readme
---

# Зашифрованный envelope

Этот пример содержит только публичный заголовок `example.sitepack.enc.json`.
Бинарный файл `example.sitepack.enc` в репозиторий не включен.

Расшифровка Age:
```
age -d example.sitepack.enc > output.sitepack
```

После расшифровки `output.sitepack` должен проверяться как обычный пакет SitePack.
