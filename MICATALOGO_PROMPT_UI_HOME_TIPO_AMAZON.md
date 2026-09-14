# Prompt UI — Pantalla principal de MiCatalogo
**Destino recomendado:** Google Stitch u otra IA de diseño UI  
**Tipo:** pantalla principal responsive de una vitrina/marketplace de productos  
**Referencia UX:** patrones familiares de Amazon, SIN copiar la identidad de Amazon.

---

## PROMPT

Diseña la **pantalla principal pública de MiCatalogo**, una plataforma gratuita donde pequeños vendedores crean una vitrina de productos y comparten su enlace con clientes.

MiCatalogo **NO es un ecommerce transaccional**. No existe carrito, checkout, pagos ni órdenes. El objetivo es que el visitante encuentre productos, abra la ficha y posteriormente contacte al vendedor por WhatsApp.

Quiero una interfaz que se sienta inmediatamente familiar para alguien que usa Amazon u otros grandes catálogos: búsqueda dominante, categorías claras, alta densidad de productos, cards consistentes y navegación simple. **No copies Amazon pixel por pixel, no uses su logo, sus colores exactos, su iconografía ni su branding.**

### Identidad visual

Crear una identidad propia para MiCatalogo:

- estilo profesional, limpio y comercial;
- mobile-first;
- fondo general gris muy claro `#F5F7FA`;
- superficies blancas;
- header principal azul marino profundo cercano a `#0F172A`;
- color primario azul moderno cercano a `#2563EB`;
- disponibilidad en verde discreto;
- textos gris carbón;
- bordes suaves;
- sombras muy discretas;
- border radius moderado, no excesivo;
- evitar glassmorphism;
- evitar gradientes exagerados;
- evitar animaciones decorativas innecesarias.

La interfaz debe dar sensación de:
**confianza + rapidez + catálogo grande + simplicidad.**

---

## Desktop principal — 1440 px

Construye la pantalla de arriba hacia abajo:

### 1. Header superior

Altura compacta.

Izquierda:
- logo textual/isotipo placeholder **MiCatalogo**.

Centro:
- buscador grande y dominante ocupando la mayor parte del ancho;
- placeholder: **“¿Qué estás buscando?”**;
- icono de búsqueda;
- botón de búsqueda claramente visible.

Derecha:
- enlace **“Crear mi catálogo gratis”** como CTA principal;
- enlace/botón secundario **“Iniciar sesión”**.

No incluir carrito.

No incluir contador de carrito.

No incluir checkout.

---

### 2. Barra de categorías

Debajo del header.

Horizontal, compacta y fácil de escanear.

Categorías de ejemplo:

- Moda
- Tecnología
- Belleza
- Hogar
- Accesorios
- Calzado
- Deportes
- Vehículos
- Otros

Permitir indicar visualmente que existen más categorías.

Debe sentirse como navegación de marketplace, no como menú corporativo.

---

### 3. Franja informativa pequeña

No crear un hero gigante.

Usar una franja/bloque horizontal relativamente compacto con el mensaje:

**“¿Vendes por WhatsApp? Crea tu catálogo gratis y comparte un solo enlace.”**

CTA:
**“Crear catálogo gratis”**

Debe apoyar la captación de vendedores sin quitar protagonismo a los productos.

---

### 4. Sección “Productos recientes”

Título:
**Productos recientes**

A la derecha:
**Ver más**

Grid de productos de alta densidad:

- 5 columnas en desktop 1440 px;
- imagen cuadrada predominante;
- card blanca;
- thumbnail 1:1;
- nombre máximo 2 líneas;
- precio grande y legible;
- nombre de tienda pequeño;
- badge discreto “Disponible” o “Agotado”.

Ejemplo de card:

```text
┌──────────────────────┐
│                      │
│       PRODUCTO       │
│        FOTO          │
│                      │
├──────────────────────┤
│ Nike Air Max 270     │
│ RD$ 4,500            │
│ Brea Fashion         │
│ ● Disponible         │
└──────────────────────┘
```

La card completa debe parecer clicable.

No agregar:
- botón Comprar;
- carrito;
- estrellas falsas;
- descuento inventado;
- reviews.

