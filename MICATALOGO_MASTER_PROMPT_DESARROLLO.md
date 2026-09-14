# MiCatalogo — Master Prompt de Desarrollo
**Versión:** 1.0  
**Fecha de referencia tecnológica:** 2026-09-12  
**Objetivo:** especificación ejecutable para IA de desarrollo, arquitectura, QA y despliegue.

---

# 0. INSTRUCCIÓN MAESTRA PARA LA IA

Actúa simultáneamente como:

- Arquitecto de software senior.
- Desarrollador Full Stack senior especializado en Laravel.
- DBA MySQL.
- Ingeniero de seguridad web.
- Ingeniero DevOps para Windows/Apache.
- QA Automation Engineer.
- Analista de producto.
- Revisor de rendimiento y costes.

Tu trabajo es construir **MiCatalogo** exactamente bajo esta especificación, sin sustituir tecnologías, agregar complejidad innecesaria ni asumir requisitos inexistentes.

## Regla anti-alucinación obligatoria

Antes de instalar, configurar o utilizar cualquier tecnología, paquete, método, API, comando o integración:

1. Comprueba si ya existe en el proyecto.
2. Comprueba si Laravel o el stack definido ya resuelve la necesidad.
3. Si tienes acceso a Internet, valida la sintaxis y compatibilidad en la documentación oficial.
4. No uses como fuente principal blogs, snippets, respuestas antiguas o documentación de otra versión.
5. No inventes métodos, clases, paquetes Composer, variables de entorno ni funcionalidades.
6. Si la documentación oficial contradice este documento por una actualización posterior, detente, documenta la diferencia y propone el cambio antes de aplicarlo.
7. No sustituyas una tecnología aprobada por otra “porque la prefieres”.
8. No agregues dependencias para resolver algo que pueda implementarse de forma clara con Laravel/PHP nativo.
9. Todo cambio arquitectónico debe registrarse en `/docs/08_DECISIONS.md`.
10. Si hay incertidumbre técnica material, no improvises: crea un bloqueo explícito en `10_PROJECT_STATE.md`.

---

# 1. DEFINICIÓN DEL PRODUCTO

MiCatalogo es una **vitrina digital gratuita de productos**.

Su propósito es resolver este problema:

> Muchos vendedores publican todos los días las mismas fotos de productos en WhatsApp. MiCatalogo les permite subir sus productos una sola vez, obtener un enlace permanente y compartir ese catálogo con sus clientes.

## MiCatalogo NO es un ecommerce

No implementar en el MVP:

- carrito;
- checkout;
- pagos;
- tarjetas;
- órdenes de compra;
- delivery;
- tracking;
- facturación;
- reserva de productos;
- comisiones de venta;
- escrow;
- inventario contable;
- POS;
- CRM;
- marketplace transaccional;
- reviews;
- puntuaciones;
- chat interno;
- mensajería propia;
- notificaciones push;
- sistema de afiliados.

La acción de conversión principal es:

```text
Cliente ve producto
        ↓
Abre ficha
        ↓
Consulta por WhatsApp
```

MiCatalogo solo muestra productos y conecta cliente con vendedor.

---

# 2. MODELO DE NEGOCIO

## Usuario vendedor

- Registro gratuito.
- Crea su vitrina.
- Sube productos.
- Comparte el enlace.
- Recibe consultas por WhatsApp.

## Visitante

- No necesita cuenta.
- Navega productos y tiendas.
- Busca.
- Filtra.
- Abre productos.
- Contacta por WhatsApp.

## Monetización

La plataforma será inicialmente gratuita y monetizará principalmente mediante publicidad en áreas públicas.

La arquitectura publicitaria debe ser **provider-agnostic**.

No integrar AdSense, Ad Manager u otro proveedor hasta que:

1. exista cuenta aprobada;
2. se revisen sus políticas vigentes;
3. exista sistema de moderación funcional;
4. exista política de privacidad;
5. exista gestión de consentimiento cuando legalmente sea necesaria.

Nunca asumir que la plataforma será aceptada automáticamente por Google AdSense.

---

# 3. BASE TECNOLÓGICA BLOQUEADA

## Backend

- PHP **8.4.x**
- Laravel **13.x**
- Composer 2.x

## Base de datos

- MySQL **8.4 LTS**
- InnoDB
- `utf8mb4`

## Frontend

- Blade
- Livewire **4.x**
- Alpine.js provisto por Livewire cuando corresponda
- Tailwind CSS **4.x**
- Vite

## Imágenes

- Laravel 13 Image API
- Intervention Image **4.x**
- GD como mínimo obligatorio
- Imagick permitido únicamente si está instalado, probado y aporta compatibilidad real
- salida principal: WebP

## Archivos

- Laravel Filesystem / Flysystem
- `league/flysystem-aws-s3-v3:^3.0`
- Cloudflare R2 Standard

## Testing

- Pest
- PHPUnit subyacente según dependencias de Laravel

## Infraestructura inicial

- Apache
- Windows VPS
- Cloudflare DNS/CDN/WAF
- Cloudflare R2
- MySQL

---

# 4. TECNOLOGÍAS PROHIBIDAS EN EL MVP

No introducir sin una decisión arquitectónica aprobada:

- React
- Vue
- Angular
- Next.js
- Nuxt
- Node.js como backend
- NestJS
- microservicios
- Kubernetes
- Docker obligatorio en producción
- Redis
- Memcached
- Elasticsearch
- OpenSearch
- Algolia
- RabbitMQ
- Kafka
- GraphQL
- WebSockets
- Firebase
- Supabase
- S3 de AWS
- Cloudinary
- servicios de pago
- WhatsApp Business API
- IA generativa
- reconocimiento automático de imágenes
- recomendaciones con IA

