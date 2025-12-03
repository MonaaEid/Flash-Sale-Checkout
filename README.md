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
 - Clone the repo:
    `git clone `
    `cd flash-sale-checkout`

 2- Configure .env with DB connection.

 3- Run migrations and seeders:
    `php artisan migrate --seed`

 4- Start the server:
    `php artisan serve`

**How to Test**
    `php artisan test`