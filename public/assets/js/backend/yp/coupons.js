define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            $(".btn-add").data("area", ["80%", "80%"]);
            $(".btn-edit").data("area", ["80%", "80%"]);
            $(".btn-editone").data("area", ["80%", "80%"]);
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'yp/coupons/index' + location.search,
                    add_url: 'yp/coupons/add',
                    edit_url: 'yp/coupons/edit',
                    del_url: 'yp/coupons/del',
                    multi_url: 'yp/coupons/multi',
                    import_url: 'yp/coupons/import',
                    table: 'yp_coupons',
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
                        {checkbox: true},
                        // {field: 'id', title: __('Id')},
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'goods_type', title: __('Goods_type'), searchList: {"1":__('Goods_type 1'),"2":__('Goods_type 2')}, formatter: Table.api.formatter.normal},
                        {field: 'amount', title: __('Amount'), operate:false},
                        // {field: 'stock', title: __('Stock')},
                        {field: 'use_money', title: __('Use_money'), operate:false},
                        {field: 'day', title: __('Day')},
                        {field: 'endtime', title: __('Endtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'status', title: __('Status'), searchList: {"1":__('Status 1'),"2":__('Status 2')}, formatter: Table.api.formatter.status},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });
            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            const coupon = new Vue({
                el: "#app",
                data() {
                    return {
                        form_data:{
                            name:'',
                            goods_type:'1',
                            status:'1',
                            endtime:'',
                            day:'',
                            use_money:'',
                            stock:'',
                            amount:'',
                            goods_ids:[],
                        },
                        goods_type:[
                            {
                                label:'所有商品',
                                value:'1'
                            },
                            {
                                label:'部分商品',
                                value:'2'
                            }
                        ],
                        status_list:[
                            {
                                label:'正常',
                                value:'1'
                            },
                            {
                                label:'隐藏',
                                value:'2'
                            }
                        ],
                        goods_list:[],
                        rules: {
                            name: [{
                                required: true,
                                message: '请输入名称',
                                trigger: 'blur'
                            }],
                            endtime: [{
                                required: true,
                                message: '请选择结束时间',
                                trigger: 'blur'
                            }],
                            day: [{
                                required: true,
                                message: '请输领取后到期天数',
                                trigger: 'blur'
                            }],
                            use_money: [{
                                required: true,
                                message: '请输入使用门槛',
                                trigger: 'blur'
                            }],
                            stock: [{
                                required: true,
                                message: '请输入库存',
                                trigger: 'blur'
                            }],
                            amount: [{
                                required: true,
                                message: '请输入券面额',
                                trigger: 'blur'
                            }],
                            goods_type: [{
                                required: true,
                                message: '请选择适用商品',
                                trigger: 'blur'
                            }],
                        },
                    }
                },
                mounted() {

                },
                methods: {
                    changeGoodsType(){
                        if(this.form_data.goods_type == 1){
                            this.form_data.goods_ids = [];
                            this.goods_list = [];
                        }
                    },
                    removeGoods(goods_id){
                        for(let i = 0; i < this.form_data.goods_ids.length; i++) {
                            if(this.form_data.goods_ids[i] == goods_id){
                                this.form_data.goods_ids.splice(i,1);
                                break;
                            }
                        }
                        for(let i = 0; i < this.goods_list.length; i++) {
                            if(this.goods_list[i].id == goods_id){
                                this.goods_list.splice(i,1);
                                break;
                            }
                        }
                    },
                    changeGoods(){
                        Fast.api.open("yp/goods/select?multiple=true", __('选择商品链接'), {
                            area: ['800px', '600px'],
                            callback: (data)=> {
                                if(data.url.length > 0){
                                    let ids = data.url.split(',');
                                    let goods_list = data.data;
                                    ids.forEach((res,index)=>{
                                        if(!this.form_data.goods_ids.includes(res)){
                                            this.form_data.goods_ids.push(res);
                                            this.goods_list.push(goods_list[index])
                                        }
                                    })
                                }
                            }
                        });
                    },
                    onSubmit(){
                        this.$refs['form'].validate((valid) => {
                            if (valid) {
                                Fast.api.ajax({
                                    url: 'yp/coupons/add',
                                    data:{row:this.form_data}
                                },function (ret,res){
                                    if(res.code == 1){
                                        parent.$(".btn-refresh").trigger("click");
                                        setTimeout(function(){Fast.api.close()},300)
                                    }
                                });
                            } else {
                                return false;
                            }
                        });
                    }
                }
            });
            Controller.api.bindevent();
        },
        edit: function () {
            const coupon = new Vue({
                el: "#app",
                data() {
                    return {
                        form_data:{
                            id:Config.id,
                            name:'',
                            goods_type:'1',
                            status:'1',
                            endtime:'',
                            day:'',
                            use_money:'',
                            stock:'',
                            amount:'',
                            goods_ids:[],
                        },
                        goods_type:[
                            {
                                label:'所有商品',
                                value:'1'
                            },
                            {
                                label:'部分商品',
                                value:'2'
                            }
                        ],
                        status_list:[
                            {
                                label:'正常',
                                value:'1'
                            },
                            {
                                label:'隐藏',
                                value:'2'
                            }
                        ],
                        goods_list:[],
                        rules: {
                            name: [{
                                required: true,
                                message: '请输入名称',
                                trigger: 'blur'
                            }],
                            endtime: [{
                                required: true,
                                message: '请选择结束时间',
                                trigger: 'blur'
                            }],
                            day: [{
                                required: true,
                                message: '请输领取后到期天数',
                                trigger: 'blur'
                            }],
                            use_money: [{
                                required: true,
                                message: '请输入使用门槛',
                                trigger: 'blur'
                            }],
                            stock: [{
                                required: true,
                                message: '请输入库存',
                                trigger: 'blur'
                            }],
                            amount: [{
                                required: true,
                                message: '请输入券面额',
                                trigger: 'blur'
                            }],
                            goods_type: [{
                                required: true,
                                message: '请选择适用商品',
                                trigger: 'blur'
                            }],
                        },
                    }
                },
                mounted() {
                    this.getData();
                },
                methods: {
                    getData(){
                        let that = this;
                        Fast.api.ajax({
                            url:'yp/coupons/detail',
                            data:{id:that.form_data.id},
                            loading:false
                        },function (ret,res){
                            that.form_data = ret;
                            if(that.form_data.goods_type == 2){
                                that.goods_list = ret.goods_list;
                            }
                            console.log(that.form_data.endtime);
                            return false;
                        })
                    },
                    changeGoodsType(){
                        if(this.form_data.goods_type == 1){
                            this.form_data.goods_ids = [];
                            this.goods_list = [];
                        }
                    },
                    removeGoods(goods_id){
                        for(let i = 0; i < this.form_data.goods_ids.length; i++) {
                            if(this.form_data.goods_ids[i] == goods_id){
                                this.form_data.goods_ids.splice(i,1);
                                break;
                            }
                        }
                        for(let i = 0; i < this.goods_list.length; i++) {
                            if(this.goods_list[i].id == goods_id){
                                this.goods_list.splice(i,1);
                                break;
                            }
                        }
                    },
                    changeGoods(){
                        Fast.api.open("yp/goods/select?multiple=true", __('选择商品链接'), {
                            area: ['800px', '600px'],
                            callback: (data)=> {
                                if(data.url.length > 0){
                                    let ids = data.url.split(',');
                                    let goods_list = data.data;
                                    ids.forEach((res,index)=>{
                                        if(!this.form_data.goods_ids.includes(res)){
                                            this.form_data.goods_ids.push(res);
                                            this.goods_list.push(goods_list[index])
                                        }
                                    })
                                }
                            }
                        });
                    },
                    onSubmit(){
                        let that = this;
                        that.$refs['form'].validate((valid) => {
                            if (valid) {
                                Fast.api.ajax({
                                    url: 'yp/coupons/edit?ids='+that.form_data.id,
                                    data:{row:that.form_data}
                                },function (ret,res){
                                    if(res.code == 1){
                                        parent.$(".btn-refresh").trigger("click");
                                        setTimeout(function(){Fast.api.close()},300)
                                    }
                                });
                            } else {
                                return false;
                            }
                        });
                    }
                }
            });
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
