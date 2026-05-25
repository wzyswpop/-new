define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var compact = function (value, length) {
        value = value == null ? '' : value.toString();
        return value.length > length ? value.substring(0, length) + '...' : value;
    };
    var metrics = function (value, row) {
        return '<span class="hc-wall-metrics">' +
            '<span class="muted">复</span> ' + (row.copy_count || 0) +
            '<span class="muted">存</span> ' + (row.favorite_count || 0) +
            '<span class="muted">评</span> ' + (row.feedback_count || 0) +
            '<span class="muted">单</span> ' + (row.order_count || 0) +
            '</span>';
    };
    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'yp/user_recipe/index' + location.search,
                    edit_url: 'yp/user_recipe/edit',
                    table: 'yp_user_recipe'
                }
            });

            var table = $("#table");
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'updatetime',
                sortOrder: 'desc',
                showExport: false,
                search: true,
                showToggle: false,
                showColumns: false,
                columns: [[
                    {checkbox: true},
                    {field: 'id', title: __('Id'), operate: false, width: 60},
                    {field: 'user.nickname', title: __('User.nickname'), operate: 'LIKE', width: 120},
                    {field: 'name', title: __('Name'), operate: 'LIKE', cellStyle: function () { return {css: {'min-width': '150px'}}; }},
                    {field: 'bean_summary', title: __('Bean_summary'), operate: false, formatter: function (value) { return compact(value, 90); }, cellStyle: function () { return {css: {'min-width': '280px', 'white-space': 'normal'}}; }},
                    {field: 'baking', title: __('Baking'), operate: 'LIKE'},
                    {field: 'scene_tags', title: __('Scene_tags'), operate: 'LIKE', visible: false},
                    {field: 'flavor_tags', title: __('Flavor_tags'), operate: 'LIKE', visible: false},
                    {field: 'author_title', title: __('Author_title'), operate: 'LIKE', visible: false},
                    {field: 'public_status', title: __('Public_status'), searchList: {private: __('Public_status private'), public: __('Public_status public')}, formatter: Table.api.formatter.normal},
                    {field: 'is_featured', title: __('Is_featured'), searchList: {0: __('Is_featured 0'), 1: __('Is_featured 1')}, formatter: Table.api.formatter.status},
                    {field: 'metrics', title: __('Metrics'), operate: false, formatter: metrics},
                    {field: 'featured_at', title: __('Featured_at'), operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime, visible: false},
                    {field: 'status', title: __('Status'), searchList: {normal: __('Status normal'), hidden: __('Status hidden')}, formatter: Table.api.formatter.status},
                    {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                ]]
            });
            Table.api.bindevent(table);
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