Node/NPM sí se utiliza exclusivamente para Vite/Tailwind y tooling frontend.

---

# 5. PRINCIPIOS DE ARQUITECTURA

1. Monolito modular Laravel.
2. Una sola base de datos MySQL.
3. Multiusuario mediante relaciones y Policies, no mediante paquetes de tenancy.
4. Imágenes fuera del VPS en R2.
5. El VPS ejecuta lógica, no sirve grandes volúmenes de media.
6. Las páginas públicas deben poder renderizarse en servidor.
7. No construir infraestructura para una escala que todavía no existe.
8. Sí dejar límites y abstracciones claras para poder escalar sin reescribir el dominio.
9. Seguridad y ownership se validan siempre en backend.
10. Todo proceso no crítico que pueda ser pesado debe poder ejecutarse en cola.

---

# 6. ARQUITECTURA GENERAL

```text
                           INTERNET
                               |
                               v
                        CLOUDFLARE
                  DNS + TLS + WAF + CDN
                               |
                 +-------------+-------------+
                 |                           |
                 v                           v
            APLICACIÓN                    MEDIA
             Laravel                      R2/CDN
                 |                           |
                 v                           |
              MySQL                         |
                 ^                           |
                 |                           |
                 +------ metadata -----------+
```

## Responsabilidad de Cloudflare

- DNS.
- HTTPS/TLS.
- WAF.
- protección básica frente a bots y abuso.
- CDN.
- caché de media.
- custom domain de R2.
- Turnstile para formularios susceptibles a bots.

## Responsabilidad de Laravel

- autenticación;
- autorización;
- tiendas;
- productos;
- categorías;
- validación;
- procesamiento de imágenes;
- colas;
- búsqueda;
- moderación;
- estadísticas;
- administración;
- generación de links WhatsApp;
- SEO;
- slots de publicidad.

## Responsabilidad de MySQL

Solo datos estructurados y metadatos.

No usar BLOB para fotografías.

## Responsabilidad de R2

- logos;
- imágenes finales;
- thumbnails;
- opcionalmente staging temporal controlado en una fase futura.

---

# 7. RESTRICCIÓN DE DESPLIEGUE EN EL VPS ACTUAL

El VPS puede contener otros sistemas ejecutándose con XAMPP/PHP 8.2.

**NO actualizar el PHP global de XAMPP sin auditar antes todos los sistemas existentes.**

Laravel 13 requiere PHP >= 8.3.

Objetivo para MiCatalogo:

```text
PHP 8.4.x
Laravel 13.x
```

Antes del deployment:

1. inventariar vhosts;
2. inventariar proyectos existentes;
3. verificar versiones PHP requeridas por cada proyecto;
4. verificar extensiones;
5. verificar módulos Apache;
6. comprobar si MiCatalogo puede usar runtime PHP 8.4 aislado;
7. preparar rollback;
8. hacer backup.

Si la única opción disponible implica modificar el runtime global y existe riesgo para otros proyectos:

**DETENER deployment y documentar el bloqueo.**

Nunca romper otros sistemas para desplegar MiCatalogo.

---

# 8. ESTRUCTURA DE DOCUMENTACIÓN OBLIGATORIA

Crear:

```text
/docs
├── 00_PROJECT_OVERVIEW.md
├── 01_ARCHITECTURE.md
├── 02_DATABASE.md
├── 03_BUSINESS_RULES.md
├── 04_SECURITY.md
├── 05_STORAGE_R2.md
├── 06_TESTING.md
├── 07_DEPLOYMENT.md
├── 08_DECISIONS.md
├── 09_TODO.md
├── 10_PROJECT_STATE.md
├── 11_API_AND_ROUTES.md
├── 12_OPERATIONS.md
└── 13_RISK_REGISTER.md
```

`10_PROJECT_STATE.md` es obligatorio para continuidad entre IAs.

Debe contener siempre:

- fecha;
- commit actual;
- fase;
- tareas terminadas;
- tareas en progreso;
- tareas pendientes;
- bloqueos;
- decisiones vigentes;
- archivos clave;
- migraciones;
- comandos necesarios;
- tests;
- errores conocidos;
- siguiente tarea exacta.

---

# 9. CONTROL DE CAMBIOS POR IA

Antes de modificar código:

```text
1. leer 10_PROJECT_STATE.md
2. leer 09_TODO.md
3. revisar git status
4. identificar la fase activa
5. inspeccionar archivos relacionados
6. escribir plan corto
7. modificar solo lo necesario
8. ejecutar pruebas
9. revisar git diff
10. actualizar documentación
```

Prohibido:

- reescribir módulos completos sin necesidad;
- eliminar código sin comprobar referencias;
- cambiar stack;
- renombrar masivamente;
- modificar producción sin backup;
- hacer `git reset --hard`;
- borrar migraciones ya ejecutadas en producción;
- editar secretos en código fuente.

---

# 10. ROLES Y AUTORIZACIÓN

## Guest

Puede:

- home;
- búsqueda;
- categorías;
- tiendas públicas;
- productos públicos;
- contactar WhatsApp;
- compartir;
- reportar contenido.

## Seller

Puede:

- gestionar únicamente recursos propios;
- gestionar tienda;
- productos;
- categorías internas;
- imágenes;
- disponibilidad;
- configuración;
- estadísticas propias.

## Admin

Puede:

- gestionar usuarios;
- tiendas;
- productos;
- categorías globales;
- reportes;
- suspensiones;
- métricas;
- configuración operativa;
- slots publicitarios.

## Regla crítica

Nunca basar seguridad en ocultar botones.

Utilizar:

- Policies;
- Gates cuando corresponda;
- scopes/queries con ownership;
- middleware;
- validación server-side.

