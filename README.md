# Nebula — Modern Admin Theme for Magento 2 / Mage-OS

> **Replace Magento's legacy admin theme — bring it into the 21st century.**

Nebula modernizes the Magento Admin with a faster interface, cleaner workflows,
dark mode, and developer-friendly customization — **without forcing you to
replace Magento itself.** It runs alongside your existing store on
Magento 2.4.7+ (or Mage-OS 2.0+) and PHP 8.1+.

---

## Why Nebula

| | |
|---|---|
| ⚡ **Faster** | Native ES modules replace RequireJS; vanilla JS replaces jQuery; Alpine.js 3 replaces KnockoutJS. |
| 🎨 **Beautiful** | Tailwind CSS 4 styling, dark mode, and 18 built-in skins for client-specific branding. |
| 🧩 **Composable** | JSON-driven forms and grids plus reusable snippets — clearer definitions in place of sprawling XML UI Components. |
| 🛠️ **Developer-friendly** | A simpler, more maintainable admin stack. Easier grid and form extensions, fewer moving parts. |
| 🚀 **Zero build step** | Ships with compiled assets — install and go. |

### Who it's for

- **Store owners & admin teams** — faster daily order, catalog, and content
  workflows, with dark mode and pinned navigation. Modernize the back office
  without changing your commerce platform.
- **Developers** — a cleaner admin architecture with JSON definitions instead
  of XML UI patterns, and straightforward extension points.
- **Agencies** — quicker admin improvements, client branding via skins, and
  lower back-office customization costs.

---

## Highlights

- **JSON-driven UI** — admin forms and grids defined as JSON, with a library of
  reusable composable snippets.
- **Modern frontend** — Alpine.js 3, Tailwind CSS 4, native ES modules, no
  RequireJS/KnockoutJS/jQuery.
- **Dark mode + skins** — multiple built-in themes; brand per client.
- **Focused modules** — a clean, modular package (see below) you can adopt
  incrementally.
- **Well tested** — extensive PHPUnit and Vitest coverage, plus bridge tests for
  third-party compatibility.

---

## Installation

```bash
composer require qoliber/nebula-admin-theme
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
```

Then activate the **Nebula** theme under
**Stores → Configuration → Design** (adminhtml), or assign it as the admin
theme for your environment.

### Requirements

- Magento **2.4.7+** or Mage-OS **2.0+**
- PHP **8.1+**

---

## Modules

This package ships the following focused modules:

| Module | Responsibility |
|---|---|
| `Qoliber_Nebula` | Package umbrella + shared configuration |
| `Qoliber_NebulaComponent` | Core component framework (definitions, snippets, registries) |
| `Qoliber_NebulaForm` | JSON-driven admin forms |
| `Qoliber_NebulaGrid` | JSON-driven admin grids |
| `Qoliber_NebulaTheme` | Theme integration + data providers |
| `Qoliber_NebulaMenu` | Admin navigation / menu |
| `Qoliber_NebulaSkin` | Tailwind CSS styling and skins |
| `Qoliber_NebulaMedia` | Media browser / picker |
| `Qoliber_NebulaQuill` | Quill WYSIWYG editor integration |
| `Qoliber_NebulaDirective` | Widget / directive support |
| `Qoliber_NebulaStore` | Stores, websites, and design-config screens |
| `Qoliber_NebulaSystem` | System pages and tooling |
| `Qoliber_NebulaReports` | Reports grids |
| `Qoliber_NebulaCurrency` | Currency screens |
| `Qoliber_NebulaUser` | Admin users and roles |
| `Qoliber_NebulaUiRemoval` | Disables Magento's UI Component reader/generator where Nebula replaces it |

---

## License

Nebula is released under the **Nebula Community License (NCL)** — a
source-available license. See [LICENSE.md](LICENSE.md) for the full terms.

---

## Brought to you by

[**qoliber**](https://qoliber.com) and [**Siteation**](https://siteation.dev/)
