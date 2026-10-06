# Perfiles de negocio y registro

## Decisión de arquitectura

`shops.business_type` es la fuente del perfil comercial. El tipo pertenece a la
tienda, no al usuario: una cuenta puede operar varias tiendas con perfiles
distintos. `business_capability_overrides` queda reservado para ajustes explícitos
y `business_profile_version` permite evolucionar los presets sin resembrar datos.

El backend es la autoridad. La web y Android reciben `capabilities` y
`product_fields`; el menú es una ayuda visual y las rutas vuelven a comprobar la
capacidad antes de ejecutar una operación.

## Registro

El alta transaccional crea, en una sola operación:

1. Usuario activo con plan `free`.
2. Tienda con nombre separado del nombre del propietario, tipo estable, WhatsApp y
   slug único.
3. Categorías iniciales del preset, sin borrar ni reemplazar categorías existentes.

El slug se normaliza en el servidor, respeta palabras reservadas y conserva tiendas
eliminadas mediante `withTrashed` para no reutilizar enlaces antiguos. El campo
opcional `slug` solo es una sugerencia del formulario; no es una autoridad del
cliente.

La pantalla sigue el flujo público observado en Puntto: nombre del negocio, tipo de
negocio, propietario, correo, WhatsApp, contraseña y aceptación de términos. No se
envían credenciales ni datos al comparar la interfaz.

## Capacidades

Cada perfil declara capacidades como `products`, `inventory`, `sales`, `customers`,
`credit`, `cash`, `expenses`, `finance`, `decants` y `public_catalog`. Los estados
son:

- `enabled`: implementado y permitido por perfil y plan.
- `disabled`: válido para el dominio, pero no habilitado por el perfil o plan.
- `unsupported`: aún no implementado; no se muestra como una función operativa.

Los decants se mantienen disponibles para una tienda heredada de tipo general que
ya tiene productos `sale_unit=decant`, para no romper catálogos existentes. Esto no
concede la función a nuevas tiendas gratuitas; el alta nueva necesita el perfil y
plan correspondientes.

## Compatibilidad

Las tiendas existentes reciben `general_retail` como valor técnico seguro. No se
infieren tipos por nombre, categorías o productos y no se borran datos. Los campos
nuevos tienen valores por defecto y los clientes Android anteriores ignoran las
claves nuevas del JSON.

## Contrato móvil

`GET /api/v1/shops` y el snapshot de catálogo incluyen:

- `business_type` y `business_type_label`;
- `capabilities` con el estado de cada capacidad;
- `product_fields` para que el formulario pueda adaptar sus campos;
- `menu_permissions` ya filtrado por el perfil.

Android conserva estos datos en su caché existente, aplica el filtro de capacidades
al menú y mantiene el fallback de red actual. Las funciones no soportadas no se
presentan como disponibles.