Un Seller A jamás puede consultar, modificar o eliminar datos privados del Seller B.

---

# 11. AUTENTICACIÓN Y ANTI-ABUSO

Implementar:

- registro;
- login;
- logout;
- recuperación de contraseña;
- verificación de email;
- rate limiting;
- Cloudflare Turnstile en registro;
- Turnstile en recuperación de contraseña si existe abuso;
- Turnstile en reportes públicos;
- protección CSRF.

Turnstile debe validarse también en servidor mediante Siteverify.

No aceptar únicamente el token del frontend.

## Creación de tienda

Un usuario nuevo no debe poder generar cientos de tiendas.

MVP:

```text
máximo de tiendas activas por cuenta free = 1
```

La arquitectura puede usar `User hasMany Shops`, pero el límite de negocio se controla por configuración.

---

# 12. IDENTIFICADORES Y ENUMERACIÓN

Usar PK numérica interna para eficiencia:

```text
id BIGINT UNSIGNED
```

Y `public_id` ULID para entidades públicas importantes:

- shops;
- products;
- reports.

No exponer IDs incrementales como identificador público principal.

## Slugs

### Tienda

Slug globalmente único.

```text
/tienda/brea-fashion
```

### Producto

Slug único dentro de la tienda.

```text
/tienda/brea-fashion/producto/nike-air-max
```

Restricción DB:

```text
UNIQUE(shop_id, slug)
```

Resolver colisiones de manera determinista.

Nunca asumir que un nombre genera un slug único.

---

# 13. MODELO DE DATOS MÍNIMO

## users

Campos Laravel + estado.

Campos adicionales:

```text
status
email_verified_at
last_login_at
created_at
updated_at
```

Estados:

```text
active
suspended
```

---

## shops

```text
id
public_id
user_id
name
slug
description
logo_object_key
whatsapp_country_code
whatsapp_number
instagram
status
created_at
updated_at
deleted_at
```

Índices:

- unique public_id;
- unique slug;
- user_id;
- status.

---

## global_categories

```text
id
parent_id nullable
name
slug
status
sort_order
created_at
updated_at
```

Permite jerarquía simple.

No permitir profundidad ilimitada en MVP.

---

## shop_categories

```text
id
shop_id
name
slug
sort_order
status
created_at
updated_at
```

Restricción:

```text
UNIQUE(shop_id, slug)
```

---

## products

```text
id
public_id
shop_id
global_category_id nullable
shop_category_id nullable
name
slug
description nullable
price DECIMAL(12,2) nullable
currency CHAR(3) default DOP
availability_status
moderation_status
published_at nullable
created_at
updated_at
deleted_at
```

Restricción:

```text
UNIQUE(shop_id, slug)
```

Estados disponibilidad:

```text
available
out_of_stock
```

Estados moderación:

```text
draft
active
pending_review
suspended
```

Usar PHP backed enums + casts de Eloquent.

No usar strings repetidos por toda la aplicación.

---

## product_images

```text
id
product_id
object_key
thumbnail_object_key
mime_type
width
height
size_bytes
checksum_sha256
sort_order
processing_status
created_at
updated_at
```

Estados:

```text
pending
processing
ready
failed
```

Índices:

- product_id;
- processing_status.

---

## reports

```text
id
public_id
reportable_type
reportable_id
reason
description nullable
status
created_at
updated_at
resolved_at nullable
resolved_by nullable
```

Usar polymorphic únicamente si reduce duplicación sin ocultar reglas de negocio.

---

## shop_daily_metrics

```text
shop_id
date
page_views
whatsapp_clicks
```

Unique:

```text
(shop_id, date)
```

---

## product_daily_metrics

```text
product_id
date
page_views
whatsapp_clicks
```

Unique:

```text
(product_id, date)
```

Las métricas son pageviews/clics, no afirmar que son “personas únicas”.

---

# 14. CONFIGURACIÓN DE LÍMITES

No hardcodear límites en controladores.

Crear configuración central, por ejemplo:

```text
config/catalog.php
```

Inicial:

```text
free.max_active_shops = 1
free.max_products_per_shop = 100
free.max_images_per_product = 3
uploads.max_file_size_mb = 10
uploads.max_batch_files = 30
images.main_max_width = 1600
images.main_max_height = 1600
images.thumbnail_width = 480
images.thumbnail_height = 480
images.webp_quality = 80
images.thumbnail_quality = 75
```

Estos límites son modificables sin tocar lógica de dominio.

---

# 15. PIPELINE SEGURO DE IMÁGENES

## Formatos iniciales aceptados

- JPEG
- JPG
- PNG
- WebP

No aceptar inicialmente:

- SVG;
- GIF;
- BMP;
- TIFF;
- archivos ejecutables disfrazados.

HEIC/HEIF:

- solo habilitar si el runtime dispone de soporte Imagick/HEIC validado;
- no prometer soporte si el servidor no puede decodificarlo;
- mantenerlo deshabilitado por defecto.

## Validación

Validar:

1. tamaño real;
2. MIME por contenido;
3. extensión;
4. que pueda decodificarse;
5. dimensiones;
6. límite máximo de píxeles;
7. pertenencia del usuario;
8. cuota disponible.

No confiar en:

```text
Content-Type del navegador
nombre del archivo
extensión
```

## Protección ante imágenes problemáticas

Definir límites de dimensiones/píxeles para evitar consumo excesivo de memoria.

Valor inicial configurable:

```text
max_input_pixels = 60_000_000
```

Si excede el límite:

- rechazar de forma segura;
- no intentar procesarla completamente;
- mostrar mensaje comprensible.

## Privacidad

Al re-encodear:

- aplicar orientación EXIF;
- eliminar metadatos EXIF;
- eliminar GPS;
- no conservar nombre de archivo original como ruta pública.

