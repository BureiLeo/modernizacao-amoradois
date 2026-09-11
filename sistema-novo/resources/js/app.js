// ApexCharts carregado sob demanda (dynamic import) para nao pesar o
// bundle inicial em paginas que nao tem grafico (mobile-first: evita
// ~266KB gzip desnecessarios na maioria das telas).
document.addEventListener('alpine:init', () => {
    window.Alpine.data('salesChart', (initial) => ({
        chart: null,

        async init() {
            const { default: ApexCharts } = await import('apexcharts');
            const target = this.$el.querySelector('[data-chart-canvas]');

            this.chart = new ApexCharts(target, this.options(ApexCharts, initial));
            this.chart.render();
        },

        update(payload) {
            if (! this.chart) return;

            this.chart.updateOptions({
                xaxis: { categories: payload.categories },
            });
            this.chart.updateSeries([{ name: 'Faturamento', data: payload.values }]);
        },

        options(ApexCharts, data) {
            return {
                chart: {
                    type: 'area',
                    height: 260,
                    toolbar: { show: false },
                    fontFamily: 'Figtree, sans-serif',
                },
                series: [{ name: 'Faturamento', data: data.values }],
                xaxis: {
                    categories: data.categories,
                    labels: { style: { colors: '#8A6F63' } },
                    axisBorder: { show: false },
                    axisTicks: { show: false },
                },
                yaxis: {
                    labels: {
                        style: { colors: '#8A6F63' },
                        formatter: (v) => 'R$ ' + Number(v).toLocaleString('pt-BR', { maximumFractionDigits: 0 }),
                    },
                },
                colors: ['#953C36'],
                fill: {
                    type: 'gradient',
                    gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.02 },
                },
                stroke: { curve: 'smooth', width: 2 },
                dataLabels: { enabled: false },
                grid: { borderColor: '#E8C8C2', strokeDashArray: 4 },
                tooltip: {
                    y: { formatter: (v) => 'R$ ' + Number(v).toLocaleString('pt-BR', { minimumFractionDigits: 2 }) },
                },
            };
        },
    }));
});
