define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'yp/integral_log/index' + location.search,
                    del_url: 'yp/integral_log/del',
                    multi_url: 'yp/integral_log/multi',
                    import_url: 'yp/integral_log/import',
                    table: 'yp_integral_log',
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
                        {field: 'num', title: __('Num'),operate:false},
                        {field: 'before', title: __('Before'),operate:false},
                        {field: 'after', title: __('After'),operate:false},
                        {field: 'memo', title: __('Memo'), operate: 'LIKE'},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'type', title: __('Type'), searchList: {"add":__('Type add'),"sub":__('Type sub')}, formatter: Table.api.formatter.normal},
                        {field: 'change_type', title: __('Change_type'), searchList: {"pay":__('Change_type pay'),"pay_integral":__('Change_type pay_integral'),"cancel":__('Change_type cancel'),'admin':__('Change_type admin'),'sign':__('Change_type sign'),'send':__('Change_type send')}, formatter: Table.api.formatter.normal},
                        {field: 'order_no', title: __('Order_no'), operate: 'LIKE'},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });
            $('.btn-export_log').click(function (){
                var options = table.bootstrapTable('getOptions');
                var search = options.queryParams({});
                var filter = search.filter;
                var op = search.op;
                location.href = Config.SCRIPT_NAME + '/yp/Integral_log/export_log' + '?f=' + filter + '&o=' + op;
            });
            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
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
