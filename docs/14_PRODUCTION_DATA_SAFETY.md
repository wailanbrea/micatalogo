# Seguridad de datos en producción

Estas reglas aplican a cualquier agente que trabaje en MiCatalogo, incluidos Codex,
Antigravity y OpenCode.

## Nunca sembrar demos en producción

- `DatabaseSeeder` es solo para desarrollo local y pruebas; no se ejecuta en producción.
- Un despliegue usa migraciones idempotentes (`php artisan migrate --force`) y nunca
  `db:seed`, `migrate:fresh`, `migrate:refresh`, `migrate:reset` ni truncados.
- Los datos demo deben vivir en seeders de desarrollo o tests separados de la ruta de
  despliegue.

## Nunca borrar cuentas o tiendas durante un despliegue

Antes de modificar producción:

1. Crear y verificar un backup de la base de datos.
2. Registrar el conteo e identificadores de usuarios y tiendas.
3. Aplicar únicamente el cambio solicitado y acotado.
4. Comparar esos conteos e identificadores después del despliegue.

No se eliminan usuarios, tiendas, productos ni pedidos como parte de una actualización
visual o funcional. Cualquier baja requiere alcance explícito, backup verificado y una
operación reversible o soft-delete.

## Cambios de datos oficiales

Los valores de BSolutions que deban mantenerse consistentes se corrigen mediante una
migración idempotente y una verificación posterior. `firstOrCreate` no debe usarse como
mecanismo para actualizar datos existentes.