Configurar Intervention Image para strip de metadata.

## Derivados

### Imagen principal

```text
máx 1600x1600
mantener aspecto
no upscale
WebP
quality 80 aprox.
```

### Thumbnail

```text
480x480
WebP
quality 75 aprox.
```

No usar la imagen principal en grids.

---

# 16. FLUJO DE UPLOAD DEL MVP

Por seguridad y simplicidad, el MVP NO debe hacer pública una carpeta temporal en R2.

Flujo:

```text
navegador
   ↓
Laravel / Livewire
   ↓
validación inicial
   ↓
storage privado temporal local
   ↓
crear registro pending
   ↓
dispatch ProcessProductImageJob
   ↓
procesar
   ↓
guardar derivados en R2
   ↓
actualizar metadata
   ↓
borrar temporal
```

La carpeta temporal debe estar fuera de `public/`.

## Razón

La validación completa debe ocurrir antes de publicar contenido.

La aplicación queda preparada para migrar a direct-to-R2 en una fase futura si el tráfico de subida lo exige, pero no se sacrificará seguridad para ahorrar ancho de banda prematuramente.

---

# 17. COLAS

MVP:

```text
QUEUE_CONNECTION=database
```

Jobs:

- ProcessProductImageJob
- DeleteOrphanedMediaJob
- PurgeDeletedProductMediaJob
- cualquier tarea pesada futura justificada.

Requisitos:

- retry limitado;
- backoff;
- timeout;
- failed jobs;
- idempotencia.

Un retry de procesamiento no debe crear copias duplicadas.

Usar nombres de objeto deterministas o controlar correctamente el reemplazo.

## Worker en Windows

El worker debe correr como proceso persistente gestionado por un mecanismo adecuado al VPS.

No depender de que alguien deje una consola abierta.

Documentar instalación, reinicio y verificación.

---

# 18. SCHEDULER

Configurar Laravel Scheduler para:

- limpiar temporales;
- purgar soft-deleted vencidos;
- eliminar huérfanos confirmados;
- tareas de mantenimiento;
- agregaciones futuras.

En Windows debe existir una tarea programada que ejecute `schedule:run` con la periodicidad oficial requerida por Laravel.

Documentar ruta exacta de PHP usada por MiCatalogo.

No utilizar accidentalmente el PHP 8.2 de otro proyecto.

---

# 19. CLOUDFLARE R2

Usar **R2 Standard**.

No usar Infrequent Access para las imágenes públicas del catálogo.

## Bucket

No usar `r2.dev` en producción.

Usar custom domain:

```text
media.<DOMINIO_MICATALOGO>
```

El dominio real no debe inventarse: se configura cuando exista.

## Seguridad

- credenciales en `.env`;
- nunca en Git;
- permisos mínimos;
- deshabilitar `r2.dev` en producción;
- rutas no predecibles;
- WAF/caché por custom domain.

## Cache de media

Los filenames finales deben ser content/versioned:

```text
products/{public_id}/main-{hash}.webp
products/{public_id}/thumb-{hash}.webp
```

Así pueden utilizar cache largo/immutable sin servir imágenes viejas cuando cambian.

Al reemplazar una foto:

1. generar nuevo nombre;
2. guardar nuevo objeto;
3. actualizar DB;
4. después programar eliminación del objeto viejo.

Nunca sobrescribir primero y confiar en purgas.

---

# 20. CONTROL DE COSTES DE R2

Registrar internamente:

```text
size_bytes
```

de cada media final.

Permitir calcular:

- almacenamiento total;
- almacenamiento por tienda;
- promedio por producto.

No calcular cuotas haciendo listados completos del bucket en cada request.

Usar metadata de base de datos.

Crear panel admin con:

- total imágenes;
- bytes almacenados;
- imágenes fallidas;
- productos sin imagen;
- usuarios con mayor consumo.

---

# 21. SUBIDA MASIVA

Objetivo:

El vendedor debe poder agregar muchos productos sin repetir el mismo flujo 20 veces.

Flujo:

```text
seleccionar fotos
     ↓
previews
     ↓
filas editables
     ↓
nombre
precio
categoría
     ↓
guardar
```

Límite inicial:

```text
30 archivos por lote
```

No enviar 30 archivos simultáneamente sin control.

Usar concurrencia limitada.

Mostrar:

- progreso;
- éxito;
- error por archivo;
- reintento individual.

Un fallo no debe cancelar productos ya guardados correctamente.

---

# 22. CICLO DE VIDA DE ARCHIVOS

## Eliminación de producto

```text
soft delete
    ↓
periodo de recuperación
    ↓
purga programada
    ↓
eliminar media
```

Periodo inicial configurable:

```text
30 días
```

## Reglas

Nunca eliminar el objeto R2 antes de confirmar la intención persistente en DB.

Si falla R2:

- mantener registro de limpieza pendiente;
- reintentar;
- no romper la transacción principal.

Crear auditoría de huérfanos:

- objeto DB sin R2;
- objeto R2 sin DB;
- thumbnail faltante;
- processing atascado.

No ejecutar borrado masivo automático basado únicamente en una comparación dudosa.

Primero reportar; después purgar únicamente estados confirmados.

---

# 23. BÚSQUEDA

No usar servicio externo inicialmente.

Crear:

```text
CatalogSearchService
```

para desacoplar implementación.

MVP:

- búsqueda por nombre de producto;
- tienda;
- categoría;
- filtros.

Priorizar:

1. coincidencia exacta;
2. prefijo;
3. coincidencia parcial controlada.

No disparar una consulta por cada tecla.

Usar debounce razonable en Livewire.

Paginar siempre.

Registrar queries lentas durante QA.

