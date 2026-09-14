# Security

Authorization is enforced in the backend with Policies, ownership scopes, middleware,
and server-side validation. Hiding a UI control is never authorization.

Authentication requires registration, email verification, password recovery, rate
limits, CSRF protection, and server-side Turnstile validation where applicable.

Laravel Fortify provides the local registration, login, logout, password-reset, and
email-verification flows. Users have active or suspended status; suspended accounts
cannot authenticate and seller routes require a verified email.

Cloudflare Turnstile protects login and registration. The widget token is verified by
the server through Siteverify, including the expected action, before either operation
continues. Keys are configured only through environment variables.

Uploads must validate actual MIME type, decodability, dimensions, pixel count, quota,
and ownership. Images are re-encoded to remove EXIF and GPS metadata.

Catalog authorization uses model policies and database query scopes. A seller may only
read or mutate their own shops and dependent categories, products, images, and metrics.
Administrators are granted catalog-wide access by policy; global categories and report
review are administrator-only.
