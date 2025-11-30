# Flash Sale API (Laravel) - Scaffold

This repository is a **scaffold** implementing the Flash Sale Interview Task logic:
- Products, Holds, Orders, Webhook events
- Concurrency-safe hold creation (DB transactions & row locks)
- Hold expiry via queued job
- Webhook idempotency and out-of-order handling
- Seeders and basic PHPUnit tests

**Important:** This is a scaffold (collection of PHP files, migrations and tests). To run it as a real Laravel app:
1. Create a new Laravel project: `composer create-project laravel/laravel flash-sale-api`
2. Copy the files from this scaffold into your Laravel project (merge into `app/`, `database/`, `routes/`, etc.)
3. Run `composer install`, configure `.env`, `php artisan key:generate`
4. Run migrations: `php artisan migrate`
5. Seed products: `php artisan db:seed --class=ProductSeeder`
6. Start queue worker: `php artisan queue:work`
7. Serve: `php artisan serve`

Files included: migrations, models, controllers, jobs, seeders, routes/api.php, tests, README.

