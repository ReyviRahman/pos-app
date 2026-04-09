# AGENTS.md

## Commands

```bash
composer dev          # Runs: php artisan serve + queue:listen + npm run dev concurrently
composer test         # Runs: php artisan config:clear && php artisan test
npm run dev           # Vite dev server
npm run build         # Vite production build
```

## Testing

- Uses **Pest** (not PHPUnit directly)
- Test database: SQLite in-memory
- Run single test: `./vendor/bin/pest --filter=test_name`

## Database

- Primary: MySQL (`sccr_resto`)
- Secondary: MySQL (`sccr_db`) - configured via `DB_SCCR_*` env vars

## Payment Services

- **Midtrans**: Server key in `MIDTRANS_SERVER_KEY`, client key in `MIDTRANS_CLIENT_KEY`
- **Xendit**: Secret key in `XENDIT_SECRET_KEY`

## Key Routes

- `/order` - POS (role: waiter) - Livewire
- `/payment` - Payment page (role: kasir) - Livewire
- `/history` - Transaction history
- `/products`, `/products/create`, `/products/{id}/edit` - Product management
- `/bahans` - Ingredient management
- `/inventory-movement` - Stock movements

## Notes

- Livewire components use `pages::` namespace
- Role middleware: `role:waiter`, `role:kasir`, `role:admin`, `role:manajer`
- Session driver: database
- Queue connection: database
