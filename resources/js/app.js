import './bootstrap';
import Chart from 'chart.js/auto';

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

const initializeSalesCharts = () => {
    document.querySelectorAll('[data-sales-chart]').forEach((canvas) => {
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