---

### 5. Publicidad

Después de una o dos filas de productos mostrar un **slot publicitario horizontal discreto**, claramente separado de las product cards.

Usar en el mockup:

**PUBLICIDAD**

Debe verse como un espacio reservado, no como producto.

No debe dominar la pantalla.

---

### 6. Sección “Tiendas destacadas”

Mostrar una fila de tiendas:

- logo circular/cuadrado;
- nombre;
- categoría principal;
- enlace visual “Ver catálogo”.

Mantenerla compacta.

---

### 7. Sección “Explora por categoría”

Crear cards visuales para categorías principales.

No usar imágenes gigantes.

Diseño limpio y modular.

---

### 8. Más productos

Otra cuadrícula de productos para que la home se sienta realmente como un catálogo navegable y no como landing page.

Incluir paginación o botón:

**“Ver más productos”**

---

### 9. Footer

Footer simple.

Columnas/enlaces:

- MiCatalogo
- Crear catálogo
- Cómo funciona
- Términos
- Privacidad
- Contenido prohibido
- Reportar
- Contacto

Texto inferior:
**© MiCatalogo**

---

# Página sin funcionalidades de ecommerce

En ningún lugar diseñar:

- carrito;
- checkout;
- comprar ahora;
- método de pago;
- envío;
- seguimiento;
- wishlist compleja;
- reviews;
- estrellas si no existen datos reales.

La home es una **vitrina de descubrimiento de productos**.

---

# Responsive móvil — 390 px

Genera también adaptación móvil coherente.

### Header móvil

Primera fila:

```text
[☰] [MiCatalogo]                  [Cuenta]
```

Segunda fila:

```text
[ 🔍 ¿Qué estás buscando?              ]
```

El buscador debe mantener gran importancia.

### Categorías

Scroll horizontal con pills/cards compactos.

### Productos

2 columnas.

Cards compactas:

- imagen cuadrada;
- nombre 2 líneas;
- precio;
- tienda;
- disponibilidad.

No reducir tanto el texto que pierda legibilidad.

### CTA vendedor

Franja compacta:
**“Vende por WhatsApp con un catálogo gratis”**

### Publicidad

Banner responsive entre grupos de productos, nunca simulando una card.

---

# UX obligatoria

La pantalla debe comunicar en menos de 5 segundos:

1. aquí hay productos;
2. puedo buscar;
3. puedo navegar categorías;
4. cada producto pertenece a una tienda;
5. si soy vendedor puedo crear gratis mi catálogo.

El producto debe ser el protagonista.

Priorizar densidad útil y escaneabilidad sobre decoración.

---

# Componentes visuales que deben quedar claramente definidos

- Header
- SearchBar
- CategoryNav
- SellerCTA
- ProductCard
- ProductGrid
- AvailabilityBadge
- Price
- StoreCard
- CategoryCard
- AdSlot
- Pagination/LoadMore
- Footer

---

# Estados que el diseño debe contemplar

Crear variantes visuales o especificarlas:

### Product Card
- disponible;
- agotado;
- imagen faltante.

### Search
- normal;
- escribiendo;
- sin resultados.

### Grid
- loading con skeleton;
- vacío;
- error.

No utilizar spinners grandes.

---

# Reglas de accesibilidad

- contraste WCAG adecuado;
- focus visible;
- textos legibles;
- botones con tamaños táctiles;
- no depender únicamente del color para indicar agotado;
- alt text previsto para productos;
- jerarquía clara de headings.

---

# Resultado esperado

Entrega una interfaz que se perciba como:

**“un Amazon mucho más simple orientado a vitrinas de vendedores de WhatsApp”**

pero con identidad propia de MiCatalogo.

El diseño final debe ser directamente implementable con:

- Laravel 13
- Blade
- Livewire 4
- Tailwind CSS 4

No diseñar componentes imposibles o excesivamente dependientes de JavaScript.

Generar:
1. versión desktop 1440 px;
2. versión móvil 390 px;
3. componentes consistentes;
4. spacing y jerarquía visual profesional;
5. alta densidad de productos;
6. ninguna funcionalidad de ecommerce transaccional.
