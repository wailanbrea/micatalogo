import './bootstrap';

let chartModulePromise;

const loadChart = () => {
    chartModulePromise ??= import('chart.js/auto').then((module) => module.default ?? module);
    return chartModulePromise;
};

const initializeLiquidLevels = () => {
    document.querySelectorAll('[data-liquid-level]').forEach((level) => {
        const key = `micatalogo:${level.dataset.liquidKey}`;
        const current = Number(level.dataset.liquidLevel);
        const fill = level.querySelector('.liquid-level__fill');

        if (!fill || !Number.isFinite(current)) {
            return;
        }

        try {
            const storedPrevious = window.localStorage.getItem(key);
            const previous = storedPrevious === null ? null : Number(storedPrevious);
            if (previous !== null && Number.isFinite(previous) && previous !== current) {
                fill.style.height = `${previous}%`;
                requestAnimationFrame(() => {
                    fill.style.height = `${current}%`;
                });
            }

            window.localStorage.setItem(key, String(current));
        } catch {
            // Storage can be unavailable in private browsing or blocked contexts.
        }
    });
};

const initializeSalesCharts = async () => {
    const canvases = [...document.querySelectorAll('[data-sales-chart]')]
        .filter((canvas) => canvas.dataset.chartReady !== 'true');

    if (!canvases.length) {
        return;
    }

    const Chart = await loadChart();

    canvases.forEach((canvas) => {
        if (canvas.dataset.chartReady === 'true') {
            return;
        }

        const data = JSON.parse(canvas.dataset.chart || '{}');
        const metric = canvas.dataset.chartMetric || 'quantity';
        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: data.labels || [],
                datasets: [{
                    label: metric === 'revenue' ? 'Ingresos (RD$)' : 'Unidades vendidas',
                    data: metric === 'revenue' ? (data.revenue || []) : (data.quantities || []),
                    backgroundColor: metric === 'revenue' ? '#10b981' : '#2563eb',
                    borderRadius: 8,
                    maxBarThickness: 42,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { intersect: false, mode: 'index' },
                },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, ticks: { precision: 0 } },
                },
            },
        });
        canvas.dataset.chartReady = 'true';
    });
};

const initializeDashboardWidgets = () => {
    initializeLiquidLevels();
    initializeSalesCharts();
};

// HTML number inputs still allow characters such as `e`, `+` and `-` in many
// browsers. Keep quantities and other numeric fields strict at the point of
// entry; server-side validation remains the final authority.
const numericInputMode = (input) => {
    const descriptor = `${input.name || ''} ${input.id || ''} ${input.getAttribute('aria-label') || ''}`.toLowerCase();
    const integer = input.step === '1' || /quantity|cantidad|stock|existenc|volume|volumen|header_row|seats|unidades|ml/.test(descriptor);
    return integer ? 'integer' : 'decimal';
};

const sanitizeNumericInput = (input) => {
    if (!(input instanceof HTMLInputElement) || input.type !== 'number') {
        return;
    }

    const integer = numericInputMode(input) === 'integer';
    let value = input.value.replace(integer ? /[^0-9]/g : /[^0-9.,]/g, '');
    if (!integer) {
        value = value.replace(',', '.');
        const firstDot = value.indexOf('.');
        if (firstDot !== -1) {
            value = value.slice(0, firstDot + 1) + value.slice(firstDot + 1).replace(/\./g, '');
        }
    }

    input.inputMode = integer ? 'numeric' : 'decimal';
    if (input.value !== value) {
        input.value = value;
        input.dispatchEvent(new Event('input', { bubbles: true }));
    }
};

let navigationProgressTimer;

const startNavigationProgress = () => {
    const navigationProgress = document.querySelector('[data-navigation-progress]');
    if (!navigationProgress) {
        return;
    }

    window.clearTimeout(navigationProgressTimer);
    navigationProgress.classList.remove('is-complete');
    navigationProgress.classList.add('is-active');
};

const finishNavigationProgress = () => {
    const navigationProgress = document.querySelector('[data-navigation-progress]');
    if (!navigationProgress) {
        return;
    }

    navigationProgress.classList.remove('is-active');
    navigationProgress.classList.add('is-complete');
    navigationProgressTimer = window.setTimeout(() => {
        navigationProgress.classList.remove('is-complete');
    }, 220);
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeDashboardWidgets, { once: true });
} else {
    initializeDashboardWidgets();
}

// Livewire replaces the page body during wire:navigate without re-running the
// module. Reinitialize widgets after every client-side navigation.
document.addEventListener('livewire:navigated', initializeDashboardWidgets);
document.addEventListener('livewire:navigating', startNavigationProgress);
document.addEventListener('livewire:navigated', finishNavigationProgress);

document.addEventListener('beforeinput', (event) => {
    const input = event.target;
    if (!(input instanceof HTMLInputElement) || input.type !== 'number' || !event.data) {
        return;
    }

    const pattern = numericInputMode(input) === 'integer' ? /[^0-9]/ : /[^0-9.,]/;
    if (pattern.test(event.data)) {
        event.preventDefault();
    }
});

document.addEventListener('keydown', (event) => {
    const input = event.target;
    if (!(input instanceof HTMLInputElement) || input.type !== 'number') {
        return;
    }

    if (['e', 'E', '+', '-'].includes(event.key) || (numericInputMode(input) === 'integer' && ['.', ','].includes(event.key))) {
        event.preventDefault();
    }
});

document.addEventListener('input', (event) => sanitizeNumericInput(event.target));
document.querySelectorAll('input[type="number"]').forEach(sanitizeNumericInput);

// Keep full-page fallbacks feeling like the same app as wire:navigate. Some
// admin actions intentionally use a normal request (exports, forms and new
// tabs), so only panel links without modifiers get the immediate response cue.
document.addEventListener('click', (event) => {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }

    const link = event.target.closest?.('a[href]');
    if (!link || link.target === '_blank' || link.hasAttribute('download') || link.hasAttribute('wire:navigate')) {
        return;
    }

    try {
        const url = new URL(link.href, window.location.href);
        if (url.origin === window.location.origin && (/^\/panel\//.test(url.pathname) || /^\/admin\//.test(url.pathname))) {
            startNavigationProgress();
        }
    } catch {
        // Ignore malformed or javascript links.
    }
});

// Puntto-style keyboard affordance for the most frequent action in the POS.
document.addEventListener('keydown', (event) => {
    if (event.key !== 'F2' || event.defaultPrevented) {
        return;
    }

    const search = document.querySelector('#pos-search');
    if (search) {
        event.preventDefault();
        search.focus();
    }
});
