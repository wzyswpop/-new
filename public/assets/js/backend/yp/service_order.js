define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'yp/service_order/index' + location.search,
                    multi_url: 'yp/service_order/multi',
                    import_url: 'yp/service_order/import',
                    table: 'yp_service_order',
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
                search:false,
                showToggle:false,
                showColumns:false,
                columns: [
                    [
                        // {checkbox: true},
                        // {field: 'id', title: __('Id')},
                        {field: 'user.nickname', title: __('User.nickname'), operate: 'LIKE'},
                        {field: 'order_no', title: __('Order_no')},
                        {field: 'type', title: __('Type'), searchList: {"1":__('Type 1'),"2":__('Type 2')}, formatter: Table.api.formatter.normal},
                        {field: 'money', title: __('Money'), operate:false},
                        {field: 'orders.discount_integral', title: __('抵扣积分'), operate:false},
                        {field: 'explain', title: __('Explain'), operate: 'LIKE'},
                        {field: 'images', title: __('Images'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.images},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1'),"2":__('Status 2')}, formatter: Table.api.formatter.status},
                        {field: 'return_goods', title: __('Return_goods'),searchList: {"0":__('Return_goods 0'),"1":__('Return_goods 1'),"2":__('Return_goods 2'),"3":__('Return_goods 3'),"4":__('Return_goods 4'),'':'无',"6":__('Return_goods 6'),"5":__('Return_goods 5')}, formatter: Table.api.formatter.status},
                        {field: 'return_money', title: __('Return_money'),searchList: {"0":__('Return_money 0'),"1":__('Return_money 1'),"2":__('Return_money 2'),"3":__('Return_money 3'),'':'无'}, formatter: Table.api.formatter.normal},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'handletime', title: __('Handletime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate,
                            buttons: [
                                {
                                    name: 'detail',
                                    text: __('查看详情'),
                                    title: __('查看详情'),
                                    classname: 'btn btn-xs btn-success btn-dialog',
                                    url: 'yp/service_order/detail',
                                    callback: function (data) {
                                    },
                                    visible: function (row) {
                                        return true;
                                    }
                                },
                                {
                                    name: 'return_confirm',
                                    text: __('确认收货'),
                                    title: __('确认收货'),
                                    classname: 'btn btn-xs btn-del btn-success btn-ajax',
                                    url: 'yp/service_order/return_confirm',
                                    confirm: '确认收货后将退款至用户余额',
                                    success: function (data, ret) {
                                        $("#table").bootstrapTable('refreshOptions',{});
                                    },
                                    error: function (data, ret) {
                                        Layer.alert(ret.msg);
                                        return false;
                                    },
                                    visible:function(row){
                                        if(row.type == 2 && row.return_goods == 3){
                                            return true;
                                        }else{
                                            return false
                                        }
                                    }
                                },
                                {
                                    name: 'return_confirm',
                                    text: __('拒绝收货'),
                                    title: __('拒绝收货'),
                                    classname: 'btn btn-xs btn-del btn-danger btn-ajax',
                                    url: 'yp/service_order/return_confirm?type=no',
                                    confirm: '是否拒绝?',
                                    success: function (data, ret) {
                                        $("#table").bootstrapTable('refreshOptions',{});
                                    },
                                    error: function (data, ret) {
                                        Layer.alert(ret.msg);
                                        return false;
                                    },
                                    visible:function(row){
                                        if(row.type == 2 && row.return_goods == 3){
                                            return true;
                                        }else{
                                            return false
                                        }
                                    }
                                },
                                {
                                    name: '同意',
                                    text: __('同意'),
                                    title: __('同意'),
                                    classname: 'btn btn-xs btn-del btn-success btn-ajax',
                                    url: 'yp/service_order/examine?type=yes',
                                    confirm: '是否同意?',
                                    success: function (data, ret) {
                                        $("#table").bootstrapTable('refreshOptions',{});
                                    },
                                    error: function (data, ret) {
                                        Layer.alert(ret.msg);
                                        return false;
                                    },
                                    visible:function(row){
                                        if(row.status == 0 && row.return_money == 1 && row.type == 1){
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
                                                url: 'yp/service_order/examine?type=no&ids='+row.id,
                                                data: {reason: value},
                                            }, function (data, ret) {
                                                Layer.closeAll();
                                                $(".btn-refresh").trigger("click");
                                            });
                                        });
                                        return false;
                                    },
                                    visible:function (row) {
                                        if(row.status == 0 && row.return_money == 1 && row.type == 1){
                                            return true;
                                        }else{
                                            return false
                                        }
                                    }
                                },
                                {
                                    name: '同意',
                                    text: __('同意'),
                                    title: __('同意'),
                                    classname: 'btn btn-xs btn-del btn-success btn-ajax',
                                    url: 'yp/service_order/goods_examine?type=yes',
                                    confirm: '是否同意?',
                                    success: function (data, ret) {
                                        $("#table").bootstrapTable('refreshOptions',{});
                                    },
                                    error: function (data, ret) {
                                        Layer.alert(ret.msg);
                                        return false;
                                    },
                                    visible:function(row){
                                        if(row.return_goods == 1 && row.status == 0 && row.type == 2){
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
                                                url: 'yp/service_order/goods_examine?type=no&ids='+row.id,
                                                data: {reason: value},
                                            }, function (data, ret) {
                                                Layer.closeAll();
                                                $(".btn-refresh").trigger("click");
                                            });
                                        });
                                        return false;
                                    },
                                    visible:function (row) {
                                        if(row.return_goods == 1 && row.status == 0 && row.type == 2){
                                            return true;
                                        }else{
                                            return false
                                        }
                                    }
                                },
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
