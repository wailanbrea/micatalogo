# Risk Register

| Risk | Impact | Mitigation | Status |
|---|---|---|---|
| Shared PHP is 8.2.33 | Medium | Laravel 12 approved for local development | Accepted |
| Active database is MariaDB 11.4 | Medium | Approved for local development only | Accepted |
| Composer 2.8.11 has advisories | Medium | Use local Composer 2.10.3 | Mitigated |
| Other XAMPP projects coexist | Critical | Do not replace shared PHP or Apache settings | Open |
| Image abuse and metadata leakage | High | Validate, cap pixels, re-encode, strip metadata | Mitigated locally |
| Cross-seller access | Critical | Policies, ownership scopes, and security tests | Mitigated |
| R2 or worker failure | High | Queued idempotent jobs, retries, and monitoring | Mitigated locally |
| Data loss | Critical | Backups and restore drills before production | Planned |
