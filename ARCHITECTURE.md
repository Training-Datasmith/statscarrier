# Architecture: statscarrier

## Purpose

A PrestaShop statistics module that reports carrier usage and revenue share, allowing merchants to see which shipping carriers are most frequently selected by customers.

## Directory Structure

```
statscarrier.php   - Module class (ModuleGraph subclass); all business logic
upgrade/           - Migration scripts
tests/             - PHPUnit test stubs and PHPStan bootstrap
translations/      - Locale string overrides
```

## Key Design Decisions

- **ModuleGraph inheritance**: Extends PrestaShop's graph module to render a pie/bar chart of carrier usage over the selected period.
- **Date-range aware**: Filters orders by the admin stats date picker via the parent `getDate()` helper.

## Extension Points

- Override `getData()` to change grouping (e.g., by shipping zone rather than carrier name).

## Dependency Flow

```
statscarrier (ModuleGraph)
  └─> hookDisplayAdminStatsModules() — renders the carrier chart
  └─> getData()                      — carrier usage SQL
        └─> Db::getInstance()
```
