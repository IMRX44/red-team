//Alpine
document.addEventListener('alpine:init', () => {

    Alpine.data("stock", ()=>({

        stock: {
            loading: false,
            data: null
        },

        statuses: [],

        table: {
            data: [],
            loading: false,
            filters: {
                page: 1,
                per_page: 25,
                from_date: null,
                to_date: null,
                status: null
            },
            pagination: {
                items: [],
                currentPage: 1,
                totalPage: 0,
            }
        },

        async init(){
            await this.getPageData();
        },

        //request functions
        async getPageData(){
            await Promise.all([
                this.getSummary(),
                this.getProducts()
            ]);

            pwLoadTippyInPage();
        },

        async getSummary(){
            this.stock.loading = true;

            try{
                const result = await pwApiRequest('persian-woocommerce/reports/stock/summary', {
                    method: 'GET'
                })

                if(result.success){
                    const data = result.data;

                    this.stock.data = data;

                    this.statuses = [
                        {
                            value: 'همه',
                            key: null,
                            count: data.outofstock_count + data.outofstock_count +  data.lowstock_count + data.onbackorder_count
                        },
                        {
                            value: 'ناموجود',
                            key: 'outofstock',
                            count: data.outofstock_count
                        },
                        {
                            value: 'کمبود موجودی',
                            key: 'lowstock',
                            count: data.lowstock_count
                        },
                        {
                            value: 'موجود',
                            key: 'instock',
                            count: data.instock_count
                        },
                        {
                            value: 'در پیش خرید',
                            key: 'onbackorder',
                            count: data.onbackorder_count
                        }
                    ]

                }else{
                    pwNotyf.error(result.message ? result.message : 'حطایی رخ داده است!');
                }

                this.stock.loading = false;

            }catch (error){
                console.error('Error fetching posts:', error);
                this.stock.loading = false;
            }
        },

        async getProducts(){
            this.table.loading = true;

            try{
                const result = await pwApiRequest('persian-woocommerce/reports/stock/products', {
                    method: 'GET',
                    data: pwGenerateFiltersObject(this.table.filters)
                })

                if(result.success){
                    const data = result.data;

                    this.table.data =  data.products;
                    this.table.pagination = {
                        currentPage: data.pagination.current_page,
                        totalPage: parseInt((data.pagination.total_items / this. table.filters.per_page)) + 1,
                        items: pwGetVisiblePages({
                            currentPage: data.pagination.current_page,
                            totalPage: parseInt((data.pagination.total_items / this. table.filters.per_page)) + 1
                        })
                    }

                }else{
                    pwNotyf.error(result.message ? result.message : 'حطایی رخ داده است!');
                }

                this.table.loading = false;

            }catch (error){
                console.error('Error fetching posts:', error);
                this.table.loading = false;
            }
        },

        //other function
        changePage(newPage){
            this.table.pagination.currentPage = newPage;
            this.table.filters.page = newPage;
            this.getProducts();
        },

    }))

})