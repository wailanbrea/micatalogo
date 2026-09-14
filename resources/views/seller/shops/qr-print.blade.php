<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cartel QR Mostrador - {{ $shop->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .print-card svg {
            width: 100% !important;
            height: 100% !important;
            max-width: 100% !important;
            max-height: 100% !important;
            display: block;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: white !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .print-card {
                border: 2px solid #0f172a !important;
                box-shadow: none !important;
                max-width: 100% !important;
                margin: 0 auto !important;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen text-slate-800 antialiased font-sans flex flex-col items-center py-6 px-4">

    <!-- Screen Control Bar (Hidden on Print) -->
    <div class="no-print mb-6 flex flex-wrap items-center justify-between gap-4 w-full max-w-lg bg-white p-4 rounded-xl shadow-xs border border-slate-200">
        <a 
            href="{{ route('seller.shops.metrics.index', $shop) }}" 
            class="text-xs font-bold text-slate-600 hover:text-slate-900 transition flex items-center gap-1.5"
        >
            ← Volver a métricas
        </a>

        <button 
            onclick="window.print()" 
            class="flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700 shadow-xs transition cursor-pointer"
        >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            Imprimir Cartel
        </button>
    </div>

    <!-- Printable Tabletop / Counter Tent Card -->
    <div class="print-card w-full max-w-md bg-white rounded-3xl border-2 border-slate-200 shadow-xl overflow-hidden p-8 sm:p-10 text-center flex flex-col items-center">
        
        <!-- Store Brand Header -->
        <div class="flex flex-col items-center mb-5">
            @if ($shop->logo_url)
                <img src="{{ $shop->logo_url }}" alt="{{ $shop->name }}" class="h-20 w-20 rounded-2xl object-cover border-2 border-slate-100 shadow-md mb-3">
            @else
                <div class="flex h-20 w-20 items-center justify-center rounded-2xl bg-blue-600 text-white font-black text-3xl shadow-md mb-3">
                    {{ strtoupper(substr($shop->name, 0, 1)) }}
                </div>
            @endif

            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">{{ $shop->name }}</h1>
            @if ($shop->description)
                <p class="text-xs text-slate-500 mt-1 max-w-xs line-clamp-2">{{ $shop->description }}</p>
            @endif
        </div>

        <!-- Banner Accent -->
        <div class="w-full bg-slate-900 text-white rounded-xl py-2 px-4 mb-5 shadow-xs">
            <p class="text-[11px] uppercase tracking-widest font-black text-amber-400">Catálogo Digital</p>
            <p class="text-sm font-bold">¡Escanea para ver todos nuestros productos!</p>
        </div>

        <!-- High Quality QR Code Box (Constrained & Responsive) -->
        <div class="p-3 bg-white rounded-2xl border-2 border-slate-900 shadow-sm flex items-center justify-center mb-5 w-56 h-56 sm:w-60 sm:h-60 overflow-hidden shrink-0">
            <div class="w-full h-full flex items-center justify-center">
                {!! $qrSvg !!}
            </div>
        </div>

        <!-- Scan Instructions -->
        <div class="max-w-xs space-y-1 mb-5">
            <p class="text-xs font-bold text-slate-700">1. Abre la cámara de tu teléfono móvil</p>
            <p class="text-xs font-bold text-slate-700">2. Apunta hacia el código QR</p>
            <p class="text-xs font-bold text-slate-700">3. Consulta precios y pide directo por WhatsApp</p>
        </div>

        <!-- Store Contact Footer -->
        <div class="w-full border-t border-slate-100 pt-4 flex flex-col items-center gap-1.5 text-xs text-slate-600 font-medium">
            <div class="flex items-center gap-2 font-mono">
                <span class="inline-block h-2 w-2 rounded-full bg-emerald-500"></span>
                <span>WhatsApp: +{{ $shop->whatsapp_country_code }} {{ $shop->whatsapp_number }}</span>
            </div>
            @if ($shop->instagram)
                <div class="text-slate-500">
                    Instagram: @<span class="font-semibold">{{ $shop->instagram }}</span>
                </div>
            @endif
            <p class="text-[10px] text-slate-400 font-mono mt-2">
                {{ $shopUrl }} · MiCatalogo.com.do
            </p>
        </div>

    </div>

</body>
</html>
