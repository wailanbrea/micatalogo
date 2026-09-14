---
name: Modern Retail Catalog System
colors:
  surface: '#f8f9ff'
  surface-dim: '#cbdbf5'
  surface-bright: '#f8f9ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#eff4ff'
  surface-container: '#e5eeff'
  surface-container-high: '#dce9ff'
  surface-container-highest: '#d3e4fe'
  on-surface: '#0b1c30'
  on-surface-variant: '#434655'
  inverse-surface: '#213145'
  inverse-on-surface: '#eaf1ff'
  outline: '#737686'
  outline-variant: '#c3c6d7'
  surface-tint: '#0053db'
  primary: '#004ac6'
  on-primary: '#ffffff'
  primary-container: '#2563eb'
  on-primary-container: '#eeefff'
  inverse-primary: '#b4c5ff'
  secondary: '#565e74'
  on-secondary: '#ffffff'
  secondary-container: '#dae2fd'
  on-secondary-container: '#5c647a'
  tertiary: '#006242'
  on-tertiary: '#ffffff'
  tertiary-container: '#007d55'
  on-tertiary-container: '#bdffdb'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#dbe1ff'
  primary-fixed-dim: '#b4c5ff'
  on-primary-fixed: '#00174b'
  on-primary-fixed-variant: '#003ea8'
  secondary-fixed: '#dae2fd'
  secondary-fixed-dim: '#bec6e0'
  on-secondary-fixed: '#131b2e'
  on-secondary-fixed-variant: '#3f465c'
  tertiary-fixed: '#6ffbbe'
  tertiary-fixed-dim: '#4edea3'
  on-tertiary-fixed: '#002113'
  on-tertiary-fixed-variant: '#005236'
  background: '#f8f9ff'
  on-background: '#0b1c30'
  surface-variant: '#d3e4fe'
  surface-canvas: '#F5F7FA'
  surface-card: '#FFFFFF'
  border-subtle: '#E2E8F0'
  border-strong: '#CBD5E1'
  text-heading: '#0F172A'
  text-body: '#1E293B'
  text-muted: '#64748B'
  status-available-bg: '#ECFDF5'
  status-available-text: '#065F46'
  status-available-dot: '#10B981'
  status-out-bg: '#F1F5F9'
  status-out-text: '#64748B'
  status-out-dot: '#94A3B8'
  header-navy: '#0F172A'
  header-navy-hover: '#1E293B'
  ad-slot-bg: '#F1F5F9'
  ad-slot-border: '#CBD5E1'
typography:
  headline-xl:
    fontFamily: Inter
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 40px
    letterSpacing: -0.02em
  headline-xl-mobile:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '700'
    lineHeight: 32px
    letterSpacing: -0.01em
  headline-lg:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '700'
    lineHeight: 32px
    letterSpacing: -0.015em
  headline-lg-mobile:
    fontFamily: Inter
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
    letterSpacing: -0.01em
  headline-md:
    fontFamily: Inter
    fontSize: 18px
    fontWeight: '600'
    lineHeight: 26px
    letterSpacing: -0.01em
  headline-sm:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '600'
    lineHeight: 24px
  body-lg:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  body-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  body-sm:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '400'
    lineHeight: 16px
  price-lg:
    fontFamily: Inter
    fontSize: 20px
    fontWeight: '700'
    lineHeight: 24px
    letterSpacing: -0.02em
  price-md:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '700'
    lineHeight: 20px
    letterSpacing: -0.01em
  label-md:
    fontFamily: Inter
    fontSize: 13px
    fontWeight: '500'
    lineHeight: 16px
  label-sm:
    fontFamily: Inter
    fontSize: 11px
    fontWeight: '600'
    lineHeight: 14px
    letterSpacing: 0.02em
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  gutter: 1rem
  gutter-desktop: 1.25rem
  margin: 1rem
  margin-tablet: 1.5rem
  margin-desktop: 2rem
  space-xs: 0.25rem
  space-sm: 0.5rem
  space-md: 0.75rem
  space-lg: 1.25rem
  space-xl: 2rem
---

## Brand & Style

