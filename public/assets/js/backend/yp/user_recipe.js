define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {
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
                showColumns: true,
                columns: [[
                    {checkbox: true},
                    {field: 'id', title: __('Id'), operate: false},
                    {field: 'user.nickname', title: __('User.nickname'), operate: 'LIKE'},
                    {field: 'name', title: __('Name'), operate: 'LIKE'},
                    {field: 'bean_summary', title: __('Bean_summary'), operate: false, cellStyle: function () { return {css: {'min-width': '260px', 'white-space': 'normal'}}; }},
                    {field: 'baking', title: __('Baking'), operate: 'LIKE'},
                    {field: 'scene_tags', title: __('Scene_tags'), operate: 'LIKE'},
                    {field: 'flavor_tags', title: __('Flavor_tags'), operate: 'LIKE'},
                    {field: 'author_title', title: __('Author_title'), operate: 'LIKE'},
                    {field: 'public_status', title: __('Public_status'), searchList: {private: __('Public_status private'), public: __('Public_status public')}, formatter: Table.api.formatter.normal},
                    {field: 'is_featured', title: __('Is_featured'), searchList: {0: __('Is_featured 0'), 1: __('Is_featured 1')}, formatter: Table.api.formatter.status},
                    {field: 'copy_count', title: __('Copy_count'), operate: false},
                    {field: 'favorite_count', title: __('Favorite_count'), operate: false},
                    {field: 'feedback_count', title: __('Feedback_count'), operate: false},
                    {field: 'order_count', title: __('Order_count'), operate: false},
                    {field: 'featured_at', title: __('Featured_at'), operate: 'RANGE', addclass: 'datetimerange', formatter: Table.api.formatter.datetime},
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
