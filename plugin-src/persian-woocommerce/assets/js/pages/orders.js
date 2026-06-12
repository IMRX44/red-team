//Alpine
document.addEventListener('alpine:init', () => {

    Alpine.data("orders", ()=>({

        namePage: null,

        orders: {
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

        chart: {
            loading: false,
            data: null
        },

        table: {
            data: [],
            loading: true,
            filters: {
                page: 1,
                per_page: 25
            },
            pagination: {
                items: [],
                currentPage: 1,
                totalPage: 0,
            }
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
            const tempFromDate = this.orders.current.filters.from_date ? this.orders.current.filters.from_date  : this.date.today;
            const tempToDate = this.orders.current.filters.to_date ? this.orders.current.filters.to_date : this.date.today;
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
                        this.orders.current.filters[objKey] = pwDateToTimestamp(queryString[objKey], (objKey === 'to_date'));
                    } else {
                        delete queryString[objKey];
                    }
                } else {
                    this.orders.current.filters[objKey] = queryString[objKey];
                }
            }

            if (!this.orders.current.filters?.from_date || !this.orders.current.filters?.to_date || (this.orders.current.filters?.from_date > this.orders.current.filters?.to_date)) {
                this.orders.current.filters.from_date = this.date.today.startOf('day').add('days', -31).unix() * 1000;
                this.orders.current.filters.to_date = this.date.today.endOf('day').unix() * 1000;
            }

            const filtersObj = pwGenerateFiltersObject({
                ...this.orders.current.filters,
                comparison: this.date.comparison
            });
            pwSetUrlQueryParams(this.namePage, filtersObj);

            const formDate =  new persianDate(this.orders.current.filters.from_date);
            const toDate = new persianDate(this.orders.current.filters.to_date);

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

            //console.log(pwFormatDate(this.date.previousRange.from, 'YYYY/MM/DD'));
            //console.log(pwFormatDate(this.date.previousRange.to, 'YYYY/MM/DD'));

            this.orders.previous.filters.from_date = this.date.previousRange.from.unix() * 1000;
            this.orders.previous.filters.to_date = this.date.previousRange.to.unix() * 1000;

            await Promise.all([
                this.getOrders(),
                this.getCustomers(),
                this.getChartData()
            ])

            pwLoadTippyInPage();
        },

        async getOrders(){
            this.orders.loading = true;

            try{
                const [current, previous] = await Promise.all([
                    pwApiRequest('persian-woocommerce/reports/customer/summary', {
                        method: 'GET',
                        data: pwGenerateFiltersObject(this.orders.current.filters)
                    }),
                    pwApiRequest('persian-woocommerce/reports/customer/summary', {
                        method: 'GET',
                        data: pwGenerateFiltersObject(this.orders.previous.filters)
                    })
                ])

                this.orders.current.data = [];
                this.orders.previous.data = [];

                if(current.success && previous.success){
                    this.orders.current.data = current.data;
                    this.orders.previous.data = previous.data;
                }else{
                    pwNotyf.error(current.message ? current.message : 'حطایی رخ داده است!');
                }

                this.orders.loading = false;

            }catch (error){
                console.error('Error fetching posts:', error);
                this.orders.loading = false;
            }
        },

        async getCustomers(){
            this.table.loading = true;

            try{
                const result = await pwApiRequest('persian-woocommerce/reports/customer/users', {
                    method: 'GET',
                    data: pwGenerateFiltersObject({
                        /*from_date: 1702466566000,
                        to_date: 1765624966000,*/
                        ...this.orders.current.filters,
                        ...this.table.filters
                    })
                })

                if(result.success){
                    const data = result.data;

                    this.table.data = data.customers?.length > 0 ? data.customers : [];

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

        async getChartData(){
            this.chart.loading = true;

            try{
                const result = await pwApiRequest('persian-woocommerce/reports/customer/chart', {
                    method: 'GET',
                    data: pwGenerateFiltersObject(this.orders.current.filters)
                })

                if(result.success){
                    this.chart.data = result.data;
                    this.drawingChart();
                }else{
                    pwNotyf.error(result.message ? result.message : 'حطایی رخ داده است!');
                }

                this.chart.loading = false;

            }catch (error){
                console.error('Error fetching posts:', error);
                this.chart.loading = false;
            }
        },

        //other function
        selectDateFilter(from, to){
            const [rangeDateFrom, rangeDateTo] = pwCreateRangeDateFilter(document.getElementById("rangeDateFilter"), from, to)
            this.date.range.from = rangeDateFrom;
            this.date.range.to = rangeDateTo;
        },

        setDateFilter(from = null, to= null){

            this.orders.current.filters.from_date = this.date.range.from.getState().selected.unixDate;
            this.orders.current.filters.to_date = this.date.range.to.getState().selected.unixDate;

            if(this.date.quick.selected){
                if(((this.date.quick.selected.from.unix() * 1000 ) !== this.orders.current.filters.from_date) || ((this.date.quick.selected.to.unix() * 1000 ) !== this.orders.current.filters.to_date)){
                    this.date.quick.selected = null
                }
            }

            const filtersObj = pwGenerateFiltersObject({
                ...this.orders.current.filters,
                comparison: this.date.comparison
            });
            pwSetUrlQueryParams(this.namePage, filtersObj);

            this.getPageData()

        },

        clearDateFilter(){
            this.orders.current.filters.from_date = this.date.today.startOf('day').add('days', -31).unix() * 1000;
            this.orders.current.filters.to_date = this.date.today.endOf('day').unix() * 1000;

            const filtersObj = pwGenerateFiltersObject({
                ...this.orders.current.filters,
                comparison: this.date.comparison
            });
            pwSetUrlQueryParams(this.namePage, filtersObj);

            this.date.quick.selected = null;
            this.getPageData()
        },

        drawingChart(){
            const labels = [];
            const userDataset =  [];
            const orderDataset =  [];

            this.chart.totalNewUsers = 0;
            this.chart.totalOrders = 0

            for (const key in this.chart?.data) {
                labels.push(key)
                userDataset.push(this.chart.data[key].new_user);
                orderDataset.push(this.chart.data[key].orders);

                this.chart.totalNewUsers += this.chart.data[key].new_user;
                this.chart.totalOrders += this.chart.data[key].orders;
            }

            this.chartData = {
                labels,
                datasets: [
                    {
                        label: 'تعداد کاربران جدید',
                        data: userDataset,
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
                        label: 'تعداد سفارشات',
                        data: orderDataset,
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
            this.getCustomers();
        },
    }))

})