This design system is built for a non-transactional marketplace and product showcase platform engineered specifically for independent merchants who sell via WhatsApp. It blends the high-density, scan-optimized information architecture of major international marketplaces (such as Amazon) with a clean, contemporary, and approachable visual style.

### Target Audience & Persona
- **End Shoppers & Browsers:** Users searching for everyday items, fashion, electronics, and local goods on mobile devices or desktops, expecting immediate visual confirmation, clear pricing, and straightforward merchant identification without checkout friction.
- **Micro & Independent Sellers:** Small business operators looking for a polished, credible home for their inventory that conveys trust, scale, and operational legitimacy without requiring technical expertise or payment integrations.

### Design Principles & Emotional Tone
- **Utilitarian Speed & Familiarity:** Visual pathways (search, category strips, 5-column and 2-column product grids) mimic established e-commerce ergonomics so users instantly know how to search and scan within 5 seconds.
- **Commercial Trust & Authenticity:** Restrained deep-navy anchors, high-contrast typography, and explicit availability statuses project transparency. No dark patterns, synthetic countdowns, simulated stock rushes, or fake star ratings are permitted.
- **Light & Functional Restraint:** Adopting a refined Modern Corporate/Commercial aesthetic: stark white card surfaces resting against an ultra-light gray foundation (`#F5F7FA`), disciplined 1px borders (`#E2E8F0`), and crisp, non-distracting ambient drop shadows. Glassmorphism, intense blur effects, and ornamental gradients are strictly avoided to ensure low computational overhead and rapid page assembly in server-rendered environments.

## Colors

The color architecture is calibrated to deliver immediate commercial credibility, high visual density, and WCAG AA/AAA compliance across all interface tiers.

### Functional Palette Logic
- **Header & Structural Anchor (`#0F172A`):** Deep Navy serves as the structural foundation for the main navigation bar, creating a crisp, distraction-free frame for the primary search input and utility actions.
- **Primary Brand Action (`#2563EB`):** A vibrant, authoritative blue deployed strictly for interactive focal points: the search submission trigger, primary call-to-action buttons ("Crear mi catálogo gratis"), active category chips, link hovers, and high-priority directional controls.
- **Canvas & Elevation Grays (`#F5F7FA` & `#FFFFFF`):** The general background uses `#F5F7FA` to create a soft, glare-free working area that makes white product cards (`#FFFFFF`) pop forward organically without relying on heavy borders or exaggerated dropshadows.
- **Inventory Statuses:**
  - *Disponible:* A muted, clean mint container (`#ECFDF5`) with deep emerald text (`#065F46`) and a bright green dot indicator (`#10B981`), ensuring readability against white product cards.
  - *Agotado:* A de-saturated slate pill (`#F1F5F9`) with neutral graphite text (`#64748B`) and slate dot (`#94A3B8`).
- **Typography Hierarchy:** Text colors avoid pitch black (`#000000`) in favor of `#0F172A` for headers and prices, `#1E293B` for product titles and primary labels, and `#64748B` for secondary store attributions, counts, and metadata.

## Typography

The type system runs exclusively on **Inter** to ensure maximum cross-platform rendering fidelity, high numeral legibility for catalog pricing, and neutral, utilitarian rhythm.

### Editorial & Display Directives
- **Zero Ambiguity Price Display:** Prices employ a strict heavy weight (`FontWeight: 700`) with tight tracking (`letterSpacing: -0.02em`), pairing the currency symbol in medium weight (`RD$`) with whole numbers to prevent digit confusion.
- **Product Title Line Clamping:** Product cards enforce a strict 2-line maximum clamp (`line-clamp-2`, `height: 40px` on desktop) using `body-md` (14px/20px). This maintains systematic row alignment regardless of varying title lengths.
- **Store Identification & Metadata:** Vendor attribution uses `body-sm` (12px) in `#64748B` to keep the focus on the product while ensuring shop attribution remains clear.
- **Responsive Scaling:** Section headers dynamically drop from 24px desktop down to 20px on mobile (`390px`), ensuring section navigation headers don't cause awkward line-breaks alongside "Ver más" links.

## Layout & Spacing

The layout is built around a responsive, high-density catalog model optimized for scannability and content density.

