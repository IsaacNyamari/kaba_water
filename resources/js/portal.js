// Client portal entry — lightweight, no framework bloat.
import Chart from 'chart.js/auto';

window.PortalCharts = {
    sparkline(el, data, labels) {
        return new Chart(el, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    data,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37,99,235,0.08)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 0,
                }],
            },
            options: {
                plugins: { legend: { display: false } },
                scales: { x: { display: false }, y: { display: false } },
                maintainAspectRatio: false,
            },
        });
    },
};