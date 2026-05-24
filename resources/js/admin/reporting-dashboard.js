import {
    Chart,
    BarController, BarElement,
    LineController, LineElement, PointElement,
    DoughnutController, ArcElement,
    CategoryScale, LinearScale,
    Tooltip, Legend, Title, Filler,
} from 'chart.js';

Chart.register(
    BarController, BarElement,
    LineController, LineElement, PointElement,
    DoughnutController, ArcElement,
    CategoryScale, LinearScale,
    Tooltip, Legend, Title, Filler,
);

const PALETTE = {
    primary:    '#1d4ed8',
    accent:     '#0ea5e9',
    success:    '#16a34a',
    warning:    '#f59e0b',
    danger:     '#dc2626',
    secondary:  '#475569',
    info:       '#0891b2',
};

const STATUS_COLORS = ['#94a3b8', '#f59e0b', '#16a34a', '#dc2626', '#1d4ed8'];

/**
 * Alpine component for the Reporting dashboard.
 *
 * Fetches 6 endpoints in parallel on mount, renders each into a Chart.js
 * canvas referenced via x-ref. The timeline buttons trigger a single
 * refetch for that one chart.
 */
export function reportingDashboard() {
    return {
        loading: false,
        days: 30,
        charts: {},

        async load() {
            this.loading = true;
            try {
                const [byStatus, byCentre, bySection, bySeries, bySex, timeline] = await Promise.all([
                    window.axios.get('/api/admin/reporting/by-status'),
                    window.axios.get('/api/admin/reporting/by-centre'),
                    window.axios.get('/api/admin/reporting/by-section'),
                    window.axios.get('/api/admin/reporting/by-series-bac'),
                    window.axios.get('/api/admin/reporting/by-sex'),
                    window.axios.get('/api/admin/reporting/timeline', { params: { days: this.days } }),
                ]);

                this.charts.status   = this.makeDoughnut(this.$refs.chartStatus,   byStatus.data, STATUS_COLORS);
                this.charts.centre   = this.makeBar(this.$refs.chartCentre,       byCentre.data, PALETTE.primary, 'y');
                this.charts.section  = this.makeBar(this.$refs.chartSection,      bySection.data, PALETTE.info, 'y');
                this.charts.series   = this.makeBar(this.$refs.chartSeries,       bySeries.data, PALETTE.warning);
                this.charts.sex      = this.makeDoughnut(this.$refs.chartSex,    [
                    { label: 'Hommes', value: bySex.data.male },
                    { label: 'Femmes', value: bySex.data.female },
                ], [PALETTE.primary, PALETTE.danger]);
                this.charts.timeline = this.makeLine(this.$refs.chartTimeline,    timeline.data, PALETTE.accent);
            } catch (e) {
                console.error('[reporting]', e);
            } finally {
                this.loading = false;
            }
        },

        async setDays(d) {
            this.days = d;
            const { data } = await window.axios.get('/api/admin/reporting/timeline', { params: { days: d } });
            this.charts.timeline?.destroy();
            this.charts.timeline = this.makeLine(this.$refs.chartTimeline, data, PALETTE.accent);
        },

        makeBar(canvas, rows, color, axis = 'x') {
            return new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: rows.map(r => r.label),
                    datasets: [{
                        label: 'Candidats',
                        data: rows.map(r => r.value),
                        backgroundColor: color,
                        borderRadius: 4,
                    }],
                },
                options: {
                    indexAxis: axis,
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { x: { beginAtZero: true }, y: { beginAtZero: true } },
                },
            });
        },

        makeDoughnut(canvas, rows, colors) {
            return new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels: rows.map(r => r.label),
                    datasets: [{
                        data: rows.map(r => r.value),
                        backgroundColor: colors,
                        borderColor: '#fff',
                        borderWidth: 2,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } },
                    cutout: '60%',
                },
            });
        },

        makeLine(canvas, rows, color) {
            return new Chart(canvas, {
                type: 'line',
                data: {
                    labels: rows.map(r => r.label),
                    datasets: [{
                        label: 'Inscriptions',
                        data: rows.map(r => r.value),
                        borderColor: color,
                        backgroundColor: color + '22',
                        tension: 0.3,
                        fill: true,
                        pointRadius: 2,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                },
            });
        },
    };
}
