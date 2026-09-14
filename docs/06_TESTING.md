# Testing

Pest 3 is the required local test framework because current Pest versions require the
unapproved PHP 8.4/Laravel 13 stack. Tests will cover business services, authentication,
ownership, products, categories, upload validation, quotas, image processing, reports,
moderation, search, and public pages.

Storage tests will use fakes. Security tests must demonstrate that one seller cannot
read or modify another seller's private resources.

The current authentication coverage verifies screen rendering, registration, active
login, suspended-account denial, verified-email protection for the seller panel,
Turnstile server validation, and account rate limits.

Seller shop tests cover normalized contact data, deterministic slug collisions, the
one-active-shop free limit, ownership denial, updates, and soft deletion.
