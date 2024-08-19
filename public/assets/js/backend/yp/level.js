define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'yp/level/index' + location.search,
                    add_url: 'yp/level/add',
                    edit_url: 'yp/level/edit',
                    del_url: 'yp/level/del',
                    multi_url: 'yp/level/multi',
                    import_url: 'yp/level/import',
                    dragsort_url:'',
                    table: 'yp_level',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'weigh',
                sortOrder: 'asc',
                showExport:false,
                search:false,
                showToggle:false,
                showColumns:false,
                commonSearch:false,
                columns: [
                    [
                        // {checkbox: false},
                        // {field: 'id', title: __('Id')},
                        {field: 'weigh', title: __('Weigh'), operate: false,formatter: function (value, row, index) {
                                return value+'级';
                            }
                            },
                        {field: 'name', title: __('Name'), operate: 'LIKE',formatter: function (value, row, index) {
                            if(row.id == 1){
                                return value+'(默认等级)';
                            }else{
                                return value;
                            }
                        }
                        },
                        {field: 'money', title: __('Money'), operate:false},
                        {field: 'discount', title: __('Discount'), operate:false,formatter: function (value, row, index) {
                                if(value <= 0){
                                    return '无';
                                }else{
                                    return value + '折';
                                }
                            }},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: function (value, row, index) {

                                var that = $.extend({},this);
                                var table = $(that.table).clone(true);
                                if(row.id == 1){
                                    $(table).data('operate-del',null);
                                }
                                that.table = table;
                                return Table.api.formatter.operate.call(that, value, row, index);
                            }
                        }
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
