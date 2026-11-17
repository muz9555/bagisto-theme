# Verification — 17 November 2026

Validated on PHP 8.5.12 with the local Bagisto 2.5.x-dev application. Standalone package dependencies resolved Laravel 13.42.0 and Pest 5.3.7.

- `composer install`: standalone development dependencies installed successfully.
- `composer validate --strict` and `composer lint`: passed.
- `composer test`: catalog-distribution integrity passed, including unique identifiers, category ordering, source URLs, prices and all ten bundled product images.
- `BAGISTO_PATH=/absolute/path/to/bagisto composer test:bagisto`: nine tests passed, covering import and repeat-import behavior, zero-stock saleability, currency guards, view resolution, native checkout fallback, subdirectory URLs, missing CMS guide suppression, imported CMS links and repeated theme activation.
- Installer checks use temporary public/config/bootstrap destinations and preserve channel name, logo, favicon, locales, currencies, inventory sources and merchant configuration.
- Read-only installation/content checks passed and rendered the populated hero, product tabs, brand spotlights, comparisons and footer.
- A separate application fixture removed manual MX Technology provider registration and supplied a Composer discovery entry. It resolved the external package's Blade templates and rendered the complete homepage while retaining native checkout views.
- Theme JavaScript syntax checks passed. The packaging changes do not replace Bagisto's Shop bundle or payment views.
- No catalog cleanup, real order or payment was performed by theme installation.

The included desktop/mobile screenshots show the existing theme design. GitHub CI is configured for PHP 8.4 and 8.5; local execution used PHP 8.5. Earlier Bagisto/Laravel releases are not claimed compatible. The homepage is catalog/configuration-driven rather than controlled by the default Shop's drag-and-drop homepage sections.