Si los datos crecen hasta un punto donde MySQL ya no satisface latencia objetivo, abrir ADR antes de introducir motor externo.

---

# 24. EXPERIENCIA PÚBLICA

Debe sentirse familiar para quien haya usado Amazon u otros catálogos grandes, sin copiar identidad, código o interfaz exacta.

## Home

- logo;
- búsqueda dominante;
- categorías;
- productos recientes/destacados;
- tiendas;
- slots publicitarios discretos;
- CTA `Crea tu catálogo gratis`.

## Grid

Responsive:

```text
mobile: 2 columnas
tablet: 3
desktop: 4-6 según ancho
```

## Product card

- thumbnail;
- nombre;
- precio;
- tienda;
- disponibilidad.

No llenar la tarjeta de botones.

## Ficha

- galería;
- nombre;
- precio;
- disponibilidad;
- descripción;
- tienda;
- CTA WhatsApp;
- compartir;
- más productos de la tienda.

No carrito.

---

# 25. WHATSAPP

No integrar API empresarial en MVP.

Usar enlace oficial compatible:

```text
https://wa.me/<numero>?text=<mensaje_url_encoded>
```

Normalizar número:

- country code;
- dígitos;
- sin espacios;
- sin `+`;
- sin caracteres no numéricos.

Mensaje:

```text
Hola, me interesa "{PRODUCT_NAME}" que vi en MiCatalogo:
{PRODUCT_URL}
```

Registrar clic como evento interno sin interceptar ni leer conversaciones.

---

# 26. MODERACIÓN Y UGC

La plataforma contiene contenido generado por usuarios.

Desde MVP debe existir:

- términos;
- política de contenido;
- reportar tienda;
- reportar producto;
- suspensión;
- revisión admin;
- auditoría de acciones admin.

Razones iniciales:

```text
illegal_content
counterfeit
fraud
adult_content
prohibited_product
spam
copyright
other
```

No permitir que contenido suspendido aparezca públicamente.

La publicidad debe poder desactivarse por:

- tienda;
- producto;
- categoría;
- globalmente.

Si una página está bajo revisión, poder no servir anuncios.

---

# 27. PUBLICIDAD

Crear componente conceptual:

```text
AdSlot
```

Slots:

```text
home_after_grid_1
catalog_between_rows
product_below_details
```

Reglas:

- no parecer producto;
- etiquetado de publicidad si proveedor lo requiere;
- no provocar clic accidental;
- no colocar encima del CTA principal;
- no colocar entre precio y WhatsApp;
- no reservar espacios vacíos en producción cuando ads están desactivados.

Sistema debe soportar:

```text
ADS_ENABLED=false
```

hasta contar con proveedor aprobado.

No hardcodear scripts de publicidad por todo Blade.

Centralizar integración.

---

# 28. PRIVACIDAD Y CONSENTIMIENTO

Minimizar datos.

No almacenar IP cruda de visitantes para estadísticas ordinarias salvo necesidad operativa temporal y documentada.

No vender datos.

Preparar:

- política de privacidad;
- cookies;
- consentimiento;
- eliminación de cuenta;
- exportación básica de información propia.

Si se usa un proveedor publicitario que exige CMP en determinadas regiones:

- integrar una CMP compatible/certificada según política vigente;
- no inventar una implementación legal;
- revisar requisitos actuales antes del lanzamiento internacional.

---

# 29. MÉTRICAS

MVP:

- pageviews de tienda;
- pageviews de producto;
- clics WhatsApp.

No denominar “usuarios únicos” a pageviews.

Incrementar vistas únicamente en carga pública real GET.

No incrementar por:

- re-render Livewire;
- preview administrativo;
- bots identificados claramente;
- health checks.

Agregar diariamente mediante UPSERT/operación atómica para evitar race conditions.

---

# 30. SEO

Páginas indexables:

- home;
- categorías;
- tiendas activas;
- productos activos.

Implementar:

- title;
- meta description;
- canonical;
- Open Graph;
- sitemap;
- robots.txt;
- breadcrumbs;
- URLs legibles.

No indexar:

- login;
- panel;
- admin;
- búsquedas internas infinitas;
- contenido suspendido;
- borradores.

Structured data solo si los datos son reales.

No crear reviews, ratings, disponibilidad o precios falsos para SEO.

---

# 31. CACHE Y RENDIMIENTO

## Media

Cloudflare custom domain + cache.

## HTML

No cachear indiscriminadamente páginas autenticadas.

No usar una regla `Cache Everything` global sobre:

- dashboard;
- login;
- Livewire;
- admin.

## Objetivos

- imágenes pequeñas;
- thumbnail en grid;
- lazy loading;
- dimensiones explícitas;
- paginación;
- eager loading;
- evitar N+1;
- índices;
- minimizar JS;
- assets Vite versionados.

No introducir Redis antes de medir.

---

# 32. SEGURIDAD HTTP

Producción:

- HTTPS obligatorio;
- cookies Secure;
- cookies HttpOnly donde aplique;
- SameSite correcto;
- HSTS cuando TLS esté estable;
- CSP evaluada;
- `X-Content-Type-Options: nosniff`;
- protección contra framing mediante CSP `frame-ancestors` o header equivalente;
- Referrer-Policy apropiada.

No romper scripts de Livewire/ads con CSP sin probar.

Documentar cualquier excepción.

---

# 33. VALIDACIÓN DE INPUT

Todos los inputs:

- longitud máxima;
- tipo;
- encoding;
- normalización;
- sanitización según contexto.

No almacenar HTML arbitrario en:

- nombres;
- descripciones;
- categorías.

Descripción de producto: texto plano en MVP.

Escapar output.

No implementar editor WYSIWYG en MVP.

---

# 34. RATE LIMITING

