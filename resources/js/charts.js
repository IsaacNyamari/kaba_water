import {
    Chart,
    LineController, LineElement, PointElement, LinearScale, CategoryScale,
    BarController, BarElement,
    DoughnutController, ArcElement,
    Tooltip, Legend, Filler,
} from 'chart.js';

Chart.register(
    LineController, LineElement, PointElement, LinearScale, CategoryScale,
    BarController, BarElement,
    DoughnutController, ArcElement,
    Tooltip, Legend, Filler,
);

/**
 * Alpine data factory for a Chart.js canvas.
 *
 * Robust against wire:navigate page swaps:
 *   - Only renders when the canvas is still in the DOM.
 *   - Destroys the chart before navigation swaps the page.
 *   - Re-renders when returning to a cached page.
 *   - Cleans up its MutationObserver and listeners on Alpine teardown.
 */
window.chart = (data = null, options = {}) => ({
    chart: null,
    observer: null,

    init() {
        if (! data) return;

        // Render once the DOM has settled (canvas ref exists).
        this.$nextTick(() => this.render(data, options));

        // Re-render on theme toggle (Flux writes "dark" class on <html>).
        this.observer = new MutationObserver(() => {
            if (this.$refs.canvas?.isConnected) {
                this.render(data, options);
            }
        });
        this.observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['class'],
        });

        // Destroy BEFORE Livewire swaps the page so Chart.js's animator
        // never draws into a detached canvas.
        this._onNavigating = () => this.teardownChart();
        document.addEventListener('livewire:navigating', this._onNavigating);

        // Re-render AFTER the new (or cached) page is on screen.
        this._onNavigated = () => {
            if (this.$refs.canvas?.isConnected) {
                this.render(data, options);
            }
        };
        document.addEventListener('livewire:navigated', this._onNavigated);
    },

    render(data, options) {
        const canvas = this.$refs.canvas;

        // Bail if the canvas is missing or detached (SPA swap in progress).
        if (! canvas || ! canvas.isConnected) return;

        this.teardownChart();

        const ctx = canvas.getContext('2d');
        if (! ctx) return;

        this.chart = new Chart(ctx, this.applyTheme(data, options));
    },

    teardownChart() {
        if (this.chart) {
            try { this.chart.destroy(); } catch (_) { /* swallow */ }
            this.chart = null;
        }
    },

    /**
     * Alpine calls this when the component is torn down (wire:navigate
     * away, x-if removed, etc.). Remove observers and listeners.
     */
    destroy() {
        this.teardownChart();

        if (this.observer) {
            this.observer.disconnect();
            this.observer = null;
        }

        document.removeEventListener('livewire:navigating', this._onNavigating);
        document.removeEventListener('livewire:navigated',  this._onNavigated);
    },

    applyTheme(data, options) {
        const dark = document.documentElement.classList.contains('dark');
        const grid = dark ? 'rgba(255,255,255,0.06)' : 'rgba(15,23,42,0.06)';
        const tick = dark ? '#a1a1aa' : '#71717a';

        const scales = data.type === 'doughnut' ? {} : {
            x: {
                grid: { color: grid, drawTicks: false },
                ticks: { color: tick, font: { size: 11 } },
                border: { display: false },
            },
            y: {
                grid: { color: grid, drawTicks: false },
                ticks: { color: tick, font: { size: 11 }, precision: 0 },
                border: { display: false },
                beginAtZero: true,
            },
        };

        return {
            type: data.type,
            data: {
                labels: data.labels,
                datasets: (data.datasets || []).map(ds => ({
                    tension: 0.35,
                    borderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    ...ds,
                })),
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        display: !! options.showLegend,
                        position: options.legendPosition || 'bottom',
                        labels: { color: tick, boxWidth: 10, boxHeight: 10, padding: 12, usePointStyle: true },
                    },
                    tooltip: {
                        backgroundColor: dark ? '#18181b' : '#ffffff',
                        titleColor: dark ? '#fafafa' : '#0f172a',
                        bodyColor: dark ? '#d4d4d8' : '#334155',
                        borderColor: dark ? '#27272a' : '#e2e8f0',
                        borderWidth: 1,
                        padding: 10,
                        cornerRadius: 8,
                        displayColors: false,
                    },
                },
                scales,
                ...options.chartOptions,
            },
        };
    },
});