### Breakpoint Strategy & Grids
- **Desktop (1440px Canvas):**
  - Main container is centered with a max-width of `1400px` and `2rem` (`32px`) lateral margins.
  - **Product Grid:** 5 columns per row, using `1.25rem` (`20px`) gutters. This achieves Amazon-like density without visual clutter.
  - **Store Grid:** 6 columns per row for compact merchant discovery.
  - **Category Cards Grid:** 6 to 8 columns of compact icon/image cards.
- **Tablet (768px - 1024px):**
  - 3 to 4 product columns with `1rem` gutters and `1.5rem` margins.
- **Mobile (390px Default):**
  - Strict 2-column product grid with `0.75rem` (`12px`) gutters and `1rem` (`16px`) outer margins.
  - Categories shift to a single-line horizontal scroll container (`flex` with `overflow-x-auto`, no scrollbars, `gap-2`).

### Vertical Rhythm
- Section vertical margins are standardized at `2rem` (32px) on mobile and `2.5rem` (40px) on desktop to keep the showcase compact and catalog-like, avoiding the airy emptiness of marketing landing pages.

## Elevation & Depth

This system avoids heavy shadows, layered blur effects, or multi-tiered floating surfaces. Depth is established through crisp surface contrast and subtle structural borders.

### Surface System
- **Ground Floor:** `#F5F7FA` (Neutral Canvas).
- **Interactive Planes (Cards, Bars, Inputs):** Pure `#FFFFFF` surfaces bounded by a 1px uniform border in `#E2E8F0`.
- **Top Command Deck (Header):** Deep solid `#0F172A` with zero transparency. Provides a clear visual cap that grounds the user during infinite scroll.

### Shadow Specifications
- **Resting Product & Store Cards:**
  - `box-shadow: 0 1px 2px 0 rgba(15, 23, 42, 0.04);`
  - Subtle, clean elevation that defines card edges over the light gray canvas.
- **Card Hover State (Pointer devices only):**
  - `box-shadow: 0 4px 12px -2px rgba(15, 23, 42, 0.08), 0 2px 4px -2px rgba(15, 23, 42, 0.04);`
  - Subtly shifts card border color from `#E2E8F0` to `#CBD5E1`. Transform movements should be minimal (maximum `-1px` or `-2px` Y-axis shift) to maintain visual stability in dense grids.
- **Dropdowns, Autocomplete, & Modals:**
  - `box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.1), 0 8px 10px -6px rgba(15, 23, 42, 0.05);`

## Shapes

The interface uses restrained, moderate corner radiuses to reinforce its commercial catalog character. Overly circular components (pill buttons on large surfaces, hyper-rounded cards) are avoided to preserve layout structure and scan density.

### Radius Scale Implementation
- **Cards (Product, Store, Category):** `rounded-md` (`0.375rem` / `6px`) to `rounded-lg` (`0.5rem` / `8px`). Keeps corners clean without clipping image edges.
- **Product Image Containers:** `0.375rem` (`6px`) top radius matching the card outline, strictly square aspect ratio (1:1).
- **Inputs & Search Components:** `0.375rem` (`6px`) on desktop combined units, or `0.5rem` (`8px`) for standalone inputs.
- **Badges & Tags:** `rounded` (`0.25rem` / `4px`) or `rounded-full` strictly for small status pills (Availability indicators).
- **Merchant Avatars:** Circular (`rounded-full`) for store profile pictures to contrast against rectangular product thumbnails.

## Components

### 1. Main Navigation Header
- **Desktop (1440px):**
  - Single compact bar (64px height), background `#0F172A`.
  - **Left:** Brand wordmark "MiCatalogo" in bold white typography with a clean primary-blue accent dot.
  - **Center:** Dominant search bar taking 50–60% of header width. Unified input group containing a search icon, input field with placeholder *"¿Qué estás buscando?"*, and an integrated right-aligned action button in `#2563EB` with hover state `#1D4ED8`.
  - **Right:** Text link "Iniciar sesión" in `#E2E8F0` followed by the high-visibility primary CTA button "Crear mi catálogo gratis" (solid `#2563EB`, text white, `rounded-md`, 10px 16px padding). No cart icon or cart counters anywhere in the DOM.
