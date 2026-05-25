define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/user/index',
                    add_url: 'user/user/add',
                    edit_url: 'user/user/edit',
                    del_url: 'user/user/del',
                    multi_url: 'user/user/multi',
                    table: 'user',
                }
            });

            var table = $("#table");
            var escapeHtml = function (value) {
                return $('<div/>').text(value == null ? '' : value).html();
            };
            var userFormatter = function (value, row) {
                var avatar = row.avatar || '';
                var nickname = row.nickname || '-';
                var mobile = row.mobile || '未绑定手机';
                return '<div class="ops-user-cell">' +
                    '<img src="' + escapeHtml(avatar) + '" alt="' + escapeHtml(nickname) + '">' +
                    '<div><div class="ops-cell-title">' + escapeHtml(nickname) + '</div><div class="ops-cell-meta">ID ' + escapeHtml(row.id) + ' · ' + escapeHtml(mobile) + '</div></div>' +
                    '</div>';
            };
            var rateFormatter = function (value) {
                var rate = parseFloat(value || 0).toFixed(2);
                return '<span class="ops-pill primary">' + rate + '%</span>';
            };
            var recommenderFormatter = function (value, row) {
                if (!row.recommender_id) {
                    return '<span class="text-muted">自然注册</span>';
                }
                var nickname = row.recommender_nickname || row.recommender_text || '推荐人已删除';
                var mobile = row.recommender_mobile || '';
                var meta = mobile ? 'ID ' + row.recommender_id + ' · ' + mobile : 'ID ' + row.recommender_id;
                return '<div class="ops-referrer-cell">' +
                    '<div class="ops-cell-title">' + escapeHtml(nickname) + '</div>' +
                    '<div class="ops-cell-meta">' + escapeHtml(meta) + '</div>' +
                    '</div>';
            };

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'user.id',
                showExport:false,
                showColumns:false,
                search:false,
                showToggle:false,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id'), sortable: true},
                        // {field: 'group.name', title: __('Group')},
                        // {field: 'username', title: __('Username'), operate: 'LIKE'},
                        {field: 'nickname', title: __('会员'), operate: 'LIKE', formatter: userFormatter},
                        // {field: 'email', title: __('Email'), operate: 'LIKE'},
                        {field: 'mobile', title: __('Mobile'), operate: 'LIKE', visible: false},
                        {field: 'age', title: __('年龄'),formatter:function(res){
                            if(res == 0){
                                return '未知';
                            }
                        }},
                        {field: 'avatar', title: __('Avatar'), events: Table.api.events.image, formatter: Table.api.formatter.image, operate: false},
                        {field: 'level.name', title: __('Level'),operate:false},
                        // {field: 'level', title: __('Level'), operate: 'BETWEEN', sortable: true},
                        {field: 'gender', title: __('Gender'), formatter: Table.api.formatter.status,searchList: {1: __('男'), 2: __('女'),0:'未知'}},
                        {field: 'integral', title: __('积分')},
                        {field: 'recommender_text', title: __('推荐人'), operate: false, formatter: recommenderFormatter},
                        {field: 'referral_count', title: __('推荐注册'), operate: false, sortable: false},
                        {field: 'distribution_rate', title: __('推荐比例'), operate: false, formatter: rateFormatter},
                        // {field: 'score', title: __('Score'), operate: 'BETWEEN', sortable: true},
                        // {field: 'successions', title: __('Successions'), visible: false, operate: 'BETWEEN', sortable: true},
                        // {field: 'maxsuccessions', title: __('Maxsuccessions'), visible: false, operate: 'BETWEEN', sortable: true},
                        {field: 'logintime', title: __('Logintime'), formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange', sortable: true},
                        // {field: 'loginip', title: __('Loginip'), formatter: Table.api.formatter.search},
                        {field: 'jointime', title: __('Jointime'), formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange', sortable: true},
                        // {field: 'joinip', title: __('Joinip'), formatter: Table.api.formatter.search},
                        {field: 'status', title: __('Status'), formatter: Table.api.formatter.status, searchList: {normal: __('Normal'), hidden: __('Hidden')}},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate,
                            buttons:[
                                {
                                    name: 'referrals',
                                    text: __('推荐用户'),
                                    title: __('推荐用户'),
                                    classname: 'btn btn-xs btn-info btn-dialog',
                                    icon: 'fa fa-sitemap',
                                    url: 'user/user/referrals'
                                },
                                {
                                    name: 'integral',
                                    text: __('变动积分'),
                                    classname: 'btn btn-xs btn-success btn-magic btn-click',
                                    click: function (e,row){
                                        var indexs = Layer.prompt({
                                            type: 1,
                                            title: "选择变动类型",
                                            closeBtn: 0,
                                            shadeClose: true,
                                            content: Config.select,
                                            yes:function (value,index) {
                                                var type_id = $('.type_id').val();
                                                Layer.prompt({
                                                    title: "请输入变动数量",
                                                    success: function (layero) {
                                                        $("input", layero).prop("placeholder", "请输入变动数量");
                                                    }
                                                }, function (value) {
                                                    Fast.api.ajax({
                                                        url: "user/user/change?ids=" + row.id,
                                                        data: {type: type_id,num:value,category:'integral'},
                                                    }, function (data, ret) {
                                                        Layer.closeAll();
                                                        $(".btn-refresh").trigger("click");
                                                    });
                                                });
                                            }
                                        });

                                        return false;
                                    }
                                }
                            ]
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
