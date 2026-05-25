define(['jquery', 'bootstrap', 'backend', 'table', 'form','template'], function ($, undefined, Backend, Table, Form,Template) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'yp/order/index' + location.search,
                    add_url: 'yp/order/add',
                    edit_url: 'yp/order/edit',
                    del_url: 'yp/order/del',
                    multi_url: 'yp/order/multi',
                    import_url: 'yp/order/import',
                    table: 'yp_order',
                }
            });

            var table = $("#table");
            Template.helper("cdnurl", function(image) {
                return Fast.api.cdnurl(image);
            });
            Template.helper("Encode", function (value) {
                return encodeURIComponent(value || '');
            });
            Template.helper("Moment", Moment);
            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                pageSize: 20,
                pageList: [20, 50, 100],
                templateView: true,
                showHeader: false,
                fixedColumns: false,
                search:false,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        // {field: 'user.nickname', title: __('User.nickname'), operate: 'LIKE'},
                        {field: 'order_no', title: __('Order_no'), operate: 'LIKE'},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1'),"2":__('Status 2'),"3":__('Status 3'),"4":__('Status 4'),"5":__('Status 5'),"6":__('Status 6')}, formatter: Table.api.formatter.status},
                        {field: 'payment', title: __('Payment'), searchList: {"balance":__('Payment balance'),"wechat":__('Payment wechat')}, formatter: Table.api.formatter.status},
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'phone', title: __('Phone'), operate: 'LIKE'},
                        // {field: 'express_name', title: __('Express_name'), operate: 'LIKE'},
                        // {field: 'express_no', title: __('Express_no'), operate: 'LIKE'},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'paytime', title: __('Paytime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'canceltime', title: __('Canceltime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'delivertime', title: __('Delivertime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'confirmtime', title: __('Confirmtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
            $(".ops-filter-tabs[data-field] a[data-toggle='tab']").off("click.opsfilter").on("click.opsfilter", function (e) {
                e.preventDefault();
                e.stopImmediatePropagation();
                var link = $(this);
                var group = link.closest("[data-field]");
                var field = group.data("field");
                var value = link.data("value");
                var object = $("[name='" + field + "']", table.closest(".bootstrap-table").find(".commonsearch-table"));
                group.find("li").removeClass("active");
                link.closest("li").addClass("active");
                $(".panel-heading [data-field='" + field + "'] li", table.closest(".panel-intro")).removeClass("active");
                $(".panel-heading [data-field='" + field + "'] a[data-value='" + value + "']", table.closest(".panel-intro")).closest("li").addClass("active");
                if (object.prop("tagName") === "SELECT") {
                    $("option[value='" + value + "']", object).prop("selected", true);
                } else {
                    object.val(value);
                }
                table.trigger("uncheckbox");
                table.bootstrapTable("refresh", {pageNumber: 1});
                return false;
            });
            //点击详情
            $(document).on("click", ".detail[data-id]", function () {
                Backend.api.open('yp/order/detail/id/' + $(this).data('id'), __('查看详情'),{area:['1200px', '780px']});
            });
            // 待支付订单改价
            $(document).on("click", ".btn-change-price[data-id]", function () {
                Backend.api.open('yp/order/changeprice/ids/' + $(this).data('id'), '修改订单金额', {area:['720px', '500px']});
            });
            // 发货 & 批量发货
            $(document).on("click", ".btn-delivery", function () {
                if($(this).data('id')){
                    Backend.api.open('yp/order/delivery/ids/' + $(this).data('id'), __('发货'),{area:['1000px', '700px']});
                }else{
                    Backend.api.open('yp/order/delivery/ids/' + Table.api.selectedids(table), __('批量发货'),{area:['1000px', '700px']});
                }
            });
            $(document).on("click", ".btn-copy-recipient", function () {
                var productionText = decodeURIComponent($(this).data("production") || '');
                var text = productionText;
                if (!text) {
                    var parts = [
                        decodeURIComponent($(this).data("name") || ''),
                        decodeURIComponent($(this).data("phone") || ''),
                        decodeURIComponent($(this).data("address") || '')
                    ];
                    text = parts.filter(function (item) {
                        return item !== '';
                    }).join('，');
                }
                var fallbackCopy = function () {
                    var input = $('<textarea readonly></textarea>').val(text).appendTo('body');
                    input[0].select();
                    document.execCommand('copy');
                    input.remove();
                    Toastr.success('收货信息已复制');
                };
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text).then(function () {
                        Toastr.success('收货信息已复制');
                    }, fallbackCopy);
                } else {
                    fallbackCopy();
                }
            });
            $('.btn-export_log').click(function (){
                var options = table.bootstrapTable('getOptions');
                var search = options.queryParams({});
                var filter = search.filter;
                var op = search.op;
                location.href = Config.SCRIPT_NAME + '/yp/order/export_log' + '?f=' + filter + '&o=' + op;
            });
            $(document).on("click", ".kuaidisub[data-id]", function () {
                Backend.api.open('yp/order/relative/id/' + $(this).data('id'), __('快递查询'),{area:['800px', '600px']});
            });
        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        delivery: function () {
            Controller.api.bindevent();
        },
        changeprice: function () {
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
