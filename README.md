# **Project Overview**
This project implements a minimal inventory reservation system with:

 - Holds: temporary reservations with TTL.

 - Orders: created from valid holds, idempotent by idempotency_key.

 - Payment Webhooks: idempotent, safe against retries and out-of-order delivery.

# **Assumptions & Invariants**
 * products.stock is the baseline total supply; availability = stock − active holds − completed orders.

 * Holds expire automatically after ttl_seconds.

 * Each hold can be consumed once.

 * Orders must be created from valid holds.

 * Payment webhook enforces idempotency and finalizes order state.

 * Cache (product:{id}:available_stock) is invalidated on every state change.


# **How to Run Locally**
***Database Installation***
 * Download & install MySQL
 * start the mysql service
     `sudo service mysql start`
 * Create a database for your project
 * Configure Laravel to use MySQL
   ```DB_CONNECTION=mysql
        DB_HOST=127.0.0.1
        DB_PORT=3306
        DB_DATABASE=flash_sale
        DB_USERNAME=root
        DB_PASSWORD=your_password```

  ***Run the project***
 1- Clone the repo:
    `git clone https://github.com/MonaaEid/Flash-Sale-Checkout.git `
    `cd flash-sale-checkout`

 2- Install dependencies
  `composer install`
 2- Configure .env with DB connection.

 3- Run migrations and seeders:
    `php artisan migrate --seed`

 4- Start the server:
    `php artisan serve`

**How to Test**
    `php artisan test`