- **Mobile (390px):**
  - Row 1 (48px height): Menu trigger icon, centered brand wordmark, and compact "Acceder" link.
  - Row 2 (52px height): Full-width dominant search field pinned under row 1 with persistent white background and clear submit trigger.

### 2. Category Navigation Bar
- **Desktop:** Secondary horizontal bar beneath header (44px height), background `#FFFFFF`, border-bottom 1px `#E2E8F0`. Horizontal list of categories (Moda, Tecnología, Belleza, Hogar, Accesorios, Calzado, Deportes, Vehículos, etc.) in 13px medium `#334155` with hover `#2563EB`. Ends with an "Explorar todas ▾" dropdown trigger.
- **Mobile:** Horizontal scroll strip (`overflow-x-auto` with scrollbar hidden), category pills with 1px `#E2E8F0` border, white background, 6px 12px padding, and 12px typography.

### 3. Seller Acquisition Strip (Compact Banner)
- A non-intrusive horizontal banner positioned above the product feed.
- Height: 56px–64px desktop, auto mobile.
- Background: Very subtle cool slate/blue tint (`#EFF6FF`), border: 1px solid `#DBEAFE`.
- Layout: Left side features WhatsApp-aligned copy: *"¿Vendes por WhatsApp? Crea tu catálogo gratis y comparte un solo enlace."* Right side contains an outline/solid button *"Crear catálogo gratis"*. It delivers seller conversion without distracting shoppers from product discovery.

### 4. Product Card & Availability Indicators
- **Card Structure:** 100% white surface, 1px `#E2E8F0` border, `rounded-md` (6px), relative positioning. Entire card is wrapped in a clickable anchor linking to the product detail view.
- **Image Frame:** Fixed 1:1 aspect ratio square container with `object-cover` image positioning. Fallback state: a neutral slate placeholder icon on `#F8FAFC` background with a subtle watermark icon.
- **Content Area:** 12px padding (`0.75rem`).
  - **Title:** 14px Inter, semi-bold (`font-weight: 600`), `#1E293B`, clamped at 2 lines (min-height 40px).
  - **Price:** 18px–20px Inter, bold (`font-weight: 700`), `#0F172A`. Display format: `RD$ 4,500`.
  - **Merchant Link:** 12px Inter, regular, `#64748B`. Displays the store name (e.g., "Brea Fashion") with a small store icon.
  - **Availability Badge:**
    - *Disponible:* Inline pill with 2px 8px padding, `#ECFDF5` background, `#065F46` text, featuring a 6px circular green dot (`#10B981`) on the left.
    - *Agotado:* Inline pill with `#F1F5F9` background, `#64748B` text, featuring a 6px gray dot (`#94A3B8`).
- **Forbidden Elements:** No "Añadir al carrito", no quick-buy buttons, no fake 5-star ratings, no false discount badges.

### 5. Advertisement Slot (AdSlot)
- Clean, clearly separated responsive horizontal block placed after the first 2 rows of products.
- Frame: 1px dashed `#CBD5E1` border, `#F1F5F9` background, `rounded-md`.
- Label: Distinct uppercase label "PUBLICIDAD" in 10px `#94A3B8` tracking-widest placed in top corner.
- Ensures explicit visual divergence so it is never mistaken for a product card.

### 6. Store Showcase ("Tiendas destacadas")
- Row of compact horizontal or vertical cards representing active sellers:
  - 48px circular avatar/logo with 1px `#E2E8F0` border.
  - Merchant title (14px bold).
  - Primary category subtitle (12px muted).
  - Subtle text action: "Ver catálogo →" in `#2563EB`.

### 7. Pagination & Catalog Continuation
- A centered, high-density control: "Ver más productos" secondary button (white background, 1px `#CBD5E1` border, `#1E293B` text, hover `#F8FAFC`), or traditional numeric pagination controls with standard active state highlights.

### 8. Footer
- Background `#0F172A`, text `#94A3B8`.
- 4 clean link columns (MiCatalogo, Vendedores, Legal, Soporte) with high-contrast `#FFFFFF` section headings.
- Baseline bottom row: Copyright attribution "© MiCatalogo — Catálogo y vitrina para vendedores independientes" and security disclaimer.