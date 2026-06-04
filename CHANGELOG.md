# Changelog — Qoliber Nebula

All notable changes to the Nebula admin theme are recorded here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/);
the project aims to follow Semantic Versioning.

## [0.9.0]

### Added

- **Five modules promoted to the public package**: `Qoliber_NebulaCurrency`,
  `Qoliber_NebulaDirective`, `Qoliber_NebulaReports`, `Qoliber_NebulaSystem`,
  and `Qoliber_NebulaUser`. The package now ships 16 modules plus the theme.

### Changed

- `registration.php` updated to list the published modules.
- Version bumped to `0.9.0`.

### Security

- **Grid request hardening** — whitelist filter and sort columns against the
  grid definition and clamp `pageSize` (`MAX_PAGE_SIZE = 200`) across all data
  providers (`CollectionProvider`, `ProductProvider`, `CustomerGridProvider`,
  `SalesRuleCouponsGridProvider`, `AgreementGridProvider`, `AttributeSetProvider`).
- **Media browser** — `MediaPicker` rejects path-traversal segments and asserts
  the resolved file stays under the media root before deletion.
- **Toast notifications** — render admin messages as text (`textContent`),
  closing a DOM-XSS vector.
- `DefinitionAccessControl` treats an empty `acl` string as unset and falls back
  to `Magento_Backend::admin` (default-deny).

### Internal

- Snippet templates no longer use `ObjectManager::getInstance()`; ViewModels are
  injected via `SnippetViewModelRegistry` (or layout XML), throwing if a
  `view_model` is missing instead of silently service-locating.
- Complete PHPDoc coverage on changed PHP (FQDN `@param`/`@return`).

## [0.8.0]

### Added

- Initial public release.