Definir límites independientes para:

- login;
- register;
- password reset;
- report;
- búsqueda;
- creación de producto;
- uploads.

No escoger números aleatorios sin probar UX.

Centralizar límites.

Combinar Laravel RateLimiter + Cloudflare cuando corresponda.

---

# 35. LOGGING Y AUDITORÍA

Logs:

- errores;
- jobs fallidos;
- fallos R2;
- fallos de procesamiento;
- acciones admin sensibles;
- suspensiones;
- restauraciones.

Nunca registrar:

- contraseñas;
- tokens;
- secretos;
- contenido completo de credenciales;
- claves R2.

Rotar logs.

No dejar debug activo en producción.

```text
APP_DEBUG=false
APP_ENV=production
```

---

# 36. BACKUPS

Debe existir estrategia antes del lanzamiento.

## MySQL

Backup automático.

Validar restauración, no solo creación.

## R2

Las imágenes pueden regenerarse únicamente si se conserva original, pero el diseño elimina original, por lo que R2 es dato persistente importante.

No asumir que “está en Cloudflare” equivale a backup.

Documentar estrategia de recuperación.

Como mínimo:

- inventario DB ↔ object keys;
- export DB;
- procedimiento de restauración;
- política de retención;
- prueba periódica.

---

# 37. TOLERANCIA A FALLOS

## R2 caído

- páginas siguen respondiendo;
- mostrar placeholder de imagen;
- no lanzar excepción fatal pública.

## Job falla

- imagen queda `failed`;
- usuario puede reintentar;
- producto no queda corrupto.

## DB falla

- error genérico;
- log interno;
- no exponer stack trace.

## WhatsApp inválido

- validar configuración antes de publicar tienda;
- evitar enlaces rotos.

---

# 38. CONCURRENCIA E IDEMPOTENCIA

Evitar:

- doble submit;
- productos duplicados por doble clic;
- procesamiento duplicado de imagen;
- doble purge.

Botones de acciones mutables:

- desactivar durante request;
- backend idempotente cuando corresponda.

Jobs deben poder reintentarse de forma segura.

---

# 39. PANEL SELLER

Pantallas:

```text
Dashboard
Mi tienda
Productos
Nuevo producto
Subida masiva
Categorías
Estadísticas
Configuración
```

Dashboard:

- productos activos;
- agotados;
- vistas;
- clics WhatsApp;
- almacenamiento aproximado;
- límite usado.

Acciones visibles:

```text
+ Agregar producto
Ver mi catálogo
Copiar enlace
Compartir
```

Mobile-first.

---

# 40. PANEL ADMIN

Mínimo:

```text
Dashboard
Usuarios
Tiendas
Productos
Categorías
Reportes
Moderación
Métricas
Storage
Configuración
```

Debe mostrar:

- tiendas activas/suspendidas;
- productos;
- reportes abiertos;
- fallos de procesamiento;
- almacenamiento;
- jobs fallidos.

---

# 41. ACCESIBILIDAD

Obligatorio:

- labels;
- HTML semántico;
- focus visible;
- teclado;
- alt de imágenes;
- contraste;
- botones reales;
- links reales;
- target táctil adecuado.

No usar `<div>` clicable cuando deba ser botón/enlace.

---

# 42. RESPONSIVE

Probar mínimo:

```text
360x800
390x844
430x932
768x1024
1366x768
1440x900
1920x1080
```

No permitir overflow horizontal accidental.

---

# 43. TESTING OBLIGATORIO

## Unit

- normalización WhatsApp;
- slug;
- cuotas;
- estados;
- services.

## Feature

- registro;
- verificación;
- login;
- ownership;
- productos;
- categorías;
- uploads;
- límites;
- reportes;
- suspensión;
- búsqueda;
- páginas públicas.

## Storage

Usar fake adecuado.

Probar:

- upload válido;
- tipo inválido;
- tamaño;
- quota;
- procesamiento;
- error;
- delete;
- restore;
- purge.

## Security

Probar explícitamente:

- Seller A no ve private data de B;
- Seller A no edita producto B;
- IDs manipulados;
- rutas admin;
- CSRF cuando aplique;
- suspended content.

## Browser/E2E

Si la infraestructura de testing existente lo permite:

- registro;
- crear tienda;
- agregar producto;
- ver público;
- WhatsApp CTA.

No introducir una plataforma E2E nueva sin justificarla.

---

# 44. PERFORMANCE QA

Antes de release revisar:

- N+1;
- número de queries;
- tamaño HTML;
- tamaños de imágenes;
- lazy loading;
- Lighthouse/Core Web Vitals como referencia;
- tiempos del endpoint de búsqueda;
- queries lentas MySQL.

No optimizar a ciegas.

Medir primero.

---

# 45. RISK REGISTER — PROBLEMAS PREVISTOS Y RESOLUCIÓN

