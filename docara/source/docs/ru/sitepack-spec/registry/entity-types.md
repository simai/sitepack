---
extends: _core._layouts.documentation
section: content
title: Типы сущностей
description: Типы сущностей
---

# Реестр типов сущностей

## Базовые типы
- `content.page` — страница.
- `content.post` — запись/новость.
- `content.item` — элемент каталога или общая запись.
- `taxonomy.category` — категория.
- `taxonomy.tag` — тег.
- `route.redirect` — правило перенаправления.

## Рекомендуемые базовые типы
- `system.user` — пользователь (автор).

## Рекомендуемые ключи и шаблоны связей
- `assets`: `Link[]` на идентификаторы assets (`asset_*`).
- `parent`: `Link[]`, обычно один элемент, на идентификаторы сущностей.
- `children`: `Link[]` на идентификаторы сущностей.
- `categories`: `Link[]` на идентификаторы taxonomy category.
- `tags`: `Link[]` на идентификаторы taxonomy tag.
- `author`: `Link[]` на идентификаторы `system.user` или внешних пользователей.
- `related`: `Link[]` для общих связей.
- `property.<CODE>`: `Link[]` для CMS-свойств.
  - `CODE` определяется экспортером; импортеры **SHOULD** по умолчанию считать его регистрозависимым.
- `field.<CODE>`: `Link[]` для CMS-полей.
- Внешние ссылки:
  - формат URN: `urn:<namespace>:<entityType>:<nativeId>`.
  - примеры: `urn:bitrix:crm.deal:123`, `urn:bitrix:task:456`.
  - URN не гарантированно разрешимы между системами; храните их как информационные ссылки, если конкретная поддержка не реализована.

Примеры:
- `property.BRAND`: `["ent_brand_1"]`
- `field.RELATED_PRODUCTS`: `[{ "ref": "ent_200", "meta": { "role": "cross-sell" } }]`
- `property.CRM_DEAL`: `["urn:bitrix:crm.deal:123"]`
