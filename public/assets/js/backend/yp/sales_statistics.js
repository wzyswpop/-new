define(['jquery', 'bootstrap', 'backend', 'echarts', 'echarts-theme'], function ($, undefined, Backend, Echarts) {
    var Controller = {
        index: function () {
            var chartEl = document.getElementById('sales-stat-chart');
            if (!chartEl || !Config.salesStatistics) {
                return;
            }
            var chart = Echarts.init(chartEl, 'walden');
            chart.setOption({
                color: ['#3fb1e3', '#f6bd16', '#18d1b1'],
                tooltip: {
                    trigger: 'axis',
                    valueFormatter: function (value) {
                        return '￥' + Number(value || 0).toFixed(2);
                    }
                },
                legend: {
                    data: ['总销售额', '退款金额', '真实销售额']
                },
                grid: {
                    left: 56,
                    right: 24,
                    top: 48,
                    bottom: 36
                },
                xAxis: {
                    type: 'category',
                    data: Config.salesStatistics.months
                },
                yAxis: {
                    type: 'value',
                    name: '金额'
                },
                series: [
                    {
                        name: '总销售额',
                        type: 'bar',
                        barMaxWidth: 26,
                        data: Config.salesStatistics.grossSales
                    },
                    {
                        name: '退款金额',
                        type: 'bar',
                        barMaxWidth: 26,
                        data: Config.salesStatistics.refundAmount
                    },
                    {
                        name: '真实销售额',
                        type: 'line',
                        smooth: true,
                        lineStyle: {
                            normal: {
                                width: 3
                            }
                        },
                        data: Config.salesStatistics.realSales
                    }
                ]
            });
            $(window).resize(function () {
                chart.resize();
            });
        }
    };

    return Controller;
});
