define(['jquery', 'bootstrap', 'backend', 'addtabs', 'table', 'echarts', 'echarts-theme', 'template'], function ($, undefined, Backend, Datatable, Table, Echarts, undefined, Template) {

    var Controller = {
        index: function () {
            // 基于准备好的dom，初始化echarts实例
            var myChart = Echarts.init(document.getElementById('echart'), 'walden');

            // 指定图表的配置项和数据
            var option = {
                title: {
                    text: '',
                    subtext: ''
                },
                color: [
                    "#18d1b1",
                    "#3fb1e3",
                    "#626c91",
                    "#a0a7e6",
                    "#c4ebad",
                    "#96dee8"
                ],
                tooltip: {
                    trigger: 'axis'
                },
                legend: {
                    data: ['支付订单', '销售额', __('Register user')]
                },
                toolbox: {
                    show: false,
                    feature: {
                        magicType: {show: true, type: ['stack', 'tiled']},
                        saveAsImage: {show: true}
                    }
                },
                xAxis: {
                    type: 'category',
                    boundaryGap: false,
                    data: Config.column
                },
                yAxis: [
                    {
                        type: 'value',
                        name: '订单/会员'
                    },
                    {
                        type: 'value',
                        name: '销售额'
                    }
                ],
                grid: [{
                    left: 'left',
                    top: 'top',
                    right: '30',
                    bottom: 30
                }],
                series: [
                    {
                        name: '支付订单',
                        type: 'line',
                        smooth: true,
                        lineStyle: {
                            normal: {
                                width: 2
                            }
                        },
                        data: Config.orderdata
                    },
                    {
                        name: '销售额',
                        type: 'bar',
                        yAxisIndex: 1,
                        barMaxWidth: 28,
                        data: Config.moneydata
                    },
                    {
                        name: __('Register user'),
                        type: 'line',
                        smooth: true,
                        lineStyle: {
                            normal: {
                                width: 1.5
                            }
                        },
                        data: Config.userdata
                    }
                ]
            };

            // 使用刚指定的配置项和数据显示图表。
            myChart.setOption(option);

            $(window).resize(function () {
                myChart.resize();
            });

            $(document).on("click", ".btn-refresh", function () {
                setTimeout(function () {
                    myChart.resize();
                }, 0);
            });

        }
    };

    return Controller;
});
