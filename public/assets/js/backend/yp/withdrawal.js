define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    function showTransferError(xhr) {
        var msg = '操作失败';
        if (xhr) {
            var response = xhr.responseJSON || {};
            msg = response.msg || xhr.responseText || xhr.statusText || msg;
            if (xhr.status) {
                msg = 'HTTP ' + xhr.status + '：' + msg;
            }
        }
        Layer.alert(msg);
    }

    function transferAjax(url, data, success) {
        var index = Layer.load(0);
        $.ajax({
            url: Fast.api.fixurl(url),
            type: 'POST',
            dataType: 'json',
            data: data,
            success: function (ret) {
                Layer.close(index);
                if (ret && ret.code == 1) {
                    success && success(ret);
                    Toastr.success(ret.msg || '操作成功');
                    return;
                }
                Layer.alert((ret && ret.msg) || '操作失败，请查看后台日志');
            },
            error: function (xhr) {
                Layer.close(index);
                showTransferError(xhr);
            }
        });
    }

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'yp/withdrawal/index' + location.search,
                    multi_url: 'yp/withdrawal/multi',
                    import_url: 'yp/withdrawal/import',
                    table: 'yp_withdrawal',
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
                showColumns:false,
                search:false,
                showToggle:false,
                columns: [
                    [
                        // {checkbox: true},
                        {field: 'user.nickname', title: __('User.nickname'), operate: 'LIKE'},
                        // {field: 'bank_name', title: __('Bank_name'), operate: 'LIKE'},
                        // {field: 'card_id', title: __('Card_id'), operate: 'LIKE'},
                        // {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'money', title: __('Money'), operate:false},
                        {field: 'service_charge', title: __('Service_charge'), operate:false},
                        {field: 'amount_received', title: __('Amount_received'), operate:false},
                        {field: 'type', title: __('提现类型'), searchList: {"1":__('佣金'),"2":__('余额')}, formatter: Table.api.formatter.status},
                        {field: 'status', title: __('Status'), searchList: {"1":__('Status 1'),"2":__('Status 2'),"3":__('Status 3')}, formatter: Table.api.formatter.status},
                        {field: 'transfer_state', title: __('微信状态'), operate:false},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate,
                            buttons: [
                                {
                                    name: 'detail',
                                    text: __('查看详情'),
                                    title: __('查看详情'),
                                    classname: 'btn btn-xs btn-success btn-dialog',
                                    url: 'yp/withdrawal/detail',
                                    callback: function (data) {
                                    },
                                    visible: function (row) {
                                        return true;
                                    }
                                },
                                {
                                    name: '查询转账',
                                    text: __('查转账'),
                                    classname: 'btn btn-xs btn-info btn-click',
                                    click: function (e, row) {
                                        transferAjax('yp/withdrawal/query_transfer', {ids: row.id}, function () {
                                            $(".btn-refresh").trigger("click");
                                        });
                                        return false;
                                    },
                                    visible:function(row){
                                        return row.status == 2 && row.out_detail_no;
                                    }
                                },
                                {
                                    name: '重新发起转账',
                                    text: __('重发转账'),
                                    classname: 'btn btn-xs btn-warning btn-click',
                                    click: function (e, row) {
                                        Layer.confirm('仅在微信商户平台查不到该转账单时使用，确认重新发起微信转账？', function (index) {
                                            transferAjax('yp/withdrawal/retry_transfer', {ids: row.id}, function () {
                                                Layer.close(index);
                                                $(".btn-refresh").trigger("click");
                                            });
                                        });
                                        return false;
                                    },
                                    visible:function(row){
                                        return row.status == 2;
                                    }
                                },
                                {
                                    name: '同意',
                                    text: __('同意'),
                                    title: __('同意'),
                                    classname: 'btn btn-xs btn-del btn-success btn-ajax',
                                    url: 'yp/withdrawal/examine?type=yes',
                                    confirm: '是否同意?',
                                    success: function (data, ret) {
                                        $("#table").bootstrapTable('refreshOptions',{});
                                    },
                                    error: function (data, ret) {
                                        Layer.alert(ret.msg);
                                        return false;
                                    },
                                    visible:function(row){
                                        if(row.status == 1){
                                            return true;
                                        }else{
                                            return false
                                        }
                                    }
                                },
                                {
                                    name: '拒绝',
                                    text: __('拒绝'),
                                    classname: 'btn btn-xs btn-danger btn-magic btn-click',
                                    click: function (e, row) {
                                        Layer.prompt({
                                            title: "拒绝原因",
                                            success: function (layero) {
                                                $("input", layero).prop("placeholder", "填写拒绝原因");
                                            }
                                        }, function (value) {
                                            Fast.api.ajax({
                                                url: 'yp/withdrawal/examine?type=no&ids='+row.id,
                                                data: {reason: value},
                                            }, function (data, ret) {
                                                Layer.closeAll();
                                                $(".btn-refresh").trigger("click");
                                            });
                                        });
                                        return false;
                                    },
                                    visible:function (row) {
                                        if(row.status == 1){
                                            return true;
                                        }else{
                                            return false
                                        }
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
