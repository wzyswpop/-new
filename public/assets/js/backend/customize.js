define(['jquery', 'bootstrap', 'backend', 'table', 'form','selectpage'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'customize/index' + location.search,
                    add_url: 'customize/add',
                    edit_url: 'customize/edit',
                    del_url: 'customize/del',
                    multi_url: 'customize/multi',
                    import_url: 'customize/import',
                    table: 'customize',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'weigh',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'baking', title: __('烘焙程度'), operate: 'LIKE'},
                        {field: 'title', title: __('Title'), operate: 'LIKE'},
                        {field: 'desc', title: __('Desc'), operate: 'LIKE'},
                        {field: 'data', title: __('Data'), operate: 'LIKE'},
                        {field: 'sale', title: __('Sale')},
                        {field: 'price', title: __('价格')},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1')}, formatter: Table.api.formatter.status},
                        {field: 'weigh', title: __('Weigh'), operate: false},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();
            Controller.api.fieldlistBind();
        },
        edit: function () {
            Controller.api.bindevent();
            Controller.api.fieldlistBind();

        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            },
            fieldlistBind: function () {
                $(document).on(
                    "fa.event.appendfieldlist",
                    ".btn-append",
                    function () {// e:事件对象 el:当前行对象
                        Form.events.datetimepicker($("form"));
                        Form.events.selectpage($("form"));
                    }
                );
            }
        }
    };
    return Controller;
});