| Riesgo | Impacto | Mitigación |
|---|---|---|
| PHP 8.4 rompe proyectos existentes | Alto | Runtime aislado/auditoría antes de deployment |
| Bots crean cuentas y spam | Alto | Turnstile + email verification + rate limit |
| Usuario llena R2 con fotos | Alto | Límites por producto/tienda + size metadata + quotas |
| Imagen maliciosa o gigante | Alto | MIME real + decode + pixel limit + re-encode |
| EXIF revela ubicación | Alto | auto-orient + strip metadata |
| Upload masivo agota PHP | Alto | batch limit + concurrencia limitada + jobs |
| Worker detenido | Alto | servicio persistente + health check + failed jobs |
| Media huérfana | Medio | lifecycle jobs + auditoría + purga diferida |
| Borrado accidental | Alto | soft delete + 30 días + purga |
| Slugs duplicados | Medio | unique constraints + resolver colisión |
| IDs enumerables | Medio | ULID public_id |
| Seller accede a otro Seller | Crítico | Policies + ownership + tests |
| R2 indisponible | Medio | placeholder + graceful failure |
| Bucket expuesto incorrectamente | Alto | custom domain, r2.dev deshabilitado, WAF |
| Cache sirve contenido privado | Crítico | no Cache Everything global |
| Stats infladas por Livewire | Medio | contar solo GET público |
| AdSense rechaza sitio | Negocio | ad abstraction; no depender de aprobación |
| UGC viola políticas ads | Alto | moderación + reportes + suspensiones |
| Anuncio induce clic accidental | Alto | slots separados y claramente diferenciados |
| Búsqueda se degrada | Medio | SearchService + índices + paginación + ADR al escalar |
| Crece DB de métricas | Medio | agregación diaria |
| Backup no restaura | Crítico | pruebas periódicas de restore |
| Doble submit | Medio | UI lock + idempotencia backend |
| Producto queda sin imagen | Bajo | estados processing/failed + placeholder |
| HEIC falla | Medio | soporte condicionado a runtime probado |
| Secrets en repo | Crítico | `.env`, `.gitignore`, secret review |
| Debug expuesto | Crítico | APP_DEBUG=false producción |
| Moderación no escala | Alto | estados, cola admin, reportes y métricas desde MVP |

---

# 46. FASES DE DESARROLLO

## FASE 0 — Discovery y auditoría

- [ ] revisar repositorio;
- [ ] revisar documentación;
- [ ] revisar Git;
- [ ] inventariar entorno;
- [ ] PHP;
- [ ] Composer;
- [ ] MySQL;
- [ ] Apache;
- [ ] Node/NPM;
- [ ] extensiones;
- [ ] dominio;
- [ ] Cloudflare;
- [ ] verificar que no se rompan proyectos existentes;
- [ ] crear docs;
- [ ] crear risk register.

**Gate:** no iniciar Fase 1 con runtime incompatible sin plan.

---

## FASE 1 — Bootstrap

- [ ] Laravel 13;
- [ ] PHP 8.4;
- [ ] MySQL;
- [ ] Livewire 4;
- [ ] Tailwind 4;
- [ ] Vite;
- [ ] Pest;
- [ ] estructura base;
- [ ] `.env.example`;
- [ ] timezone;
- [ ] locale;
- [ ] configuración DOP.

---

## FASE 2 — Auth y seguridad de cuenta

- [ ] registro;
- [ ] login;
- [ ] logout;
- [ ] reset password;
- [ ] verify email;
- [ ] status account;
- [ ] Turnstile;
- [ ] rate limits;
- [ ] tests.

---

## FASE 3 — Dominio y DB

- [ ] enums;
- [ ] migrations;
- [ ] models;
- [ ] factories;
- [ ] constraints;
- [ ] indexes;
- [ ] ULIDs;
- [ ] slugs;
- [ ] tests.

---

## FASE 4 — Authorization

- [ ] Seller;
- [ ] Admin;
- [ ] Policies;
- [ ] scopes;
- [ ] ownership;
- [ ] security tests.

---

## FASE 5 — Tienda

- [ ] onboarding;
- [ ] crear;
- [ ] editar;
- [ ] WhatsApp;
- [ ] logo;
- [ ] slug;
- [ ] límite 1 tienda free;
- [ ] vista previa.

---

## FASE 6 — Categorías

- [ ] globales;
- [ ] internas;
- [ ] CRUD Seller;
- [ ] CRUD Admin;
- [ ] filtros.

---

## FASE 7 — Productos sin media

- [ ] CRUD;
- [ ] precio;
- [ ] DOP;
- [ ] disponibilidad;
- [ ] moderation status;
- [ ] cuotas;
- [ ] soft delete;
- [ ] restore.

---

## FASE 8 — R2

- [ ] bucket;
- [ ] credenciales mínimas;
- [ ] disk Laravel;
- [ ] custom domain;
- [ ] deshabilitar r2.dev prod;
- [ ] test conexión;
- [ ] cache policy.

---

## FASE 9 — Pipeline de imágenes

- [ ] validación;
- [ ] temp privado;
- [ ] queue job;
- [ ] orient;
- [ ] strip metadata;
- [ ] main WebP;
- [ ] thumbnail WebP;
- [ ] checksum;
- [ ] R2;
- [ ] estados;
- [ ] error/retry;
- [ ] cleanup.

---

## FASE 10 — Subida masiva

- [ ] selección;
- [ ] previews;
- [ ] progreso;
- [ ] concurrencia;
- [ ] fallos parciales;
- [ ] retry individual.

---

## FASE 11 — Catálogo público

- [ ] layout;
- [ ] home;
- [ ] categories;
- [ ] product card;
- [ ] shop page;
- [ ] product page;
- [ ] gallery;
- [ ] pagination;
- [ ] responsive.

---

## FASE 12 — Búsqueda

- [ ] CatalogSearchService;
- [ ] productos;
- [ ] tiendas;
- [ ] categoría;
- [ ] debounce;
- [ ] paginación;
- [ ] QA queries.

---

## FASE 13 — WhatsApp y compartir

- [ ] normalización;
- [ ] wa.me;
- [ ] mensaje;
- [ ] Web Share API;
- [ ] copy fallback;
- [ ] tracking de clicks.

---

## FASE 14 — Métricas

- [ ] daily shop metrics;
- [ ] daily product metrics;
- [ ] vistas;
- [ ] WhatsApp;
- [ ] seller dashboard;
- [ ] evitar doble conteo Livewire.

---

## FASE 15 — Moderación

- [ ] report product;
- [ ] report shop;
- [ ] admin queue;
- [ ] suspend;
- [ ] restore;
- [ ] audit.

