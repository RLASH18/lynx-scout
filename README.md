# Lynx Scout

> **Automated performance intelligence and recommendations for Laravel.**

Lynx Scout is an automated performance intelligence and recommendation package for Laravel 13. It observes application runtime behavior, detects potential performance bottlenecks, analyzes root causes, and provides actionable recommendations.

## Product Philosophy

- **Observation First**: Transparently collects query and request performance metrics.
- **Recommendation Only**: Strictly advisory. Never mutates your application code, migrations, or database.
- **Evidence-Based**: Every finding is backed by observed metrics, execution times, and request contexts.
- **Impact Prioritization**: Ranks findings according to estimated business and performance impact.

## Installation

```bash
composer require rlash18/lynx-scout --dev
```

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.
