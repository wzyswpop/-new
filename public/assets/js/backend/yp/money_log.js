define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'yp/money_log/index' + location.search,
                    del_url: 'yp/money_log/del',
                    multi_url: 'yp/money_log/multi',
                    import_url: 'yp/money_log/import',
                    table: 'yp_money_log',
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
                        // {field: 'id', title: __('Id')},
                        {field: 'user.nickname', title: __('User.nickname'), operate: 'LIKE'},
                        {field: 'num', title: __('Num'), operate:false},
                        {field: 'before', title: __('Before'), operate:false},
                        {field: 'after', title: __('After'), operate:false},
                        {field: 'memo', title: __('Memo'), operate: 'LIKE'},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'type', title: __('Type'), searchList: {"add":__('Type add'),"sub":__('Type sub')}, formatter: Table.api.formatter.normal},
                        {field: 'change_type', title: __('Change_type'), searchList: {"pay":__('Change_type pay'),'withdrawal':__('Withdrawal'),"service_order":__('Change_type service_order'),"sign":__('Change_type sign'),"recharge":__('Change_type recharge')}, formatter: Table.api.formatter.normal},
                        {field: 'order_no', title: __('Order_no'), operate: 'LIKE'},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        commission: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'yp/money_log/commission' + location.search,
                    del_url: 'yp/money_log/del',
                    multi_url: 'yp/money_log/multi',
                    import_url: 'yp/money_log/import',
                    table: 'yp_money_log',
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
                        // {field: 'id', title: __('Id')},
                        {field: 'user.nickname', title: __('User.nickname'), operate: 'LIKE'},
                        {field: 'num', title: __('Num'), operate:false},
                        {field: 'before', title: __('Before'), operate:false},
                        {field: 'after', title: __('After'), operate:false},
                        {field: 'memo', title: __('Memo'), operate: 'LIKE'},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'type', title: __('Type'), searchList: {"add":__('Type add'),"sub":__('Type sub')}, formatter: Table.api.formatter.normal},
                        {field: 'change_type', title: __('Change_type'), searchList: {"commission":__('Commission'),'withdrawal':__('Withdrawal')}, formatter: Table.api.formatter.normal},
                        {field: 'order_no', title: __('Order_no'), operate: 'LIKE'},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
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
