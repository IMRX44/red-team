//Alpine
document.addEventListener('alpine:init', () => {

    Alpine.data("revenue", ()=>({

        namePage: null,

        revenue: {
            loading: false,
            current: {
                data: null,
                filters: {
                    from_data: null,
                    to_date: null,
                }
            },
            previous: {
                data: null,
                filters: {
                    from_data: null,
                    to_date: null,
                }
            }
        },

        chart:{
            loading: false,
            current: null,
            previous: null,
            filters: {
                interval: {
                    label: 'روز',
                    value: 'day'
                },
                type: {
                    label: 'درآمد ناخالص',
                    value: 'total_sales'
                }
            },
            intervals:[
                {
                    label: 'روز',
                    value: 'day'
                },
                {
                    label: 'هفته',
                    value: 'week'
                },
                {
                    label: 'ماه',
                    value: 'month'
                },
                {
                    label: 'سال',
                    value: 'year'
                }
            ],
            types: [
                {
                    label: 'درآمد ناخالص',
                    value: 'total_sales'
                },
                {
                    label: 'درآمد خالص',
                    value: 'net_sales'
                }
            ]
        },

        topSellers: {
            loading: false,
            filters: {
                type: 'product', //product or category
            },
            products: {
                items: [],
                max: 0
            },
            categories: {
                items: [],
                max: 0
            },
        },

        date:{
            today: null,
            type: 'range', //quick
            comparison: 'year', //period
            range: {
                from: null,
                to: null,
            },
            previousRange: {
                from: null,
                to: null,
            },
            quick: {
                items: [],
                selected: null,
            }
        },

        table: {
            data: [],
            loading: false,
            filters: {
                page: 1,
                per_page: 20
            },
            pagination: {
                items: [],
                currentPage: 1,
                totalPage: 0,
            }
        },

        modals: {
            rangeDate:{
                active: false
            }
        },

        async init(){
            this.date.today = new persianDate();
            this.date.quick.items = pwCreateQuickSelectDateItems(this.date.today);

            await this.getPageData();

            //initial date picker
            const tempFromDate = this.revenue.current.filters.from_date ? this.revenue.current.filters.from_date  : this.date.today;
            const tempToDate = this.revenue.current.filters.to_date ? this.revenue.current.filters.to_date : this.date.today;
            const [rangeDateFrom, rangeDateTo] = pwCreateRangeDateFilter(document.getElementById("rangeDateFilter"), tempFromDate, tempToDate)
            this.date.range.from = rangeDateFrom;
            this.date.range.to = rangeDateTo;
        },

        //request functions
        async getPageData() {

            const queryString = pwParseQueryString(window.location.href);
            this.namePage = queryString.page || null;
            delete queryString.page;
            delete queryString.per_page;

            if(queryString.comparison && (queryString.comparison === 'period' || queryString.comparison === 'year')){
                this.date.comparison = queryString.comparison;
            }

            for (const objKey in queryString) {
                if (objKey === 'from_date' || objKey === 'to_date') {
                    if (pwCheckDateFormatIsValid(queryString[objKey])) {
                        this.revenue.current.filters[objKey] = pwDateToTimestamp(queryString[objKey], (objKey === 'to_date'));
                    } else {
                        delete queryString[objKey];
                    }
                } else {
                    this.revenue.current.filters[objKey] = queryString[objKey];
                }
            }

            if (!this.revenue.current.filters?.from_date || !this.revenue.current.filters?.to_date || (this.revenue.current.filters?.from_date > this.revenue.current.filters?.to_date)) {
                this.revenue.current.filters.from_date = this.date.today.startOf('day').add('days', -31).unix() * 1000;
                this.revenue.current.filters.to_date = this.date.today.endOf('day').unix() * 1000;
            }

            const filtersObj = pwGenerateFiltersObject({
                ...this.revenue.current.filters,
                comparison: this.date.comparison
            });
            pwSetUrlQueryParams(this.namePage, filtersObj);

            console.log('current')
            console.log(pwFormatDate(this.revenue.current.filters.from_date, 'YYYY/MM/DD hh:mm:ss'));
            console.log(pwFormatDate(this.revenue.current.filters.to_date, 'YYYY/MM/DD hh:mm:ss'));


            const formDate =  new persianDate(this.revenue.current.filters.from_date);
            const toDate = new persianDate(this.revenue.current.filters.to_date);

            if(this.date.comparison === 'year') {
                this.date.previousRange.from = formDate.add('year', -1);
                this.date.previousRange.to = toDate.add('year', -1)
            }else{
                if(this.date.quick.selected){
                    this.date.previousRange.from = this.date.quick.selected.previousFrom;
                    this.date.previousRange.to = this.date.quick.selected.previousTo;
                }else{
                    const diffDays = -1 * (toDate.diff(formDate, 'days'));
                    this.date.previousRange.from = formDate.add('days', diffDays).startOf('day');
                    this.date.previousRange.to = toDate.add('days', diffDays).endOf('day');
                }
            }

            console.log('previous')
            console.log(pwFormatDate(this.date.previousRange.from, 'YYYY/MM/DD hh:mm:ss'));
            console.log(pwFormatDate(this.date.previousRange.to, 'YYYY/MM/DD hh:mm:ss'));

            this.revenue.previous.filters.from_date = this.date.previousRange.from.unix() * 1000;
            this.revenue.previous.filters.to_date = this.date.previousRange.to.unix() * 1000;

            await Promise.all([
                this.getTopSellers(),
                this.getSummary(),
                this.getChartData(),
                this.getOrders()
            ])

            pwLoadTippyInPage();
        },

        async getSummary(){
            this.revenue.loading = true;

            try{
                const [current, previous] = await Promise.all([
                    pwApiRequest('persian-woocommerce/reports/revenue/summary', {
                        method: 'GET',
                        data: pwGenerateFiltersObject(this.revenue.current.filters)
                    }),
                    pwApiRequest('persian-woocommerce/reports/revenue/summary', {
                        method: 'GET',
                        data: pwGenerateFiltersObject(this.revenue.previous.filters)
                    })
                ])

                this.revenue.current.data = [];
                this.revenue.previous.data = [];

                if(current.success && previous.success){
                    this.revenue.current.data = current.data
                    this.revenue.previous.data = previous.data
                }else{
                    pwNotyf.error(current.message ? current.message : 'حطایی رخ داده است!');
                }

                this.revenue.loading = false;

            }catch (error){
                console.error('Error fetching posts:', error);
                this.revenue.loading = false;
            }
        },

        async getTopSellers(){
            this.topSellers.loading = true;

            try{
                const result = await pwApiRequest('persian-woocommerce/reports/revenue/top-sellers', {
                    method: 'GET',
                    data: pwGenerateFiltersObject(this.revenue.current.filters)
                })

                this.topSellers.products.items = [];
                this.topSellers.products.max = 0;
                this.topSellers.categories.items = [];
                this.topSellers.categories.max = 0;

                if(result.success){
                    const {products, categories} = result.data;

                    for (const key in products) {
                        this.topSellers.products.items.push(products[key]);
                        if(this.topSellers.products.max < products[key].count){
                            this.topSellers.products.max = products[key].count;
                        }
                    }
                    this.topSellers.products.items.sort((a, b) => (b.count || 0) - (a.count || 0));

                    for (const key in categories) {
                        this.topSellers.categories.items.push(categories[key]);
                        if(this.topSellers.categories.max < categories[key].count){
                            this.topSellers.categories.max = categories[key].count;
                        }
                    }
                    this.topSellers.categories.items.sort((a, b) => (b.count || 0) - (a.count || 0));

                }else{
                    pwNotyf.error(result.message ? result.message : 'حطایی رخ داده است!');
                }

                this.topSellers.loading = false;

            }catch (error){
                console.error('Error fetching posts:', error);
                this.topSellers.loading = false;
            }
        },

        async getChartData(){
            this.chart.loading = true;

            try{
                const [current, previous] = await Promise.all([
                    pwApiRequest('persian-woocommerce/reports/revenue/chart', {
                        method: 'GET',
                        data: {
                            ...pwGenerateFiltersObject(this.revenue.current.filters),
                            interval: this.chart.filters.interval.value
                        }
                    }),
                    pwApiRequest('persian-woocommerce/reports/revenue/chart', {
                        method: 'GET',
                        data: {
                            ...pwGenerateFiltersObject(this.revenue.previous.filters),
                            interval: this.chart.filters.interval.value
                        }
                    })
                ])

                if(current.success && previous.success){
                    this.chart.current = current.data;
                    this.chart.previous = previous.data;
                    this.drawingChart();
                }else{
                    if(!current.success){
                        pwNotyf.error(current.message ? current.message : 'حطایی رخ داده است!');
                    }
                    if(!previous.success){
                        pwNotyf.error(previous.message ? previous.message : 'حطایی رخ داده است!');
                    }
                }
                this.chart.loading = false;

            }catch (error){
                console.error('Error fetching posts:', error);
                this.chart.loading = false;
            }
        },

        async getOrders(){
            this.table.loading = true;

            try{
                const result = await pwApiRequest('persian-woocommerce/reports/revenue/orders', {
                    method: 'GET',
                    data: {
                        ...pwGenerateFiltersObject(this.revenue.current.filters),
                        ...this.table.filters
                    }
                })

                this.table.data = [];

                if(result.success){
                    const data = result.data;
                    for (const key in data.orders) {
                        this.table.data.push({
                            date: key,
                            ...data.orders[key]
                        });
                    }

                    if(this.table.data.length > 0){
                        this.table.pagination = {
                            currentPage: data.pagination.current_page,
                            totalPage: parseInt((data.pagination.total_items / this. table.filters.per_page)) + 1,
                            items: pwGetVisiblePages({
                                currentPage: data.pagination.current_page,
                                totalPage: parseInt((data.pagination.total_items / this. table.filters.per_page)) + 1
                            })
                        }
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
        selectDateFilter(from, to){
            const [rangeDateFrom, rangeDateTo] = pwCreateRangeDateFilter(document.getElementById("rangeDateFilter"), from, to)
            this.date.range.from = rangeDateFrom;
            this.date.range.to = rangeDateTo;
        },

        setDateFilter(from = null, to= null){

            this.revenue.current.filters.from_date = this.date.range.from.getState().selected.unixDate;
            this.revenue.current.filters.to_date = this.date.range.to.getState().selected.unixDate;

            if(this.date.quick.selected){
                console.log(this.date.quick.selected.from.unix() * 1000, this.revenue.current.filters.from_date);
                if(((this.date.quick.selected.from.unix() * 1000 ) !== this.revenue.current.filters.from_date) || ((this.date.quick.selected.to.unix() * 1000 ) !== this.revenue.current.filters.to_date)){
                    this.date.quick.selected = null;
                    console.log("salam")
                }
            }

            const filtersObj = pwGenerateFiltersObject({
                ...this.revenue.current.filters,
                comparison: this.date.comparison
            });
            pwSetUrlQueryParams(this.namePage, filtersObj);

            this.getPageData()

        },

        clearDateFilter(){
            this.revenue.current.filters.from_date = this.date.today.startOf('day').add('days', -31).unix() * 1000;
            this.revenue.current.filters.to_date = this.date.today.endOf('day').unix() * 1000;

            const filtersObj = pwGenerateFiltersObject({
                ...this.revenue.current.filters,
                comparison: this.date.comparison
            });
            pwSetUrlQueryParams(this.namePage, filtersObj);

            this.date.quick.selected = null;
            this.getPageData()
        },

        drawingChart(){
            const labels = [];
            const currentDataset =  [];
            const previousDataset =  [];

            for (const key in this.chart.current) {
                labels.push(key)
                currentDataset.push(this.chart.current[key][this.chart.filters.type.value]);
            }

            for (const key in this.chart.previous) {
                previousDataset.push(this.chart.previous[key][this.chart.filters.type.value]);
            }

            this.chartData = {
                labels,
                datasets: [
                    {
                        label: 'دوره فعلی',
                        data: currentDataset,
                        borderColor: '#007BFF',
                        borderWidth: 2,
                        gradient: {
                            backgroundColor: {
                                axis: 'y',
                                colors: {
                                    0: 'rgba(0,123,255,0)',
                                    90: 'rgba(0,123,255,0.5)'
                                }
                            }
                        },
                        fill: true,
                        tension: 0.5,
                        cubicInterpolationMode: 'monotone'
                    },
                    {
                        label: 'دوره قبلی',
                        data: previousDataset,
                        borderColor: '#FE6BBA',
                        borderWidth: 2,
                        gradient: {
                            backgroundColor: {
                                axis: 'y',
                                colors: {
                                    0: 'rgba(254,107,186,0)',
                                    90: 'rgba(254,107,186,0.5)'
                                }
                            }
                        },
                        fill: true,
                        tension: 0.5,
                        cubicInterpolationMode: 'monotone'
                    }
                ]
            }
            pwCreateChart(document.getElementById("chart"),  this.chartData);
        },

        compare(oldValue, newValue){

            if(!oldValue && !newValue){
                return `
                    <div class="flex items-center gap-1 text-gray-500 bg-gray-100 rounded-md text-xs cursor-pointer py-1 px-2">
                        <span dir="ltr">0%</span>
                    </div>
                `
            }

            oldValue = (typeof oldValue === 'undefined') ? 0 : oldValue;
            newValue = (typeof newValue === 'undefined') ? 0 : newValue;

            let changeAmount = 0;
            if(oldValue === 0){
                changeAmount = ((newValue - oldValue) * 100 );
            }else{
                changeAmount = (((newValue - oldValue) * 100 ) / oldValue);
            }

            if(changeAmount === 0){
                return `
                    <div class="flex items-center gap-1 text-gray-500 bg-gray-100 rounded-md text-xs cursor-pointer py-1 px-2">
                        <span dir="ltr">0%</span>
                    </div>
                `
            }else if(changeAmount > 0){
                return `
                    <div class="flex items-center gap-1 text-positive-state bg-positive-state/10 rounded-md text-xs cursor-pointer py-1 px-2">
                        <span dir="ltr">+${Number(changeAmount.toFixed(0))}%</span>
                        <img src="${pwAssetsFolder}/images/icons/ascending.svg">
                    </div>
                `
            }else{
                return `
                    <div class="flex items-center gap-1 text-warning-state bg-warning-state/10 rounded-md text-xs cursor-pointer py-1 px-2">
                        <span dir="ltr">${Number(changeAmount.toFixed(0))}%</span>
                        <img src="${pwAssetsFolder}/images/icons/descending.svg">
                    </div>
                `
            }
        },

        changePage(newPage){
            this.table.pagination.currentPage = newPage;
            this.table.filters.page = newPage;
            this.getOrders();
        }

    }))

})