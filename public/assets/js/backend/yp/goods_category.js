define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'yp/goods_category/index' + location.search,
                    add_url: 'yp/goods_category/add',
                    edit_url: 'yp/goods_category/edit',
                    del_url: 'yp/goods_category/del',
                    multi_url: 'yp/goods_category/multi',
                    import_url: 'yp/goods_category/import',
                    table: 'yp_goods_category',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'weigh',
                showExport:false,
                showToggle:false,
                search:false,
                showColumns:false,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id'),visible:false,operate:false},
                        {field: 'name', title: '标签名称', operate: 'LIKE'},
                        {field: 'image', title: '图片', operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'shows', title: '搭配筛选', searchList: {"1":__('显示'),"0":__('不显示')}, formatter: Table.api.formatter.status},
                        {field: 'weigh', title: '排序权重', operate: false},
                        {field: 'status', title: '状态', searchList: {"1":__('Status 1'),"2":__('Status 2')}, formatter: Table.api.formatter.status},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
            $(document).off("click.goodscategoryadd", ".ops-actions .btn-add").on("click.goodscategoryadd", ".ops-actions .btn-add", function () {
                Backend.api.open($.fn.bootstrapTable.defaults.extend.add_url, "新增标签", {area: ["80%", "80%"]});
            });
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