---

## FASE 16 — Publicidad preparada

- [ ] AdSlot;
- [ ] flags;
- [ ] páginas elegibles;
- [ ] exclusiones;
- [ ] staging placeholders;
- [ ] production no-op sin provider.

---

## FASE 17 — SEO

- [ ] meta;
- [ ] OG;
- [ ] canonical;
- [ ] sitemap;
- [ ] robots;
- [ ] structured data real;
- [ ] noindex privado.

---

## FASE 18 — Operaciones

- [ ] queue service;
- [ ] scheduler;
- [ ] logs;
- [ ] failed jobs;
- [ ] backups;
- [ ] restore drill;
- [ ] monitoring básico.

---

## FASE 19 — QA integral

- [ ] unit;
- [ ] feature;
- [ ] security;
- [ ] responsive;
- [ ] performance;
- [ ] accessibility;
- [ ] storage lifecycle;
- [ ] recovery.

---

## FASE 20 — Deployment

- [ ] backup;
- [ ] runtime seguro;
- [ ] env;
- [ ] production build;
- [ ] migrations;
- [ ] worker;
- [ ] scheduler;
- [ ] Cloudflare;
- [ ] HTTPS;
- [ ] smoke tests;
- [ ] rollback probado.

---

# 47. DEFINITION OF DONE DEL MVP

El MVP está terminado únicamente cuando:

- [ ] un usuario puede registrarse;
- [ ] verifica email;
- [ ] crea una tienda;
- [ ] agrega WhatsApp;
- [ ] crea categorías;
- [ ] crea hasta el límite de productos;
- [ ] sube imágenes;
- [ ] imágenes se optimizan;
- [ ] metadata sensible se elimina;
- [ ] media final vive en R2;
- [ ] grids usan thumbnails;
- [ ] catálogo es público;
- [ ] búsqueda funciona;
- [ ] filtros funcionan;
- [ ] producto abre correctamente;
- [ ] WhatsApp abre con mensaje;
- [ ] compartir funciona;
- [ ] Seller no puede acceder a recursos ajenos;
- [ ] reportes funcionan;
- [ ] Admin puede suspender;
- [ ] métricas funcionan;
- [ ] slots ads están preparados pero desacoplados;
- [ ] queue funciona persistentemente;
- [ ] scheduler funciona;
- [ ] tests pasan;
- [ ] no hay errores console críticos;
- [ ] no hay N+1 conocidos;
- [ ] backup/restore está documentado;
- [ ] documentación está actualizada;
- [ ] `10_PROJECT_STATE.md` permite continuar con otra IA.

---

# 48. QUALITY GATE POR FASE

Antes de cerrar cualquier fase:

```text
[ ] implementación completa
[ ] validaciones
[ ] authorization
[ ] tests
[ ] no errores conocidos críticos
[ ] logs revisados
[ ] git diff revisado
[ ] documentación actualizada
[ ] PROJECT_STATE actualizado
[ ] siguiente fase definida
```

No marcar algo como terminado porque “parece funcionar”.

---

# 49. CRITERIO PARA ESCALAR TECNOLOGÍA

No introducir nueva infraestructura por intuición.

Crear ADR solo si métricas muestran un problema real.

Ejemplos:

## Redis

Solo si:

- cache/database queue se vuelve cuello de botella;
- mediciones lo demuestran.

## Search engine externo

Solo si:

- MySQL deja de cumplir latencia/relevancia;
- volumen lo justifica.

## Direct-to-R2 upload

Solo si:

- ancho de banda/carga del VPS de uploads es problema medido;
- se diseña validación segura de temporales.

## Segundo servidor

Solo si:

- CPU/RAM/IO indican necesidad;
- primero se optimizó aplicación/DB.

---

# 50. FUENTES OFICIALES DE REFERENCIA VERIFICADAS EL 2026-09-12

Antes de desarrollar, volver a validar estas fuentes si ha pasado tiempo significativo:

- Laravel 13 Release Notes  
  https://laravel.com/framework/docs/releases

- Laravel 13 File Storage  
  https://laravel.com/framework/docs/filesystem

- Laravel 13 Image Manipulation  
  https://laravel.com/framework/docs/images

- Laravel 13 Validation  
  https://laravel.com/framework/docs/validation

- Livewire 4 Installation  
  https://livewire.laravel.com/docs/4.x/installation

- Livewire 4 File Uploads  
  https://livewire.laravel.com/docs/4.x/uploads

- Tailwind CSS Laravel + Vite  
  https://tailwindcss.com/docs/installation/framework-guides/laravel/vite

- MySQL 8.4 Reference Manual  
  https://dev.mysql.com/doc/refman/8.4/en/

- Cloudflare R2 Pricing  
  https://developers.cloudflare.com/r2/pricing/

- Cloudflare R2 Public Buckets / Custom Domains  
  https://developers.cloudflare.com/r2/buckets/public-buckets/

- Cloudflare Turnstile server-side validation  
  https://developers.cloudflare.com/turnstile/get-started/server-side-validation/

- Google AdSense UGC guidance  
  https://support.google.com/adsense/answer/1355699

---

# 51. ORDEN DE EJECUCIÓN PARA LA IA

Comienza SOLO por la FASE 0.

Entrega al terminarla:

1. inventario del entorno;
2. incompatibilidades;
3. riesgos;
4. stack realmente disponible;
5. archivos creados;
6. resultado de checks;
7. plan exacto para Fase 1;
8. `10_PROJECT_STATE.md` actualizado.

No empieces a desarrollar Fase 1 si la auditoría revela un riesgo de romper proyectos existentes.

A partir de ahí trabaja fase por fase, verificando cada Quality Gate antes de continuar.
