# Adapter proof: site-structure

This proof checks whether the `site-structure` profile can describe website
structure without depending on one CMS model.

## Portable source

The profile requires one core artifact:

- `application/vnd.sitepack.site-map+json`

The Site Map describes:

- site identity and locales;
- routes and page tree;
- menus;
- redirects;
- references from pages to portable content entities, assets, snapshots, or
  extension artifacts.

It does not require a Bitrix iblock, Laravel model, WordPress post id, database
table, template engine, or admin UI concept.

## Adapter mapping matrix

| SitePack concept | Docara/Larena-style adapter | WordPress-style adapter | Static-site generator adapter |
| --- | --- | --- | --- |
| `site.id`, `site.title` | Documentation site identity | Site title and import namespace | Site metadata |
| `locales` | Locale-aware documentation tree | Site languages or plugin-managed locales | Locale folders or build config |
| `pages[].path` | Route/page slug | Page permalink | Output path |
| `pages[].parentId` | Nested docs navigation | Parent page | Directory or navigation parent |
| `pages[].entityRefs` | Content record or markdown source | Page/post body source | Markdown/front matter source |
| `menus[]` | Navigation tree | Menu terms/items | Navigation config |
| `redirects[]` | Route redirect rules | Redirect plugin or rewrite rules | Redirect file or host config |
| `meta` | Adapter-specific page hints | Import notes or custom fields | Front matter/build hints |

## Extension boundary

Adapter-specific data remains outside SitePack Core:

- Docara/Larena layout hints should use an `org.simai.larena.*` extension.
- WordPress post type, block editor metadata, and plugin-specific data should
  use an `org.wordpress.*` extension.
- Static-site generator front matter that has no portable meaning should use a
  generator-specific extension.

An importer may preserve unsupported extension artifacts, skip optional ones
with warnings, or pause when a required extension blocks the claimed import
profile.

## Example packages

The proof currently uses two validated packages:

- `examples/small-docs-site`: documentation-site structure with a
  Docara/Larena adapter hint.
- `examples/small-blog-site`: blog/page structure with a WordPress-style
  adapter hint.

Both packages use the same portable profile contract:

- `site-structure`
- `content-assets`

Both packages keep portable page/content structure in core artifacts and use
extensions only as adapter hints.

## Pass criteria

The `site-structure` profile passes this proof when:

- both example packages validate in Node and PHP reference tools;
- no example requires a CMS-specific object model to satisfy the core profile;
- each platform-specific detail can be moved to an extension or importer report;
- unsupported adapter behavior can be reported without changing SitePack Core.
