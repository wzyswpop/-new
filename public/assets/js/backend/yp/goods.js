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
                    import_url: 'yp/goods/import',
                    table: 'yp_goods',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'weigh',
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
                        {field: 'category.name', title: __('Category.name'), operate: 'LIKE'},
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'money', title: __('Money'), operate:false},
                        {field: 'image', title: __('Image'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'sales', title: __('Sales')},
                        {field: 'see', title: __('See')},
                        {field: 'is_hot', title: __('Is_hot'), searchList: {"1":__('Is_hot 1'),"2":__('Is_hot 2')}, formatter: Table.api.formatter.normal},
                        {field: 'weigh', title: __('热销排序')},
                        {field: 'is_stock', title: __('Is_stock'),searchList: {"0":__('Is_stock 0'),"1":__('Is_stock 1')}, formatter: Table.api.formatter.normal},
                        {field: 'status', title: __('Status'), searchList: {"1":__('Status 1'),"2":__('Status 2')}, formatter: Table.api.formatter.status},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });
            table.on('post-body.bs.table', function (e, settings, json, xhr) {
                $(".btn-editone").data("area", ['80%','90%']);// 编辑弹窗
            });
            // 为表格绑定事件
            Table.api.bindevent(table);
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
                        goodsDetail: {},
                        goodsDetailInit: {
                            freight_id:'',
                            name: '',
                            desc: '',
                            is_hot:1,
                            classify:1,
                            is_customized:0,
                            customized_price:0,
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
                                required: true,
                                message: '请上传商品轮播图',
                                trigger: 'change'
                            }],
                            category_id: [{
                                required: true,
                                message: '请选择商品分类',
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
                        this.goodsDetail.tags_arr.splice(this.goodsDetail.tags_arr.indexOf(tag), 1);
                    },
                    handleInputConfirm() {
                        let inputValue = this.inputValue;
                        if (inputValue) {
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
                        console.log(val)
                        if(val != undefined){
                            that.goodsDetail.category_id = val[val.length - 1];
                        }
                    },
                    closeTag(val) {
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
                        // 记录每个规格项真实 id，对应的临时 id
                        let tempIdArr = {};
                        for (let i in skuList) {
                            // 为每个 规格增加当前页面自增计数器，比较唯一用
                            skuList[i]['temp_id'] = this.countId++
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
                            let goods_sku_id_arr = tempSkuPrice['goods_sku_ids'].split(',');
                            for (let ids of goods_sku_id_arr) {
                                tempSkuPrice['goods_sku_temp_ids'].push(tempIdArr[ids])
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
                            for (key in that.goodsDetail) {
                                if (res.data.detail[key]) {
                                    that.goodsDetail[key] = res.data.detail[key]
                                }
                            }
                            that.goodsDetail.category_ids_arr = that.goodsDetail.category_ids_arr[0];
                            that.getInit(res.data.skuList, res.data.skuPrice);
                            Controller.api.bindevent();
                            $('#c-content').html(res.data.detail.content)
                            return false;
                        })
                    },
                    submitForm(formName) {
                        this.$refs[formName].validate((valid) => {
                            if (valid) {
                                let that = this;
                                let arrForm = JSON.parse(JSON.stringify(that.goodsDetail));
                                //处理轮播图
                                arrForm.images = arrForm.images_arr.join(',')
                                //规格
                                if (arrForm.is_stock == 0) {
                                    that.skuList = [];
                                    that.skuPrice = [];
                                }
                                if (arrForm.is_stock != 0) {
                                    submitSkuList = JSON.parse(JSON.stringify(that.skuList))
                                    submitSkuPrice = JSON.parse(JSON.stringify(that.skuPrice))
                                }
                                // 图文详情
                                arrForm.content = $("#c-content").val();
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
                                return false;
                            }
                        });
                    },
                    resetForm(formName) {
                        this.$refs[formName].resetFields();
                    },
                    addImg(type, index, multiple) {
                        let that = this;
                        parent.Fast.api.open("general/attachment/select?multiple=" + multiple, "选择图片", {
                            callback: function (data) {
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
                                that.goodsDetail.images_arr.splice(index, 1);
                                that.goodsDetail.images = that.goodsDetail.images_arr.join(",");
                                break;
                            case "sku":
                                that.skuPrice[index].image = '';
                                break;
                        }
                    },
                    categoryChange(val) {
                        this.goodsDetail.category_id = val.join(',');
                    },
                    serviceChange(val) {
                        this.goodsDetail.service_ids = val.join(',');
                    },
                    getCategoryOptions(form) {
                        let that = this;
                        Fast.api.ajax({
                            url: 'yp/freight/select',
                            loading: false
                        },function (ret,res){
                            that.freight_list = res.data
                            return false;
                        });
                        Fast.api.ajax({
                            url: 'yp/goods_category/select',
                            loading: false,
                        }, function (ret, res) {
                            that.categoryOptions = res.data;
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
                        let data = this.skuList[k].children[i]
                        this.skuList[k].children.splice(i, 1)

                        // 查询 skuPrice 中包含被删除的的子规格的项，然后移除
                        let deleteArr = []
                        this.skuPrice.forEach((item, index) => {
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
                        if (this.skuPrice[i].status == 'up') {
                            this.skuPrice[i].status = 'down'
                        } else {
                            this.skuPrice[i].status = 'up'
                        }

                    },
                    //组合新的规格价格库存重量编码图片
                    buildSkuPriceTable() {
                        let arr = [];
                        //遍历sku子规格生成新数组，然后执行递归笛卡尔积
                        this.skuList.forEach((s1, k1) => {
                            let children = s1.children;
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
