# AGENTS.md - Repository Guidelines

## Project Overview
Laravel 12 POS (Point of Sale) application with Livewire 4, Alpine.js, Tailwind CSS, and Pest testing.

## Build / Dev / Test Commands

### Development
```bash
composer run dev          # Full dev: server + queue + vite (recommended)
npm run dev               # Vite dev server only
composer run setup        # Install deps, generate key, migrate, npm install & build
```

### Building
```bash
npm run build             # Compile assets for production
```

### Testing (Pest)
```bash
composer run test         # Run full test suite
php artisan test          # Run full test suite (alternative)
vendor/bin/pest           # Run full test suite (direct)

# Run a single test file
vendor/bin/pest tests/Feature/ExampleTest.php

# Run a single test by name
vendor/bin/pest --filter="it_returns_a_successful_response"

# Run only Unit or Feature tests
vendor/bin/pest tests/Unit
vendor/bin/pest tests/Feature
```

### Linting / Formatting
```bash
vendor/bin/pint           # Format all PHP files (Laravel Pint)
vendor/bin/pint --test    # Check formatting without modifying
```

## Code Style

### PHP Conventions
- **PHP Version**: 8.2+
- **Indentation**: 4 spaces (see `.editorconfig`)
- **Line endings**: LF
- **Encoding**: UTF-8
- **Trailing newline**: Required
- **Trailing whitespace**: Trimmed (except `.md` files)

### Naming Conventions
- **Controllers**: PascalCase, suffixed with `Controller` (e.g., `ProfileController`)
- **Models**: PascalCase, singular (e.g., `Product`, `Transaction`)
- **Livewire/Volt components**: kebab-case route names, `⚡` prefix for Volt blade files
- **Migrations**: snake_case with descriptive names
- **Tests**: `it()` / `test()` closures with descriptive string names

### Imports & Namespaces
- PSR-4 autoloading: `App\` → `app/`, `Tests\` → `tests/`
- Group imports where applicable; use fully qualified class names for facades
- Laravel facades imported explicitly (e.g., `use Illuminate\Support\Facades\Route`)

### Models
- Use `HasFactory` trait on all models
- Define `$fillable` arrays explicitly (no `$guarded = []`)
- Type-hint relationship return types (e.g., `BelongsToMany`, `HasMany`)
- Use `withPivot()` and `withTimestamps()` on pivot relationships
- DocBlock PHPDoc annotations for `$fillable` and `$hidden` arrays

### Controllers & Routing
- Base controller: `App\Http\Controllers\Controller` (abstract, empty)
- Use route groups with middleware for auth-protected routes
- Livewire routes use `Route::livewire()` helper
- Named routes with dot notation (e.g., `product.index`, `kasir.index`)

### Blade / Views
- Tailwind CSS for styling with `@tailwindcss/forms`
- Alpine.js for client-side interactivity
- Volt components use `⚡` prefix in filenames (e.g., `⚡index.blade.php`)
- Reusable UI components in `resources/views/components/`
- Layouts extend from `layouts/app.blade.php` or `layouts/guest.blade.php`

### Error Handling
- Use Laravel's built-in exception handling
- Form validation via Form Request classes in `app/Http/Requests/`
- Return appropriate HTTP status codes in API/test assertions

## Testing Conventions
- **Framework**: Pest 3.x (not PHPUnit-style classes)
- **Feature tests** use `RefreshDatabase` trait (configured in `tests/Pest.php`)
- **SQLite in-memory** database for testing (see `phpunit.xml`)
- Use `it()` for feature tests, `test()` for unit tests
- Custom expectations can be added in `tests/Pest.php`
- Test helpers defined as global functions in `tests/Pest.php`

## Architecture
```
app/
  Http/Controllers/    # HTTP controllers
  Http/Requests/       # Form request validation
  Models/              # Eloquent models
  Providers/           # Service providers
  View/Components/     # Blade components
resources/views/
  components/          # Reusable Blade components
  layouts/             # App and guest layouts
  pages/               # Livewire/Volt page components (⚡ prefix)
  auth/                # Authentication views
database/
  migrations/          # Schema migrations
  factories/           # Model factories
  seeders/             # Database seeders
tests/
  Feature/             # HTTP/integration tests
  Unit/                # Unit tests
  Pest.php             # Pest configuration and helpers
  TestCase.php         # Base test case
```

## Key Models
- `User` - Authentication (Laravel Breeze)
- `Product` - Menu items with price
- `Ingredient` - Raw materials with stock tracking
- `MenuIngredient` - Pivot: product-ingredient relationships
- `Transaction` / `TransactionDetail` - Sales records
- `InventoryMovement` - Stock movement tracking
- `StockTake` - Inventory audits
