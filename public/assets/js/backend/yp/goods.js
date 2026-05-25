define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            $(".btn-add").data("area", ["80%", "90%"]);
            $(".btn-edit").data("area", ["80%", "90%"]);
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'yp/goods/index' + location.search,
                    add_url: 'yp/goods/add',
                    edit_url: 'yp/goods/edit',
                    del_url: 'yp/goods/del',
                    multi_url: 'yp/goods/multi',
                    change_status_url: 'yp/goods/changeStatus',
                    import_url: 'yp/goods/import',
                    table: 'yp_goods',
                }
            });

            var table = $("#table");
            var escapeHtml = function (value) {
                return String(value || '').replace(/[&<>"']/g, function (match) {
                    return {
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#39;'
                    }[match];
                });
            };
            var stockFormatter = function (value, row) {
                var total = row.total_stock || 0;
                var min = row.min_stock || 0;
                var sku = row.sku_count || 0;
                var label = row.stock_warning ? 'danger' : 'primary';
                return '<span class="ops-pill ' + label + '">' + total + '</span>' +
                    '<div class="ops-cell-meta">规格 ' + sku + ' / 最低 ' + min + '</div>';
            };
            var imageFormatter = function (value, row) {
                var image = value ? Fast.api.cdnurl(value) : '';
                return image ? '<div class="ops-goods-thumb"><img src="' + image + '" alt=""></div>' : '<span class="ops-image-empty"><i class="fa fa-coffee"></i></span>';
            };
            var goodsNameFormatter = function (value, row) {
                var warning = row.stock_warning ? '<span class="ops-pill danger ops-inline-warning">库存预警</span>' : '';
                return '<div class="ops-cell-title">' + escapeHtml(value) + warning + '</div>' +
                    '<div class="ops-cell-meta">编码 ' + escapeHtml(row.erp_goods_no || row.sn || ('ID ' + row.id)) + '</div>';
            };
            var originFormatter = function (value, row) {
                var country = row.category && row.category.name ? row.category.name : '';
                var region = row.product_area || '';
                return '<div class="ops-cell-title">' + escapeHtml(country || '-') + '</div>' +
                    '<div class="ops-cell-meta">' + escapeHtml(region || '未填写产区') + '</div>';
            };
            var switchFormatter = function (value) {
                return Number(value) === 1 ? '<span class="ops-pill success">是</span>' : '<span class="ops-pill muted">否</span>';
            };
            var channelFormatter = function (value) {
                var style = value === '双渠道' ? 'primary' : (value === '商城' ? 'success' : (value === '定制' ? 'warning' : 'muted'));
                return '<span class="ops-pill ' + style + '">' + escapeHtml(value || '归档') + '</span>';
            };
            var channelStateFormatter = function (value, row) {
                var lines = [];
                if (Number(row.is_shop_sale) === 1) {
                    lines.push('商城：' + (row.shop_status_text || '-'));
                }
                if (Number(row.is_customized) === 1) {
                    lines.push('定制：' + (row.custom_status_text || '-'));
                }
                if (!lines.length) {
                    lines.push('未进入任何销售渠道');
                }
                return '<div>' + channelFormatter(row.channel_text) + '</div>' +
                    '<div class="ops-cell-meta">' + escapeHtml(lines.join(' / ')) + '</div>';
            };
            var priceFormatter = function (value, row) {
                var shopLine = Number(row.is_shop_sale) === 1
                    ? '商城售卖 ¥' + Number(row.money || 0).toFixed(2) + (Number(row.sku_count || 0) > 1 ? ' 起' : '')
                    : '未进商城';
                var customLine = Number(row.is_customized) === 1
                    ? '定制豆池 ¥' + Number(row.customized_price || 0).toFixed(2) + '/kg'
                    : '未进定制';
                if (Number(row.is_customized) === 1 && Number(row.is_shop_sale) !== 1) {
                    return '<div class="ops-money">¥' + Number(row.customized_price || 0).toFixed(2) + '/kg</div>' +
                        '<div class="ops-cell-meta">定制豆池价格</div>';
                }
                if (Number(row.is_shop_sale) === 1 && Number(row.is_customized) !== 1) {
                    return '<div class="ops-money">¥' + Number(row.money || 0).toFixed(2) + (Number(row.sku_count || 0) > 1 ? ' 起' : '') + '</div>' +
                        '<div class="ops-cell-meta">商城售卖价格</div>';
                }
                return '<div class="ops-money">' + escapeHtml(shopLine) + '</div>' +
                    '<div class="ops-cell-meta">' + escapeHtml(customLine) + '</div>';
            };

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'weigh',
                pageSize: 20,
                pageList: [20, 50, 100],
                fixedColumns: true,
                fixedRightNumber: 1,
                showExport:false,
                showToggle:false,
                search:false,
                showColumns:false,
                columns: [
                    [
                        {checkbox: true},
                        // {field: 'id', title: __('Id')},
                        {field: 'image', title: '咖啡豆', operate: false, formatter: imageFormatter},
                        {field: 'name', title: __('Name'), operate: 'LIKE', formatter: goodsNameFormatter},
                        {field: 'erp_goods_no', title: '商品编码', operate: 'LIKE'},
                        {field: 'category.name', title: '产国 / 产区', operate: 'LIKE', formatter: originFormatter},
                        {field: 'processing_method', title: '处理法', operate: 'LIKE'},
                        {field: 'channel_text', title: '业务渠道', operate: false, formatter: channelStateFormatter},
                        {field: 'money', title: '价格', operate:false, formatter: priceFormatter},
                        {field: 'total_stock', title: __('Stock'), operate: false, formatter: stockFormatter},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });
            table.on('post-body.bs.table', function (e, settings, json, xhr) {
                $(".btn-editone").data("area", ['80%','90%']);// 编辑弹窗
            });
            // 为表格绑定事件
            Table.api.bindevent(table);
            $(document).off("click.goodscreate", ".btn-create-goods").on("click.goodscreate", ".btn-create-goods", function () {
                Backend.api.open($.fn.bootstrapTable.defaults.extend.add_url, "新增咖啡豆", {area: ["80%", "90%"]});
            });
            $(".ops-filter-tabs[data-field] a[data-toggle='tab']").off("click.opsfilter").on("click.opsfilter", function (e) {
                e.preventDefault();
                e.stopImmediatePropagation();
                var link = $(this);
                var group = link.closest("[data-field]");
                var field = group.data("field");
                var value = link.data("value");
                group.find("li").removeClass("active");
                link.closest("li").addClass("active");
                $(".panel-heading [data-field='" + field + "'] li", table.closest(".panel-intro")).removeClass("active");
                $(".panel-heading [data-field='" + field + "'] a[data-value='" + value + "']", table.closest(".panel-intro")).closest("li").addClass("active");
                if (field === "scope") {
                    if (value === "taxonomy") {
                        Fast.api.open('yp/goods_category', '标签与分类', {area: ['90%', '90%']});
                        return false;
                    }
                    var scopeUrl = $.fn.bootstrapTable.defaults.extend.index_url.replace(/\?.*$/, '') + (value && value !== 'all' ? '?scope=' + encodeURIComponent(value) : '');
                    table.bootstrapTable("refresh", {url: scopeUrl, pageNumber: 1});
                    return false;
                }
                var object = $("[name='" + field + "']", table.closest(".bootstrap-table").find(".commonsearch-table"));
                if (object.prop("tagName") === "SELECT") {
                    $("option[value='" + value + "']", object).prop("selected", true);
                } else {
                    object.val(value);
                }
                table.trigger("uncheckbox");
                table.bootstrapTable("refresh", {pageNumber: 1});
                return false;
            });
            $(document).on("click", ".btn-change-status", function () {
                var ids = Table.api.selectedids(table);
                var status = $(this).data("status");
                if (!ids.length) {
                    Toastr.warning("请选择要操作的商品");
                    return;
                }
                Fast.api.ajax({
                    url: $.fn.bootstrapTable.defaults.extend.change_status_url,
                    type: "post",
                    data: {
                        ids: ids.join(","),
                        status: status
                    }
                }, function () {
                    table.bootstrapTable('refresh', {});
                });
            });
        },
        select: function () {
            Table.api.init({
                extend: {
                    index_url: 'yp/goods/index' + location.search,
                    table: 'goods',
                }
            });
            var idArr = [];
            var dataArr = [];
            var table = $("#table");

            table.on('check.bs.table uncheck.bs.table check-all.bs.table uncheck-all.bs.table', function (e, row) {
                if (e.type == 'check' || e.type == 'uncheck') {
                    row = [row];
                } else {
                    idArr = [];
                    dataArr = [];
                }
                $.each(row, function (i, j) {
                    if (e.type.indexOf("uncheck") > -1) {
                        var index = idArr.indexOf(j.id);
                        if (index > -1) {
                            idArr.splice(index, 1);
                            $.each(dataArr, function(key,value){
                                if(value != undefined && value.id == j.id){
                                    dataArr.splice(key, 1);
                                }
                            })
                        }
                    } else {
                        if(idArr.indexOf(j.id) == -1){
                            idArr.push(j.id);
                            dataArr.push({
                                id: j.id,
                                image: j.image,
                                money: j.money,
                                name: j.name
                            });
                        }
                    }
                });
            });

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                fixedColumns: true,
                fixedRightNumber: 1,
                search: false,
                showToggle: false,
                showColumns: false,
                showExport: false,
                columns: [
                    [
                        {checkbox: true},
                        // {field: 'id', title: __('Id')},
                        {field: 'category.name', title: __('Category.name'), operate: 'LIKE'},
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'money', title: __('Money'), operate:false},
                        {field: 'image', title: __('Image'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'sales', title: __('Sales')},
                        {field: 'ag', title: __('AG')},
                        {field: 'see', title: __('See')},
                        {field: 'is_hot', title: __('Is_hot'), searchList: {"1":__('Is_hot 1'),"2":__('Is_hot 2')}, formatter: Table.api.formatter.normal},
                        {field: 'is_stock', title: __('Is_stock'),searchList: {"0":__('Is_stock 0'),"1":__('Is_stock 1')}, formatter: Table.api.formatter.normal},
                        {field: 'status', title: __('Status'), searchList: {"1":__('Status 1'),"2":__('Status 2')}, formatter: Table.api.formatter.status},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime}
                    ]
                ]
            });
            $(document).on("click", ".btn-choose-multi", function () {
                var multiple = Backend.api.query('multiple');
                multiple = multiple == 'true' ? true : false;
                Fast.api.close({
                    url: idArr.length == 0 ? '': idArr.join(","),
                    data: dataArr,
                    multiple: multiple
                });
            });
            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Controller.initAddEdit(null, null, [], []);
        },
        edit: function () {
            Controller.initAddEdit(Config.id, 'edit', Config.skuList, Config.skuPrice);
        },
        initAddEdit: function (id, type, skuList, skuPrice) {
            //vue Sku添加页 添加规格和价格数据
            var goodsDetail = new Vue({
                el: "#goodsDetail",
                data() {
                    return {
                        supplier: [],
                        editId: id,
                        type: type,
                        stepActive: 1,
                        activeEditTab: 'base',
                        goodsDetail: {},
                        goodsDetailInit: {
                            freight_id:'',
                            name: '',
                            desc: '',
                            shop_name: '',
                            custom_name: '',
                            is_shop_sale:1,
                            is_hot:1,
                            classify:1,
                            is_customized:0,
                            custom_status:1,
                            customized_price:0,
                            blend_role:'',
                            formula_primary_position:'base',
                            formula_secondary_positions:[],
                            formula_strength:'medium',
                            formula_strong_process:0,
                            formula_role_scores:{},
                            formula_role_reason:'',
                            formula_recommended_ratio:'',
                            formula_avoid_roles:[],
                            formula_confirmed:0,
                            taste_acidity:0,
                            taste_sweetness:0,
                            taste_aroma:0,
                            taste_body:0,
                            taste_aftertaste:0,
                            recommend_ratio:0,
                            allow_ai_recommend:1,
                            allow_manual_select:1,
                            custom_pricing_method:'weight',
                            tags_arr: [],
                            product_area:'',
                            bean_seed:'',
                            special_flavour:'',
                            processing_method:'',
                            moisture_content:'',
                            density:'',
                            specs:'',
                            baking:'',
                            status: 1,
                            sales:0,
                            see:0,
                            ag:0,
                            image: '',
                            images: '',
                            images_arr: [],
                            category_id: '',
                            category_ids_arr: [],
                            is_stock: 0,
                            money: '',
                            line_money: '',
                            content: '',
                            weight: 0,
                            stock: '',
                            sn: '',
                            erp_goods_no:'',
                            weigh:0
                        },
                        timeData: {
                            images_arr: [],
                            category_ids_arr: [],
                        },
                        rules: {
                            name: [{
                                required: true,
                                message: '请输入商品标题',
                                trigger: 'blur'
                            }],
                            image: [{
                                required: true,
                                message: '请上传商品主图',
                                trigger: 'change'
                            }],
                            images: [{
                                validator: function (rule, value, callback) {
                                    var detail = goodsDetail.goodsDetail || {};
                                    var isCustomOnly = Number(detail.is_customized) === 1 && Number(detail.is_shop_sale) !== 1;
                                    if (isCustomOnly) {
                                        callback();
                                        return;
                                    }
                                    var images = Array.isArray(detail.images_arr) ? detail.images_arr : [];
                                    if (images.length > 0 || value) {
                                        callback();
                                        return;
                                    }
                                    callback(new Error('请上传商品轮播图'));
                                },
                                trigger: 'change'
                            }],
                            category_id: [{
                                required: true,
                                message: '请选择产区分类',
                                trigger: 'change'
                            }],
                            is_stock: [{
                                required: true,
                                message: '请选择商品规格',
                                trigger: 'blur'
                            }],
                            money: [{
                                required: true,
                                message: '请输入价格',
                                trigger: 'blur'
                            }],
                            customized_price: [{
                                validator: function (rule, value, callback) {
                                    var detail = goodsDetail.goodsDetail || {};
                                    if (Number(detail.is_customized) !== 1) {
                                        callback();
                                        return;
                                    }
                                    if (value === '' || value === null || value === undefined || Number(value) <= 0) {
                                        callback(new Error('开启定制可选后，请填写定制价格'));
                                        return;
                                    }
                                    callback();
                                },
                                trigger: 'blur'
                            }],
                            stock: [{
                                required: true,
                                message: '请输入库存',
                                trigger: 'blur'
                            }],
                        },
                        mustDeleteField: ['images_arr', 'category_ids_arr'],
                        categoryOptions: [],//选择分类
                        upload: Config.moduleurl,
                        editor: null,
                        //多规格
                        skuList: [],
                        // skuListLeng:0,
                        skuPrice: [],
                        skuListData: '',
                        skuPriceData: '',
                        skuModal: '',
                        childrenModal: [],
                        countId: 1,
                        allEditSkuName: '',
                        isEditInit: false, // 编辑时候初始化是否完成
                        isResetSku: 0,
                        allEditPopover: {
                            money: false,
                            stock: false,
                            weight: false,
                            sn: false,
                            erp_spec_no:false
                        },
                        allEditDatas: "",
                        visible: false,
                        visibless: '',
                        activeName: null,
                        activeIndex: null,
                        defaultProps: {
                            children: 'children',
                            label: 'name'
                        },
                        selectedcatArr: [],
                        freight_list: [],
                        inputVisible: false,
                        inputValue: '',
                        formulaPositionOptions: [
                            {label: '基底', value: 'base'},
                            {label: '甜感', value: 'sweet'},
                            {label: '香气', value: 'aroma'},
                            {label: '增味', value: 'accent'},
                            {label: '平衡', value: 'balance'}
                        ],
                        roleJudgeResult: null,
                    }
                },
                mounted() {
                    this.goodsDetail = JSON.parse(JSON.stringify(this.goodsDetailInit));
                    if (this.editId) {
                        this.getCategoryOptions(true);
                    } else {
                        this.getCategoryOptions();
                        this.getInit([], [])
                        this.$nextTick(() => {
                            Controller.api.bindevent();
                        });
                    }
                },
                methods: {
                    changeSkuSwitch(iindex, jindex) {
                        if (this.skuPrice[iindex].store_take_switch == 0) {
                            this.skuPrice[iindex].store_take = ''
                        } else {
                            this.skuPrice[iindex].store_take = JSON.parse(JSON.stringify({
                                take_type: 'money',
                                take_money: 0,
                                take_rate: 0
                            }))
                        }
                        this.$forceUpdate()
                    },
                    changeSkuSwitchTown(iindex, jindex) {
                        if (this.skuPrice[iindex].town_take_switch == 0) {
                            this.skuPrice[iindex].town_take = ''
                        } else {
                            this.skuPrice[iindex].town_take = JSON.parse(JSON.stringify({
                                take_type: 'money',
                                take_money: 0,
                                take_rate: 0
                            }))
                        }
                        this.$forceUpdate()
                    },
                    //附带标签
                    handleClose(tag) {
                        this.goodsDetail.tags_arr = this.goodsDetail.tags_arr || [];
                        this.goodsDetail.tags_arr.splice(this.goodsDetail.tags_arr.indexOf(tag), 1);
                    },
                    handleInputConfirm() {
                        let inputValue = this.inputValue;
                        if (inputValue) {
                            this.goodsDetail.tags_arr = this.goodsDetail.tags_arr || [];
                            this.goodsDetail.tags_arr.push(inputValue);
                        }
                        this.inputVisible = false;
                        this.inputValue = '';
                    },
                    showInput() {
                        this.inputVisible = true;
                        this.$nextTick(_ => {
                            this.$refs.saveTagInput.$refs.input.focus();
                        });
                    },
                    //主分类选择
                    tabSelect(tab) {
                        this.activeIndex = Number(tab.index)
                    },
                    // 选择商品分类
                    panelChange(val) {
                        let that = this;
                        if(val != undefined && val.length){
                            that.goodsDetail.category_id = val[val.length - 1];
                        }
                    },
                    closeTag(val) {
                        this.goodsDetail.category_ids_arr = this.goodsDetail.category_ids_arr || [];
                        this.goodsDetail.category_ids_arr.splice(val, 1);
                        this.selectedcatArr.splice(val, 1)
                        let idsArr = []
                        this.goodsDetail.category_ids_arr.forEach(j => {
                            idsArr.push(j[j.length - 1])
                        })
                        this.goodsDetail.category_id = idsArr.join(',');
                        this.panelChange(this.goodsDetail.category_ids_arr)
                        this.$forceUpdate()
                    },
                    // 新建服务标签
                    createTemplate() {
                        Fast.api.open("groupon/goods/service/add", "新建");
                    },
                    getInit(skuList, skuPrice) {
                        skuList = Array.isArray(skuList) ? skuList : [];
                        skuPrice = Array.isArray(skuPrice) ? skuPrice : [];
                        // 记录每个规格项真实 id，对应的临时 id
                        let tempIdArr = {};
                        for (let i in skuList) {
                            // 为每个 规格增加当前页面自增计数器，比较唯一用
                            skuList[i]['temp_id'] = this.countId++
                            skuList[i]['children'] = Array.isArray(skuList[i]['children']) ? skuList[i]['children'] : [];
                            for (let j in skuList[i]['children']) {
                                // 为每个 规格项增加当前页面自增计数器，比较唯一用
                                skuList[i]['children'][j]['temp_id'] = this.countId++

                                // 记录规格项真实 id 对应的 临时 id
                                tempIdArr[skuList[i]['children'][j]['id']] = skuList[i]['children'][j]['temp_id']
                            }
                        }
                        // for (let i in skuPrice) {
                        for (var i = 0; i < skuPrice.length; i++) {
                            let tempSkuPrice = skuPrice[i]
                            tempSkuPrice['temp_id'] = i + 1

                            // 将真实 id 数组，循环，找到对应的临时 id 组合成数组
                            tempSkuPrice['goods_sku_temp_ids'] = [];
                            let goods_sku_id_arr = String(tempSkuPrice['goods_sku_ids'] || '').split(',').filter(function (item) {
                                return item !== '';
                            });
                            for (let ids of goods_sku_id_arr) {
                                if (tempIdArr[ids] !== undefined) {
                                    tempSkuPrice['goods_sku_temp_ids'].push(tempIdArr[ids])
                                }
                            }

                            skuPrice[i] = tempSkuPrice
                        }
                        if (this.type == 'copy') {
                            for (let i in skuList) {
                                // 为每个 规格增加当前页面自增计数器，比较唯一用
                                skuList[i].id = 0;
                                for (let j in skuList[i]['children']) {
                                    skuList[i]['children'][j].id = 0;
                                }
                            }
                        }
                        if (skuPrice.length > 0) {
                            skuPrice.forEach(si => {
                                si.stock_warning_switch = false
                                if (si.stock_warning || si.stock_warning == 0) {
                                    si.stock_warning_switch = true
                                }
                            })
                        }
                        this.skuList = skuList;
                        this.skuPrice = skuPrice;
                        this.skuPrice.forEach(sku => {
                            if (sku.store_take) {
                                sku.store_take_switch = '1'
                                sku.store_take = JSON.parse(JSON.stringify(sku.store_take_arr))
                            } else {
                                sku.store_take_switch = '0'
                                sku.store_take = JSON.parse(JSON.stringify({
                                    take_type: 'money',
                                    take_money: 0,
                                    take_rate: 0
                                }))
                            }

                            if (sku.town_take) {
                                sku.town_take_switch = '1'
                                sku.town_take = JSON.parse(JSON.stringify(sku.town_take_arr))
                            } else {
                                sku.town_take_switch = '0'
                                sku.town_take = JSON.parse(JSON.stringify({
                                    take_type: 'money',
                                    take_money: 0,
                                    take_rate: 0
                                }))
                            }
                        })

                        setTimeout(() => {
                            // 延迟触发更新下面列表
                            this.isEditInit = true;
                        }, 200)
                    },
                    getEditData() {
                        let that = this;
                        Fast.api.ajax({
                            url: 'yp/goods/detail/ids/' + that.editId,
                            loading: true,
                        }, function (ret, res) {
                            let data = res && res.data ? res.data : {};
                            let detail = data.detail || {};
                            for (let key in that.goodsDetail) {
                                if (Object.prototype.hasOwnProperty.call(detail, key)) {
                                    that.goodsDetail[key] = detail[key]
                                }
                            }
                            that.parseFormulaPosition();
                            that.goodsDetail.category_ids_arr = Array.isArray(that.goodsDetail.category_ids_arr) && Array.isArray(that.goodsDetail.category_ids_arr[0]) ? that.goodsDetail.category_ids_arr[0] : [];
                            that.goodsDetail.images_arr = Array.isArray(that.goodsDetail.images_arr) ? that.goodsDetail.images_arr : [];
                            that.getInit(data.skuList, data.skuPrice);
                            Controller.api.bindevent();
                            $('#c-content').html(detail.content || '')
                            return false;
                        })
                    },
                    submitForm(formName) {
                        this.$refs[formName].validate((valid, invalidFields) => {
                            if (valid) {
                                let that = this;
                                let arrForm = JSON.parse(JSON.stringify(that.goodsDetail));
                                //处理轮播图
                                arrForm.images_arr = Array.isArray(arrForm.images_arr) ? arrForm.images_arr : [];
                                arrForm.images = arrForm.images_arr.join(',')
                                //规格
                                if (arrForm.is_stock == 0) {
                                    that.skuList = [];
                                    that.skuPrice = [];
                                }
                                // 图文详情
                                arrForm.content = $("#c-content").val();
                                arrForm.blend_role = that.stringifyFormulaPosition(arrForm);
                                delete arrForm.formula_primary_position;
                                delete arrForm.formula_secondary_positions;
                                delete arrForm.formula_strength;
                                delete arrForm.formula_strong_process;
                                delete arrForm.formula_role_scores;
                                delete arrForm.formula_role_reason;
                                delete arrForm.formula_recommended_ratio;
                                delete arrForm.formula_avoid_roles;
                                delete arrForm.formula_confirmed;
                                that.mustDeleteField.forEach(i => {
                                    delete arrForm[i]
                                })
                                if (that.editId && that.type == 'edit') {
                                    Fast.api.ajax({
                                        url: 'yp/goods/edit/ids/' + that.editId,
                                        loading: true,
                                        data: {
                                            row: arrForm,
                                            sku: {
                                                listData: JSON.stringify(that.skuList),
                                                priceData: JSON.stringify(that.skuPrice)
                                            }
                                        }
                                    }, function (ret, res) {
                                        Fast.api.close();
                                        parent.$(".btn-refresh").trigger("click");
                                    })
                                } else {
                                    if (this.type == 'copy') {
                                        delete arrForm.id
                                    }
                                    Fast.api.ajax({
                                        url: 'yp/goods/add',
                                        loading: true,
                                        data: {
                                            row: arrForm,
                                            sku: {
                                                listData: JSON.stringify(that.skuList),
                                                priceData: JSON.stringify(that.skuPrice)
                                            }
                                        }
                                    }, function (ret, res) {
                                        Fast.api.close();
                                        parent.$(".btn-refresh").trigger("click");
                                    })
                                }

                            } else {
                                this.focusFirstInvalidTab(invalidFields);
                                Toastr.warning('请先补充必填信息');
                                return false;
                            }
                        });
                    },
                    focusFirstInvalidTab(invalidFields) {
                        invalidFields = invalidFields || {};
                        let tabMap = {
                            category_id: 'base',
                            name: 'base',
                            image: 'base',
                            images: 'base',
                            is_stock: 'shop',
                            money: 'shop',
                            customized_price: 'custom',
                            stock: 'stock'
                        };
                        let fields = Object.keys(invalidFields);
                        for (let i = 0; i < fields.length; i++) {
                            if (tabMap[fields[i]]) {
                                this.activeEditTab = tabMap[fields[i]];
                                return;
                            }
                        }
                        this.activeEditTab = 'base';
                    },
                    parseFormulaPosition() {
                        var raw = this.goodsDetail.blend_role || '';
                        var config = {};
                        if (raw && typeof raw === 'string' && raw.charAt(0) === '{') {
                            try {
                                config = JSON.parse(raw) || {};
                            } catch (e) {
                                config = {};
                            }
	                        } else if (raw && typeof raw === 'string' && raw.indexOf('|') !== -1) {
	                            var parts = raw.split('|');
	                            config.primary = parts[0] || 'base';
	                            config.secondary = parts[1] ? parts[1].split(',').filter(function (item) { return item !== ''; }) : [];
	                            config.strength = parts[4] || 'medium';
	                            config.strong_process = Number(parts[5] || 0);
	                            config.confirmed = Number(parts[6] || 0);
	                        } else if (raw) {
	                            config.primary = raw === 'flavour' ? 'aroma' : (raw === 'accent' ? 'accent' : raw);
	                        }
                        this.goodsDetail.formula_primary_position = config.primary || 'base';
                        this.goodsDetail.formula_secondary_positions = Array.isArray(config.secondary) ? config.secondary : [];
                        this.goodsDetail.formula_strength = config.strength || 'medium';
	                        this.goodsDetail.formula_strong_process = this.isSpecialProcess(this.goodsDetail) ? 1 : 0;
                        this.goodsDetail.formula_role_scores = config.scores || {};
                        this.goodsDetail.formula_role_reason = config.reason || '';
                        this.goodsDetail.formula_recommended_ratio = config.recommended_ratio || '';
                        this.goodsDetail.formula_avoid_roles = Array.isArray(config.avoid_roles) ? config.avoid_roles : [];
                        this.goodsDetail.formula_confirmed = Number(config.confirmed || 0);
                        this.roleJudgeResult = {
                            primary: this.goodsDetail.formula_primary_position,
                            secondary: this.goodsDetail.formula_secondary_positions,
                            scores: this.goodsDetail.formula_role_scores,
                            reason: this.goodsDetail.formula_role_reason,
                            recommended_ratio: this.goodsDetail.formula_recommended_ratio,
                            avoid_roles: this.goodsDetail.formula_avoid_roles
                        };
                    },
	                    stringifyFormulaPosition(form) {
	                        var primary = form.formula_primary_position || 'base';
	                        var secondary = Array.isArray(form.formula_secondary_positions) ? form.formula_secondary_positions.filter(function (item) {
	                            return item && item !== primary;
	                        }) : [];
	                        return [
	                            primary,
	                            secondary.slice(0, 2).join(','),
	                            '',
	                            '',
	                            form.formula_strength || 'medium',
	                            this.isSpecialProcess(form) ? 1 : 0,
	                            Number(form.formula_confirmed || 0)
	                        ].join('|');
	                    },
                    runRoleJudge() {
                        var result = this.judgeBeanRole(this.goodsDetail);
                        this.roleJudgeResult = result;
                        this.goodsDetail.formula_role_scores = result.scores;
                        this.goodsDetail.formula_role_reason = result.reason;
                        this.goodsDetail.formula_recommended_ratio = result.recommended_ratio;
                        this.goodsDetail.formula_avoid_roles = result.avoid_roles;
                        this.goodsDetail.formula_strong_process = result.strong_process;
                        this.goodsDetail.formula_confirmed = 0;
                    },
	                    applyRoleJudge() {
	                        if (!this.roleJudgeResult) {
	                            this.runRoleJudge();
	                        }
	                        var result = this.roleJudgeResult;
                        this.goodsDetail.formula_primary_position = result.primary;
                        this.goodsDetail.formula_secondary_positions = result.secondary.slice(0, 2);
                        this.goodsDetail.formula_role_scores = result.scores;
                        this.goodsDetail.formula_role_reason = result.reason;
	                        this.goodsDetail.formula_recommended_ratio = result.recommended_ratio;
	                        this.goodsDetail.formula_avoid_roles = result.avoid_roles;
	                        this.goodsDetail.formula_strong_process = result.strong_process;
	                        this.goodsDetail.formula_confirmed = 1;
	                        Toastr.success('已采用系统判定');
	                    },
	                    confirmRoleJudge() {
	                        if (!this.goodsDetail.formula_role_scores || !Object.keys(this.goodsDetail.formula_role_scores).length) {
	                            this.runRoleJudge();
	                        }
	                        this.goodsDetail.formula_strong_process = this.isSpecialProcess(this.goodsDetail) ? 1 : 0;
	                        this.goodsDetail.formula_confirmed = 1;
	                        Toastr.success('已确认当前设置');
	                    },
                    judgeBeanRole(bean) {
                        var text = this.beanJudgeText(bean);
                        var acidity = Number(bean.taste_acidity || 0);
                        var sweetness = Number(bean.taste_sweetness || 0);
                        var body = Number(bean.taste_body || 0);
                        var aroma = this.beanAromaStrength(bean);
                        var fermentation = this.beanFermentationStrength(bean);
                        var cleanliness = this.beanCleanlinessStrength(bean);
                        var priceLevel = this.beanPriceLevel(bean);
                        var specialProcess = this.isSpecialProcess(bean);
                        var ordinaryProcess = !this.match(text, ['厌氧','热冲击','酒桶','水果浸渍','酵母','乳酸菌','共发酵','二次发酵','特殊发酵']);
                        var scores = {base:0, sweet:0, aroma:0, accent:0, balance:0};

                        if (this.match(text, ['巴西','洪都拉斯','危地马拉','哥伦比亚','云南'])) scores.base += 2;
                        if (this.match(text, ['坚果','可可','巧克力','焦糖','麦芽','奶油','红糖'])) scores.base += 2;
                        if (body >= 4) scores.base += 3;
                        if (sweetness >= 3) scores.base += 1;
                        if (acidity > 0 && acidity <= 3) scores.base += 1;
                        if (aroma > 0 && aroma <= 3) scores.base += 1;
                        if (fermentation <= 2) scores.base += 2;
                        if (priceLevel <= 3) scores.base += 1;
                        if (this.match(text, ['意式','奶咖','通用'])) scores.base += 2;
                        if (specialProcess) scores.base -= 2;
                        if (aroma >= 5) scores.base -= 1;
                        if (fermentation >= 4) scores.base -= 3;
                        if (priceLevel >= 4) scores.base -= 2;
                        if (this.match(text, ['玫瑰','茉莉','荔枝','热带水果','酒香'])) scores.base -= 1;

                        if (sweetness >= 4) scores.sweet += 4;
                        if (this.match(text, ['蜂蜜','焦糖','红糖','黄糖','枫糖','果脯','熟水果','奶油','甜橙','黄桃','甜瓜'])) scores.sweet += 3;
                        if (this.match(text, ['蜜处理','日晒','半日晒','厌氧蜜处理'])) scores.sweet += 2;
                        if (body >= 3) scores.sweet += 1;
                        if (acidity > 0 && acidity <= 4) scores.sweet += 1;
                        if (fermentation <= 3) scores.sweet += 1;
                        if (this.match(text, ['奶咖','意式','通用'])) scores.sweet += 1;
                        if (acidity >= 5) scores.sweet -= 2;
                        if (fermentation >= 4) scores.sweet -= 2;
                        if (this.match(text, ['尖酸','青苹果','草本','番茄'])) scores.sweet -= 1;
                        if (cleanliness > 0 && cleanliness <= 2) scores.sweet -= 2;

                        if (aroma >= 4) scores.aroma += 4;
                        if (this.match(text, ['茉莉','白花','玫瑰','橙花','柑橘','佛手柑','荔枝','葡萄','水蜜桃','热带水果','莓果'])) scores.aroma += 3;
                        if (this.match(text, ['埃塞俄比亚','巴拿马'])) scores.aroma += 2;
                        if (this.match(text, ['瑰夏','粉波旁','sidra','SL28','74110','74158'])) scores.aroma += 2;
                        if (this.match(text, ['水洗','日晒','蜜处理'])) scores.aroma += 1;
                        if (cleanliness >= 4) scores.aroma += 1;
                        if (fermentation >= 4) scores.aroma -= 1;
                        if (body >= 5 && aroma <= 3) scores.aroma -= 2;
                        if (aroma <= 3 && this.match(text, ['坚果','可可','麦芽','烟熏'])) scores.aroma -= 2;

                        if (specialProcess) scores.accent += 4;
                        if (this.match(text, ['厌氧','热冲击','酒桶','水果浸渍','酵母','乳酸菌','共发酵','二次发酵'])) scores.accent += 4;
                        if (fermentation >= 4) scores.accent += 3;
                        if (this.match(text, ['酒香','雪莉','白兰地','朗姆','草莓','葡萄','荔枝','香草','热带水果','乳酸','果酱'])) scores.accent += 3;
                        if (aroma >= 4) scores.accent += 1;
                        if (sweetness >= 4) scores.accent += 1;
                        if (priceLevel >= 4) scores.accent += 1;
                        if (!specialProcess && fermentation <= 2 && ordinaryProcess) scores.accent -= 3;
                        if (this.match(text, ['坚果','可可','焦糖']) && fermentation <= 2) scores.accent -= 2;
                        if (cleanliness > 0 && cleanliness <= 2) scores.accent -= 2;

                        if (this.between(acidity, 2, 4)) scores.balance += 2;
                        if (this.between(sweetness, 3, 4)) scores.balance += 2;
                        if (this.between(body, 3, 4)) scores.balance += 2;
                        if (this.between(aroma, 2, 4)) scores.balance += 1;
                        if (fermentation <= 2) scores.balance += 2;
                        if (cleanliness >= 4) scores.balance += 3;
                        if (this.match(text, ['哥伦比亚','危地马拉','洪都拉斯','墨西哥','秘鲁'])) scores.balance += 2;
                        if (this.match(text, ['水洗','蜜处理'])) scores.balance += 1;
                        if (this.match(text, ['通用','意式','黑咖'])) scores.balance += 1;
                        if (aroma >= 5) scores.balance -= 1;
                        if (fermentation >= 4) scores.balance -= 3;
                        if (acidity >= 5) scores.balance -= 2;
                        if (body > 0 && body <= 2) scores.balance -= 1;
                        if (specialProcess) scores.balance -= 2;

                        var primary = this.highestScoreRole(scores);
                        if (specialProcess && fermentation >= 4) {
                            primary = 'accent';
                        } else if (aroma >= 4 && cleanliness >= 4 && fermentation <= 3) {
                            primary = 'aroma';
                        } else if (body >= 4 && fermentation <= 2 && priceLevel <= 3) {
                            primary = 'base';
                        } else if (sweetness >= 4 && aroma <= 4 && fermentation <= 3) {
                            primary = 'sweet';
                        } else if (this.between(acidity, 2, 4) && this.between(sweetness, 3, 4) && this.between(body, 3, 4) && this.between(aroma, 2, 4) && cleanliness >= 4) {
                            primary = 'balance';
                        }
                        var secondary = this.subRolesByScores(scores, primary);
                        return {
                            primary: primary,
                            secondary: secondary,
                            scores: scores,
                            reason: this.roleReason(primary),
                            recommended_ratio: this.recommendedRatio(primary, bean),
                            avoid_roles: this.avoidRoles(primary, scores),
	                            strong_process: specialProcess ? 1 : 0,
                            confidence: this.roleConfidence(scores, primary)
                        };
                    },
                    beanJudgeText(bean) {
                        return [
                            bean.name,
                            bean.product_area,
                            bean.bean_seed,
                            bean.processing_method,
                            bean.special_flavour,
                            bean.custom_flavour_tags,
                            bean.specs,
                            bean.baking
                        ].filter(Boolean).join(' ');
                    },
                    match(text, words) {
                        text = String(text || '').toLowerCase();
                        return words.some(function (word) {
                            return text.indexOf(String(word).toLowerCase()) !== -1;
                        });
                    },
                    between(value, min, max) {
                        return value >= min && value <= max;
                    },
                    beanAromaStrength(bean) {
                        var text = this.beanJudgeText(bean);
                        var score = Number(bean.taste_aroma || 0);
                        if (this.match(text, ['茉莉','白花','玫瑰','橙花','柑橘','佛手柑','荔枝','葡萄','水蜜桃','热带水果','莓果','花香','果香'])) {
                            score = Math.max(score, 4);
                        }
                        return score;
                    },
                    beanFermentationStrength(bean) {
                        var text = this.beanJudgeText(bean);
                        if (this.match(text, ['厌氧','热冲击','酒桶','水果浸渍','酵母','乳酸菌','共发酵','二次发酵','强发酵','特殊发酵'])) return 5;
                        if (this.match(text, ['发酵','酒香','乳酸','果酱'])) return 4;
                        if (this.match(text, ['日晒','蜜处理'])) return 3;
                        return 2;
                    },
                    beanCleanlinessStrength(bean) {
                        var text = this.beanJudgeText(bean);
                        if (this.match(text, ['干净','清晰','透明','水洗'])) return 4;
                        if (this.match(text, ['浑浊','杂味','粗糙'])) return 2;
                        return 3;
                    },
                    beanPriceLevel(bean) {
                        var price = Number(bean.customized_price || bean.money || 0);
                        if (price >= 200) return 5;
                        if (price >= 150) return 4;
                        if (price >= 100) return 3;
                        if (price > 0) return 2;
                        return 3;
                    },
                    isSpecialProcess(bean) {
                        return this.match(this.beanJudgeText(bean), ['厌氧','热冲击','酒桶','水果浸渍','酵母','乳酸菌','共发酵','二次发酵','特殊发酵','特殊处理']);
                    },
                    highestScoreRole(scores) {
                        return Object.keys(scores).sort(function (a, b) {
                            return scores[b] - scores[a];
                        })[0] || 'base';
                    },
                    subRolesByScores(scores, primary) {
                        return Object.keys(scores).filter(function (role) {
                            return role !== primary && scores[role] >= 7;
                        }).sort(function (a, b) {
                            return scores[b] - scores[a];
                        }).slice(0, 2);
                    },
                    avoidRoles(primary, scores) {
                        return Object.keys(scores).filter(function (role) {
                            return role !== primary && scores[role] < 4;
                        });
                    },
                    recommendedRatio(role, bean) {
                        if (role === 'base') return '40%-70%';
                        if (role === 'sweet') return '15%-35%';
                        if (role === 'aroma') {
                            return this.match(this.beanJudgeText(bean), ['瑰夏','geisha','gesha']) || this.beanPriceLevel(bean) >= 4 ? '5%-15%' : '5%-20%';
                        }
                        if (role === 'accent') {
	                            return this.isSpecialProcess(bean) ? '3%-10%' : '3%-15%';
                        }
                        if (role === 'balance') return '10%-30%';
                        return '';
                    },
                    roleReason(role) {
                        var map = {
                            base: '该豆醇厚度和稳定性更适合作为配方主体，提供咖啡感和厚度。',
                            sweet: '该豆甜感表现更突出，适合提升配方的圆润度和顺口感。',
                            aroma: '该豆香气或花果香特征更明显，适合提升配方识别度。',
                            accent: '该豆处理法或风味表现更有个性，适合小比例增加特殊风味层次。',
                            balance: '该豆酸甜厚度较均衡，适合协调配方结构。'
                        };
                        return map[role] || '';
                    },
                    roleConfidence(scores, primary) {
                        var sorted = Object.keys(scores).map(function (role) {
                            return scores[role];
                        }).sort(function (a, b) { return b - a; });
                        var top = scores[primary] || 0;
                        var second = sorted.length > 1 ? sorted[1] : 0;
                        return Math.max(50, Math.min(95, Math.round(60 + (top - second) * 4 + top)));
                    },
                    roleLabel(role) {
                        var found = this.formulaPositionOptions.find(function (item) {
                            return item.value === role;
                        });
                        return found ? found.label : '配方定位';
                    },
                    roleScorePercent(score) {
                        return Math.max(0, Math.min(100, Number(score || 0) * 8));
                    },
                    resetForm(formName) {
                        this.$refs[formName].resetFields();
                    },
                    addImg(type, index, multiple) {
                        let that = this;
                        parent.Fast.api.open("general/attachment/select?multiple=" + multiple, "选择图片", {
                            callback: function (data) {
                                if (!data || !data.url) {
                                    return;
                                }
                                switch (type) {
                                    case "image":
                                        that.goodsDetail.image = data.url;
                                        break;
                                    case "images":
                                        that.goodsDetail.images = that.goodsDetail.images ? that.goodsDetail.images + ',' + data.url : data.url;
                                        let arrs = that.goodsDetail.images.split(',');
                                        if (arrs.length > 9) {
                                            that.goodsDetail.images_arr = arrs.slice(-9)
                                        } else {
                                            that.goodsDetail.images_arr = arrs
                                        }
                                        that.goodsDetail.images = that.goodsDetail.images_arr.join(',');
                                        break;
                                    case "sku":
                                        that.skuPrice[index].image = data.url;
                                        break;
                                }
                            }
                        });
                        return false;
                    },
                    delImg(type, index) {
                        let that = this;
                        switch (type) {
                            case "image":
                                that.goodsDetail.image = '';
                                break;
                            case "images":
                                that.goodsDetail.images_arr = that.goodsDetail.images_arr || [];
                                that.goodsDetail.images_arr.splice(index, 1);
                                that.goodsDetail.images = that.goodsDetail.images_arr.join(",");
                                break;
                            case "sku":
                                that.skuPrice[index].image = '';
                                break;
                        }
                    },
                    categoryChange(val) {
                        val = Array.isArray(val) ? val : [];
                        this.goodsDetail.category_id = val.join(',');
                    },
                    serviceChange(val) {
                        val = Array.isArray(val) ? val : [];
                        this.goodsDetail.service_ids = val.join(',');
                    },
                    getCategoryOptions(form) {
                        let that = this;
                        Fast.api.ajax({
                            url: 'yp/freight/select',
                            loading: false
                        },function (ret,res){
                            that.freight_list = res && res.data ? res.data : []
                            return false;
                        });
                        Fast.api.ajax({
                            url: 'yp/goods_category/select',
                            loading: false,
                        }, function (ret, res) {
                            that.categoryOptions = res && Array.isArray(res.data) ? res.data : [];
                            if (that.categoryOptions.length > 0) {
                                if (that.activeName && that.activeIndex) {

                                } else {
                                    that.activeName = that.categoryOptions[0].name
                                    that.activeIndex = 0;
                                }
                                that.categoryOptions.forEach(i => {
                                    if (i.children && i.children.length > 0) {
                                        i.children.forEach(j => {
                                            if (j.children && j.children.length > 0) {
                                                j.children.forEach(k => {
                                                    if (k.children && k.children.length > 0) {
                                                        k.children.forEach(g => {

                                                        })
                                                    } else {
                                                        delete k.children;
                                                    }
                                                })
                                            } else {
                                                delete j.children;
                                            }
                                        })
                                    } else {
                                        delete i.children;
                                    }
                                })
                            }
                            if (form) {
                                that.getEditData()
                            }
                            return false;
                        })
                    },
                    gotoback(formName) {
                        this.$refs[formName].validate((valid) => {
                            if (valid) {
                                this.stepActive++;
                            } else {
                                return false;
                            }
                        });
                    },
                    gonextback() {
                        this.stepActive--;
                    },
                    changeStockMode(value) {
                        if (Number(value) === 1) {
                            this.activeEditTab = 'stock';
                            if (!this.skuList.length && !this.skuPrice.length) {
                                this.$nextTick(() => {
                                    this.useFinishedBeanSkuTemplate();
                                });
                            }
                        }
                    },
                    useFinishedBeanSkuTemplate() {
                        let applyTemplate = () => {
                            let roastLevels = ['极浅烘焙', '浅度烘焙', '浅中烘焙', '中度烘焙', '中深烘焙', '深度烘焙'];
                            this.goodsDetail.is_stock = 1;
                            this.skuList = [
                                {
                                    id: 0,
                                    temp_id: this.countId++,
                                    name: '净含量',
                                    pid: 0,
                                    children: [
                                        {id: 0, temp_id: this.countId++, name: '125g', pid: 0},
                                        {id: 0, temp_id: this.countId++, name: '250g', pid: 0},
                                        {id: 0, temp_id: this.countId++, name: '500g', pid: 0}
                                    ]
                                },
                                {
                                    id: 0,
                                    temp_id: this.countId++,
                                    name: '烘焙度',
                                    pid: 0,
                                    children: roastLevels.map((name) => {
                                        return {id: 0, temp_id: this.countId++, name: name, pid: 0};
                                    })
                                }
                            ];
                            this.skuPrice = [];
                            this.isResetSku = 1;
                            this.buildSkuPriceTable();
                            this.activeEditTab = 'stock';
                        };
                        if (this.skuList.length || this.skuPrice.length) {
                            this.$confirm('使用模板会替换当前规格和价格矩阵，是否继续？', '提示', {
                                confirmButtonText: '继续',
                                cancelButtonText: '取消',
                                type: 'warning'
                            }).then(applyTemplate).catch(() => {});
                        } else {
                            applyTemplate();
                        }
                    },
                    //添加主规格
                    addMainSku() {
                        this.skuList.push({
                            id: 0,
                            temp_id: this.countId++,
                            name: this.skuModal,
                            pid: 0,
                            children: []
                        })
                        this.skuModal = '';
                        this.buildSkuPriceTable()
                    },
                    //添加子规格
                    addChildrenSku(k) {
                        if (!this.skuList[k]) {
                            return false;
                        }
                        this.skuList[k].children = Array.isArray(this.skuList[k].children) ? this.skuList[k].children : [];
                        // 检测当前子规格是否已经被添加过了
                        let isExist = false
                        this.skuList[k].children.forEach(e => {
                            if (e.name == this.childrenModal[k] && e.name != "") {
                                isExist = true
                            }
                        })

                        if (isExist) {
                            Toastr.error('子规格已存在');
                            return false;
                        }

                        this.skuList[k].children.push({
                            id: 0,
                            temp_id: this.countId++,
                            name: this.childrenModal[k],
                            pid: this.skuList[k].id,
                        })

                        this.childrenModal[k] = '';

                        // 如果是添加的第一个子规格，清空 skuPrice
                        if (this.skuList[k].children.length == 1) {
                            this.skuPrice = [] // 规格大变化，清空skuPrice
                            this.isResetSku = 1; // 重置规格
                        }

                        this.buildSkuPriceTable()
                    },
                    //删除主规格
                    deleteMainSku(k) {
                        let data = this.skuList[k]
                        if (!data) {
                            return false;
                        }

                        // 删除主规格
                        this.skuList.splice(k, 1)

                        // 如果当前删除的主规格存在子规格，则清空 skuPrice， 不存在子规格则不清空
                        if (data.children.length > 0) {
                            this.skuPrice = [] // 规格大变化，清空skuPrice
                            this.isResetSku = 1; // 重置规格
                        }

                        this.buildSkuPriceTable()
                    },
                    //删除子规格
                    deleteChildrenSku(k, i) {
                        if (!this.skuList[k] || !Array.isArray(this.skuList[k].children) || !this.skuList[k].children[i]) {
                            return false;
                        }
                        let data = this.skuList[k].children[i]
                        this.skuList[k].children.splice(i, 1)

                        // 查询 skuPrice 中包含被删除的的子规格的项，然后移除
                        let deleteArr = []
                        this.skuPrice.forEach((item, index) => {
                            item.goods_sku_text = Array.isArray(item.goods_sku_text) ? item.goods_sku_text : [];
                            item.goods_sku_text.forEach((e, i) => {
                                if (e == data.name) {
                                    deleteArr.push(index)
                                }
                            })
                        })
                        deleteArr.sort(function (a, b) {
                            return b - a;
                        })
                        // 移除有相关子规格的项
                        deleteArr.forEach((i, e) => {
                            this.skuPrice.splice(i, 1)
                        })

                        // 当前规格项，所有子规格都被删除，清空 skuPrice
                        if (this.skuList[k].children.length <= 0) {
                            this.skuPrice = [] // 规格大变化，清空skuPrice
                            this.isResetSku = 1; // 重置规格
                        }
                        this.buildSkuPriceTable()
                    },
                    editStatus(i) {
                        if (!this.skuPrice[i]) {
                            return false;
                        }
                        if (this.skuPrice[i].status == 'up') {
                            this.skuPrice[i].status = 'down'
                        } else {
                            this.skuPrice[i].status = 'up'
                        }

                    },
                    //组合新的规格价格库存净含量编码图片
                    buildSkuPriceTable() {
                        let arr = [];
                        //遍历sku子规格生成新数组，然后执行递归笛卡尔积
                        this.skuList.forEach((s1, k1) => {
                            let children = Array.isArray(s1.children) ? s1.children : [];
                            let childrenIdArray = [];
                            if (children.length > 0) {
                                children.forEach((s2, k2) => {
                                    childrenIdArray.push(s2.temp_id);
                                })

                                // 如果 children 子规格数量为 0,则不渲染当前规格, （相当于没有这个主规格）
                                arr.push(childrenIdArray);
                            }
                        })

                        this.recursionSku(arr, 0, []);
                    },
                    //递归找笛卡尔规格集合
                    recursionSku(arr, k, temp) {
                        if (k == arr.length && k != 0) {
                            let tempDetail = []
                            let tempDetailIds = []

                            temp.forEach((item, index) => {
                                for (let sku of this.skuList) {
                                    for (let child of sku.children) {
                                        if (item == child.temp_id) {
                                            tempDetail.push(child.name)
                                            tempDetailIds.push(child.temp_id)
                                        }
                                    }
                                }
                            })

                            let flag = false // 默认添加新的
                            for (let i = 0; i < this.skuPrice.length; i++) {
                                this.skuPrice[i].goods_sku_temp_ids = Array.isArray(this.skuPrice[i].goods_sku_temp_ids) ? this.skuPrice[i].goods_sku_temp_ids : [];
                                if (this.skuPrice[i].goods_sku_temp_ids.join(',') == tempDetailIds.join(',')) {
                                    flag = i
                                    break;
                                }
                            }

                            if (flag === false) {
                                this.skuPrice.push({
                                    id: 0,
                                    temp_id: this.skuPrice.length + 1,
                                    goods_sku_ids: '',
                                    goods_id: 0,
                                    image: '',
                                    stock: 0,
                                    money: 0,
                                    sn: '',
                                    erp_spec_no:'',
                                    weight: 0,
                                    status: 'up',
                                    goods_sku_text: tempDetail,
                                    goods_sku_temp_ids: tempDetailIds,
                                });

                            } else {
                                this.skuPrice[flag].goods_sku_text = tempDetail
                                this.skuPrice[flag].goods_sku_temp_ids = tempDetailIds
                            }
                            return;
                        }
                        if (arr.length) {
                            for (let i = 0; i < arr[k].length; i++) {
                                temp[k] = arr[k][i]
                                this.recursionSku(arr, k + 1, temp)
                            }
                        }
                    },
                    allEditData(type, opt) {
                        switch (opt) {
                            case 'define':
                                this.skuPrice.forEach(i => {
                                    i[type] = this.allEditDatas;
                                    if (type == 'store_take') {
                                        if (this.storeTakeEdit.store_take_switch == 0) {
                                            i.store_take = ''
                                            i.store_take_switch = '0'
                                        } else {
                                            i.store_take_switch = '1'
                                            i[type] = JSON.parse(JSON.stringify(this.storeTakeEdit));
                                            delete i.store_take.store_take_switch
                                        }
                                    }
                                })
                                break;
                        }
                        this.allEditDatas = ''
                        this.allEditPopover[type] = false;
                    },
                },
                watch: {
                    stepActive(newVal) {
                        this.editor = null;
                    },
                    skuList: {
                        handler(newName, oldName) {
                            if (this.isEditInit) { // 编辑初始化的时候会修改 skuList 但这时候不触发更新
                                this.buildSkuPriceTable();
                            }
                        },
                        deep: true
                    }
                },
            })
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});
