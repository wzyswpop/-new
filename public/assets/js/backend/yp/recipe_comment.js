define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
    var compact = function (value, length) {
        value = value == null ? '' : value.toString();
        return value.length > length ? value.substring(0, length) + '...' : value;
    };
    var source = function (value, row) {
        var text = row.source_type === 'official' ? __('Source_type official') : __('Source_type user');
        return '<span class="hc-wall-source">' + text + ' #' + row.source_id + '</span>';
    };
    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'yp/recipe_comment/index' + location.search,
                    multi_url: 'yp/recipe_comment/multi',
                    table: 'yp_recipe_comment'
                }
            });

            var table = $("#table");
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'createtime',
                sortOrder: 'desc',
                showExport: false,
                search: true,
                showToggle: false,
                showColumns: false,
                columns: [[
                    {checkbox: true},
                    {field: 'id', title: __('Id'), operate: false, width: 60},
                    {field: 'source_type', title: __('Source_type'), searchList: {official: __('Source_type official'), user: __('Source_type user')}, formatter: source},
                    {field: 'source_id', title: __('Source_id'), visible: false},
                    {field: 'user.nickname', title: __('User.nickname'), operate: 'LIKE', width: 120},
                    {field: 'user.mobile', title: __('User.mobile'), operate: 'LIKE', visible: false},
                    {field: 'content', title: __('Content'), operate: 'LIKE', formatter: function (value) { return compact(value, 120); }, cellStyle: function () { return {css: {'min-width': '360px', 'white-space': 'normal'}}; }},
                    {field: 'status', title: __('Status'), searchList: {normal: __('Status normal'), hidden: __('Status hidden')}, formatter: Table.api.formatter.status},
                    {field: 'createtime', title: __('Createtime'), operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime},
                    {field: 'updatetime', title: __('Updatetime'), operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime, visible: false}
                ]]
            });
            Table.api.bindevent(table);
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});
