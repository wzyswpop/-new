define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'yp/integral_order/index' + location.search,
                    // del_url: 'yp/integral_order/del',
                    multi_url: 'yp/integral_order/multi',
                    import_url: 'yp/integral_order/import',
                    table: 'yp_integral_order',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                fixedColumns: true,
                fixedRightNumber: 1,
                showExport:false,
                showToggle:false,
                search:false,
                showColumns:false,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'user.nickname', title: __('User.nickname'), operate: 'LIKE'},
                        {field: 'order_no', title: __('Order_no'), operate: 'LIKE'},
                        {field: 'num', title: __('Num')},
                        {field: 'goods_title', title: __('Goods_title'), operate: 'LIKE'},
                        {field: 'goods_image', title: __('Goods_image'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'score', title: __('Score')},
                        {field: 'money', title: __('Money')},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1'),"2":__('Status 2'),"3":__('Status 3')}, formatter: Table.api.formatter.status},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate,
                            buttons:[
                                {
                                    name: 'detail',
                                    text: __('查看详情'),
                                    title: __('查看详情'),
                                    classname: 'btn btn-xs btn-success btn-dialog',
                                    url: 'yp/integral_order/detail',
                                    callback: function (data) {
                                    },
                                    visible: function (row) {
                                        return true;
                                    }
                                }
                            ]
                        }
                    ]
                ]
            });
            $(document).on("click", ".btn-delivery", function () {
                if($(this).data('id')){
                    Backend.api.open('yp/integral_order/delivery/ids/' + $(this).data('id'), __('发货'),{area:['1000px', '700px']});
                }else{
                    Backend.api.open('yp/integral_order/delivery/ids/' + Table.api.selectedids(table), __('批量发货'),{area:['1000px', '700px']});
                }
            });
            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        delivery: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});
