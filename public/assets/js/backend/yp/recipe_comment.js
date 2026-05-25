define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
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
                showColumns: true,
                columns: [[
                    {checkbox: true},
                    {field: 'id', title: __('Id'), operate: false},
                    {field: 'source_type', title: __('Source_type'), searchList: {official: __('Source_type official'), user: __('Source_type user')}, formatter: Table.api.formatter.normal},
                    {field: 'source_id', title: __('Source_id')},
                    {field: 'user.nickname', title: __('User.nickname'), operate: 'LIKE'},
                    {field: 'user.mobile', title: __('User.mobile'), operate: 'LIKE'},
                    {field: 'content', title: __('Content'), operate: 'LIKE', cellStyle: function () { return {css: {'min-width': '260px', 'white-space': 'normal'}}; }},
                    {field: 'status', title: __('Status'), searchList: {normal: __('Status normal'), hidden: __('Status hidden')}, formatter: Table.api.formatter.status},
                    {field: 'createtime', title: __('Createtime'), operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime},
                    {field: 'updatetime', title: __('Updatetime'), operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime}
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
