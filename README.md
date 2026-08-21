# woodoo

[![CI](https://github.com/AnasGreen/woodoo/actions/workflows/ci.yaml/badge.svg)](https://github.com/AnasGreen/woodoo/actions/workflows/ci.yaml)

A production-oriented, open-source synchronization platform for connecting Odoo and WooCommerce. This repository currently provides the application foundation, persistence model, asynchronous processing infrastructure, and development tooling.

> **Project status:** active early development. Product, category, brand, order, and webhook synchronization features are **not implemented yet**. No Odoo or WooCommerce API calls are made by this version.

## Architecture

The application is a modular monolith. Modules remain in one Symfony deployment while keeping responsibilities explicit:

- `Odoo` and `WooCommerce` own provider-specific concerns.
- `Mapping` owns external identifier mappings for products, categories, brands, and orders.
- `Sync` owns run history, errors, webhook metadata, and workflow state.
- `Message` and `MessageHandler` are reserved for Symfony Messenger contracts and consumers.
- `Shared` contains the small set of concepts genuinely shared across modules.

PostgreSQL is the source of record. Redis backs the asynchronous Messenger transport; failed messages use Doctrine storage. Webhook bodies are not persisted by default—only a SHA-256-compatible hash and non-sensitive event metadata are modeled.

## Requirements

- Docker Engine with Docker Compose v2 (recommended), or
- PHP 8.3+, Composer 2, PostgreSQL 16+, and Redis 7+

Symfony is pinned to the 7.4 LTS line and runs on PHP 8.3.

## Installation with Docker

```bash
cp .env.example .env.local
docker compose build
docker compose up -d database redis app nginx worker
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction
```

The health endpoint is then available at <http://localhost:8080/health>. Change `HTTP_PORT` in `.env.local` if port 8080 is occupied.

To stop the stack without deleting database data:

```bash
docker compose down
```

## Local installation

```bash
cp .env.example .env.local
composer install
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate --no-interaction
```

Point `DATABASE_URL` and `MESSENGER_TRANSPORT_DSN` at locally available PostgreSQL and Redis services. Run a worker with:

```bash
php bin/console messenger:consume async --time-limit=3600 --memory-limit=256M
```

## Environment configuration

Copy `.env.example` to `.env.local` and replace placeholders locally. Odoo settings use `ODOO_URL`, `ODOO_DATABASE`, `ODOO_USERNAME`, `ODOO_API_KEY`, and `ODOO_BRAND_ATTRIBUTE_ID`. WooCommerce settings use `WOOCOMMERCE_URL`, `WOOCOMMERCE_CONSUMER_KEY`, and `WOOCOMMERCE_CONSUMER_SECRET`.

Never commit `.env.local`, credentials, customer data, or production URLs. API secrets must never be included in logs or exception messages. For production, inject secrets through the deployment platform rather than storing them in files.

## Tests and quality checks

Run the complete quality suite inside the application container:

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/phpstan analyse --no-progress
docker compose run --rm app vendor/bin/php-cs-fixer fix --dry-run --diff
```

The equivalent host commands are the same without `docker compose run --rm app`. GitHub Actions runs all four checks on pushes and pull requests.

To verify Doctrine metadata and migration status against the running database:

```bash
docker compose exec app php bin/console doctrine:schema:validate
docker compose exec app php bin/console doctrine:migrations:status
```

## Contributing

Keep modules cohesive, add tests with behavior changes, and avoid introducing service boundaries until operational needs justify them. Run the full quality suite before opening a pull request.

## License

Released under the [MIT License](LICENSE).

