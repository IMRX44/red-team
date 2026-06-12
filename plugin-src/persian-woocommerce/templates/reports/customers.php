<?php

defined( 'ABSPATH' ) || exit;

wp_enqueue_style('custom-style', PW_URL . 'assets/css/style.css', [], PW_VERSION);
wp_enqueue_style('notyf-style', PW_URL . 'assets/css/notyf.min.css', [], PW_VERSION);
wp_enqueue_style('persian-datepicker-style', PW_URL . 'assets/css/persian-datepicker-2.min.css', [], PW_VERSION);

wp_enqueue_script( 'alpine' );

wp_enqueue_script('chart-script', PW_URL . 'assets/js/chart.js', [], PW_VERSION, true);
wp_enqueue_script('chart-plugin-gradient-script', PW_URL . 'assets/js/chartjs-plugin-gradient.js', ['chart-script'], PW_VERSION, true);

wp_enqueue_script('persian-date-script', PW_URL . 'assets/js/persian-date.min.js', [], PW_VERSION, true);
wp_enqueue_script('persian-datepicker-script', PW_URL . 'assets/js/persian-datepicker-2.min.js', [], PW_VERSION, true);

wp_enqueue_script('popper-script', PW_URL . 'assets/js/popper.min.js', [], PW_VERSION, true);
wp_enqueue_script('tippy-script', PW_URL . 'assets/js/tippy-bundle.umd.min.js', ['popper-script'], PW_VERSION, true);

wp_enqueue_script('notyf-script', PW_URL . 'assets/js/notyf.min.js', [], PW_VERSION, true);

wp_enqueue_script('global-script', PW_URL . 'assets/js/global.js', ['notyf-script', 'tippy-script', 'chart-script'], PW_VERSION, true);
wp_enqueue_script('page-script', PW_URL . 'assets/js/pages/orders.js', [], PW_VERSION, true);

wp_localize_script('global-script', 'PersianWooCommerce', [
	'assetsFolder' => PW_URL . 'assets/',
	'root' => esc_url_raw(rest_url()),
	'nonce' => wp_create_nonce('wp_rest'),
]);
?>

<section x-data="orders()"  class="woo-report-container">
    <section class="bg-gray-50 text-base py-5">
        <div class="container">

            <div class="mb-6">
                <div class="flex items-center flex-wrap gap-2">
                    <div class="font-semibold text-lg">
                        گزارشات مشتریان
                    </div>

                    <!-- date -->
                    <div x-cloak x-show="!orders.loading && !table.loading && !chart.loading" id="rangeDateFilter" class="mr-auto">
                        <div
                                @click="modals.rangeDate.active = true"
                                class="filter-range-date flex items-center border border-gray-300 shadow-[0_1px_2px_0_#1018280D] rounded-lg duration-300 cursor-pointer bg-white hover:bg-primary-50"
                        >
                            <div class="border-l border-gray-300 min-w-9 p-2">
                                <img src="<?php echo PW_URL . 'assets'; ?>/images/icons/calendar.svg">
                            </div>
                            <div class="show-value text-xs font-normal flex items-center gap-2 p-2.5">
                                <template x-if="!orders.current.filters.from_date && !orders.current.filters.to_date">
                                    <span class="text-gray-500">انتخاب زمان دلخواه</span>
                                </template>

                                <div class="flex items-center gap-1">
                                    <template x-if="orders.current.filters.from_date">
                                        <span>
                                            <span class='text-gray-400'>از</span>
                                            <span x-text="pwFormatDate(orders.current.filters.from_date, 'YYYY/MM/DD')"></span>
                                        </span>
                                    </template>
                                    <template x-if="orders.current.filters.to_date">
                                        <span>
                                            <span class='text-gray-400'>تا</span>
                                            <span x-text="pwFormatDate(orders.current.filters.to_date, 'YYYY/MM/DD')"></span>
                                        </span>
                                    </template>
                                </div>

                                <div class="bg-gray-100 rounded-2xl py-1 px-2">
                                    <span class="text-gray-400">مقایسه: </span>
                                    <template x-if="date.comparison === 'period'">
                                        <span> دوره قبلی</span>
                                    </template>
                                    <template x-if="date.comparison === 'year'">
                                        <span> پارسال</span>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <!-- Modal Range Date -->
                        <div
                                x-transition
                                x-cloak
                                class="fixed top-0 left-0 z-[99999] flex items-center justify-center w-full h-full overflow-auto custom-scrollbar p-4"
                                x-show="modals.rangeDate.active"
                        >
                            <!-- overlay -->
                            <div
                                    @click="modals.rangeDate.active = false"
                                    class="fixed z-10 top-0 left-0 w-full h-full bg-black bg-opacity-50 cursor-pointer"
                            ></div>

                            <!-- body modal -->
                            <div class="modal bg-white w-[500px] max-w-full z-20 rounded-xl py-5 my-auto">
                                <div class="flex justify-between gap-2">
                                    <div class="text-xl px-5 mb-5">
                                        فیلتر زمانی
                                    </div>
                                    <button
                                            @click="clearDateFilter(); modals.rangeDate.active = false"
                                            class="text-primary-500 text-sm border-b border-transparent hover:text-error-300 px-5 pb-2 mr-auto"
                                    >
                                        پاک کردن
                                    </button>
                                </div>
                                <div class="flex text-sm text-center mb-2">
                                    <div
                                            @click="date.type = 'quick'"
                                            class="w-1/2 text-gray-500 border-b border-gray-100 cursor-pointer duration-300 px-5 pb-2"
                                            :class="{'!border-primary-500 text-primary-500': (date.type === 'quick')}"
                                    >
                                        انتخاب سریع
                                    </div>
                                    <div
                                            @click="date.type = 'range'"
                                            class="w-1/2 text-gray-500 border-b border-gray-100 cursor-pointer duration-300 px-5 pb-2"
                                            :class="{'!border-primary-500 text-primary-500': (date.type === 'range')}"
                                    >
                                        انتخاب تاریخ
                                    </div>
                                </div>

                                <!-- Range select -->
                                <div x-show="date.type === 'range'">
                                    <div class="pt-5 px-5">
                                        <div class="range-date grid grid-cols-12 gap-5 mb-5">
                                            <div class="md:col-span-6 col-span-full">
                                                <div class="text-sm text-center font-semibold text-gray-700 border-b border-gray-200 mb-2 pb-2">
                                                    انتخاب تاریخ شروع
                                                </div>
                                                <div class="range-date-from"></div>
                                                <input class="range-date-from-alt hidden" disabled value="1403-09-21">
                                            </div>
                                            <div class="md:col-span-6 col-span-full">
                                                <div class="text-sm text-center font-semibold text-gray-700 border-b border-gray-200 mb-2 pb-2">
                                                    انتخاب تاریخ پایان
                                                </div>
                                                <div class="range-date-to"></div>
                                                <input class="range-date-to-alt hidden" disabled value="1403-09-28">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Quick select-->
                                <div x-show="date.type === 'quick'">
                                    <div class="text-center border-b border-gray-100 text-sm px-5 p-4">
                                        <div class="grid grid-cols-12 gap-4">
                                            <template x-for="item in date.quick.items">
                                                <div class="col-span-6">
                                                    <button
                                                            @click="date.quick.selected = item;  selectDateFilter(item.from, item.to)"
                                                            class="w-full flex gap-2 justify-center items-center border border-gray-300 rounded-lg py-2 px-3"
                                                            :class="{'bg-primary-50 border-primary-600 text-primary-700': (date.quick.selected?.type === item.type)}"
                                                    >
                                                        <span
                                                                x-show="date.quick.selected?.type === item.type"
                                                                class="size-2 inline-block rounded-full bg-primary-500"
                                                        >
                                                        </span>
                                                        <span x-text="item.label"></span>
                                                    </button>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                <div class="text-center border-b border-gray-100 text-sm px-5 p-3 mb-6">
                                    <div class="text-[#171717] mb-2">مقایسه با</div>
                                    <div class="flex gap-4">
                                        <button
                                                @click="date.comparison = 'period'"
                                                class="w-1/2 flex gap-2 justify-center items-center border border-gray-300 rounded-lg py-2 px-3"
                                                :class="{'bg-primary-50 border-primary-600 text-primary-700': (date.comparison === 'period')}"
                                        >
                                            <span
                                                    x-show="date.comparison === 'period'"
                                                    class="size-2 inline-block rounded-full bg-primary-500"
                                            >
                                            </span>
                                            دوره قبلی
                                        </button>
                                        <button
                                                @click="date.comparison = 'year'"
                                                class="w-1/2 flex gap-2 justify-center items-center border border-gray-300 rounded-lg py-2 px-3"
                                                :class="{'bg-primary-50 border-primary-600 text-primary-700': (date.comparison === 'year')}"
                                        >
                                            <span
                                                    x-show="date.comparison === 'year'"
                                                    class="size-2 inline-block rounded-full bg-primary-500"
                                            >
                                            </span>
                                            پارسال
                                        </button>
                                    </div>
                                </div>

                                <div class="flex items-center justify-center gap-3 px-5">
                                    <button
                                            @click="modals.rangeDate.active = false"
                                            class="w-1/2 border border-gray-300 text-gray-700 font-semibold rounded-lg hover:shadow py-2"
                                    >
                                        انصراف
                                    </button>
                                    <button
                                            @click="setDateFilter(); modals.rangeDate.active = false"
                                            class="w-1/2 border bg-primary-600 border-primary-600 text-white font-semibold rounded-lg hover:shadow  py-2"
                                    >
                                        اعمال تغییرات
                                    </button>
                                </div>
                            </div>
                        </div>

                    </div>

                    <template x-if="orders.loading || table.loading || chart.loading">
                        <div class="skeleton w-56 h-[42px] rounded-md mr-auto"></div>
                    </template>

                </div>
            </div>

            <div class="mb-6">
                <!-- skeleton -->
                <template x-if="orders.loading">
                    <div class="grid grid-cols-12 gap-6">
                        <template x-for="item in [1, 2, 3, 4]">
                            <div :key="item" class="xl:col-span-3 sm:col-span-6 col-span-full">
                                <div class="bg-white rounded-lg border border-light-border p-5">
                                    <div class="flex items-center text-light-primary text-sm mb-2">
                                        <div class="skeleton w-24 h-5 rounded-full"></div>
                                        <div class="skeleton size-9 rounded-lg mr-auto"></div>
                                    </div>
                                    <div class="skeleton w-40 max-w-full h-8 rounded-lg mb-3"></div>
                                    <div class="flex">
                                        <div class="skeleton w-20 h-6 rounded-md"></div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                <div x-show="!orders.loading">
                    <div class="grid grid-cols-12 gap-6">
                        <div class="xl:col-span-3 sm:col-span-6 col-span-full">
                            <div class="bg-white rounded-lg border border-light-border p-5">
                                <div class="flex items-center text-light-primary text-sm mb-2">
                                    <div class="flex gap-1">
                                        <span>تعداد مشتریان</span>
                                    </div>
                                    <div class="size-9 flex items-center justify-center rounded-lg border border-[#D9E1E7] mr-auto">
                                        <img src="<?php echo PW_URL . 'assets'; ?>/images/icons/people.svg"/>
                                    </div>
                                </div>
                                <div class="text-2xl font-bold mb-3">
                                    <span x-text="pwFormatPrice(orders.current.data?.total_customers)"></span>
                                </div>
                                <div class="flex">
                                    <div
                                            x-html="compare(orders.previous.data?.total_customers, orders.current.data?.total_customers)"
                                            class="tooltip-btn"
                                            :tooltip-text="pwFormatPrice(orders.previous.data?.total_customers)"
                                    ></div>
                                </div>
                            </div>
                        </div>
                        <div class="xl:col-span-3 sm:col-span-6 col-span-full">
                            <div class="bg-white rounded-lg border border-light-border p-5">
                                <div class="flex items-center text-light-primary text-sm mb-2">
                                    <div class="flex gap-1">
                                        <span>نرخ مشتریان فعال</span>
                                        <button class="tooltip-btn"
                                                tooltip-text="درصد مشتریانی که در بازه زمانی انتخاب‌شده خرید انجام داده‌اند.">
                                            <img src="<?php echo PW_URL . 'assets'; ?>/images/icons/interface.svg">
                                        </button>
                                    </div>
                                    <div class="size-9 flex items-center justify-center rounded-lg border border-[#D9E1E7] mr-auto">
                                        <img src="<?php echo PW_URL . 'assets'; ?>/images/icons/money-time.svg"/>
                                    </div>
                                </div>
                                <div class="text-2xl font-bold mb-3">
                                    <span x-text="pwFormatPrice(orders.current.data?.active_customers)"></span>
                                </div>
                                <div class="flex">
                                    <div
                                            x-html="compare(orders.previous.data?.active_customers, orders.current.data?.active_customers)"
                                            class="tooltip-btn"
                                            :tooltip-text="pwFormatPrice(orders.previous.data?.active_customers)"
                                    ></div>
                                </div>
                            </div>
                        </div>
                        <div class="xl:col-span-3 sm:col-span-6 col-span-full">
                            <div class="bg-white rounded-lg border border-light-border p-5">
                                <div class="flex items-center text-light-primary text-sm mb-2">
                                    <div class="flex gap-1">
                                        <span>میانگین تعداد سفارش‌ کاربرها</span>
                                        <button class="tooltip-btn"
                                                tooltip-text="میانگین تعداد سفارش‌ها برای هر مشتری در بازه‌ی زمانی مشخص. شاخصی برای سنجش میزان تکرار خرید.">
                                            <img src="<?php echo PW_URL . 'assets'; ?>/images/icons/interface.svg">
                                        </button>
                                    </div>
                                    <div class="size-9 flex items-center justify-center rounded-lg border border-[#D9E1E7] mr-auto">
                                        <img src="<?php echo PW_URL . 'assets'; ?>/images/icons/bag-tick.svg"/>
                                    </div>
                                </div>
                                <div class="text-2xl font-bold mb-3">
                                    <span x-text="pwFormatPrice(orders.current.data?.avg_customer_orders)"></span>
                                </div>
                                <div class="flex">
                                    <div
                                            x-html="compare(orders.previous.data?.avg_customer_orders, orders.current.data?.avg_customer_orders)"
                                            class="tooltip-btn"
                                            :tooltip-text="pwFormatPrice(orders.previous.data?.avg_customer_orders)"
                                    ></div>
                                </div>
                            </div>
                        </div>
                        <div class="xl:col-span-3 sm:col-span-6 col-span-full">
                            <div class="bg-white rounded-lg border border-light-border p-5">
                                <div class="flex items-center text-light-primary text-sm mb-2">
                                    <div class="flex gap-1">
                                        <span>تعداد کاربر جدید</span>
                                    </div>
                                    <div class="size-9 flex items-center justify-center rounded-lg border border-[#D9E1E7] mr-auto">
                                        <img src="<?php echo PW_URL . 'assets'; ?>/images/icons/user-tick.svg"/>
                                    </div>
                                </div>
                                <div class="text-2xl font-bold mb-3">
                                    <span x-text="pwFormatPrice(orders.current.data?.new_customers)"></span>
                                </div>
                                <div class="flex">
                                    <div
                                            x-html="compare(orders.previous.data?.new_customers, orders.current.data?.new_customers)"
                                            class="tooltip-btn"
                                            :tooltip-text="pwFormatPrice(orders.previous.data?.new_customers)"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-6">
                <!-- skeleton -->
                <template x-if="chart.loading">
                    <div class="bg-white border border-light-border rounded-lg p-5">
                        <div class="mb-5">
                            <div class="skeleton h-7 w-48 max-w-full rounded-lg mb-4"></div>
                            <div class="flex items-center gap-6">
                                <template x-for="item in [1, 2]">
                                    <div :key="item" class="skeleton h-6 w-56 max-w-full rounded-lg"></div>
                                </template>
                            </div>
                        </div>
                        <div class="h-[400px] skeleton rounded-lg"></div>
                    </div>
                </template>

                <div x-show="!chart.loading">
                    <div class="bg-white border border-light-border rounded-lg p-5">
                        <div class="mb-5">
                            <div class="text-dark-primary font-semibold text-lg mb-4">تعداد کاربران جدید و سفارشات</div>
                            <div class="flex items-center gap-6">
                                <div class="flex items-center gap-2">
                                    <div class="h-2 w-3 bg-primary-300 rounded-full"></div>
                                    <div class="text-gray-600 text-sm">تعداد کاربران جدید</div>
                                    <div class="text-bold">
                                        <span x-text="pwFormatPrice(chart.totalNewUsers)"></span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="h-2 w-3 bg-error-300 rounded-full"></div>
                                    <div class="text-gray-600 text-sm">تعداد سفارشات</div>
                                    <div class="text-bold">
                                        <span x-text="pwFormatPrice(chart.totalOrders)"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="h-[400px]" id="chart">
                            <canvas></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!--table-->
            <div class="bg-white border border-light-border rounded-xl overflow-hidden mb-5">

                <div class="flex items-center flex-wrap gap-3 p-4">
                    <div class="text-lg text-gray-900 font-semibold order-first ml-auto">
                        لیست مشتری
                    </div>
                </div>

                <div class="overflow-auto custom-scrollbar">
                    <table class="w-full">
                        <thead class="!text-xs text-gray-600 text-nowrap">
                        <tr class="border-y bg-white border-gray-200">
                            <td class="bg-gray-50 py-3 px-5">
                                نام و نام خانوادگی
                            </td>
                            <td class="bg-gray-50 py-3 px-5">
                                نام کاربری
                            </td>
                            <td class="bg-gray-50 py-3 px-5">
                                ایمیل
                            </td>
                            <td class="bg-gray-50 py-3 px-5">
                                استان و شهر
                            </td>
                            <td class="bg-gray-50 py-3 px-5">
                                سفارش‌ها
                            </td>
                            <td class="bg-gray-50 py-3 px-5">
                                مجموع هزینه سفارش‌ها
                            </td>
                            <td class="bg-gray-50 py-3 px-5">
                                آخرین سفارش
                            </td>
                        </tr>
                        </thead>

                        <tbody x-show="table.loading" class="w-full text-sm text-gray-700">
                        <template x-for="row in [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]">
                            <tr class="border-b bg-white border-gray-200">
                                <td class="py-4 md:px-5 px-3">
                                    <div class="skeleton w-20 h-5 rounded-full"></div>
                                </td>
                                <td class="py-4 md:px-5 px-3">
                                    <div class="skeleton w-16 h-5 rounded-full"></div>
                                </td>
                                <td class="py-4 md:px-5 px-3">
                                    <div class="skeleton w-32 h-5 rounded-full"></div>
                                </td>
                                <td class="py-4 md:px-5 px-3">
                                    <div class="skeleton w-20 h-5 rounded-full"></div>
                                </td>
                                <td class="py-4 md:px-5 px-3">
                                    <div class="skeleton w-7 h-5 rounded-full"></div>
                                </td>
                                <td class="py-4 md:px-5 px-3">
                                    <div class="skeleton w-20 h-5 rounded-full"></div>
                                </td>
                                <td class="py-4 md:px-5 px-3">
                                    <div class="skeleton w-20 h-5 rounded-full"></div>
                                </td>
                            </tr>
                        </template>
                        </tbody>

                        <tbody
                                x-show="!table.loading && table.data.length > 0"
                                class="w-full text-sm text-gray-700"
                        >
                        <template x-for="row in table.data">
                            <tr class="border-b bg-white border-gray-200">
                                <td class="!text-sm py-4 md:px-5 px-3">
                                    <span x-text="row.name + ' ' + row.last_name"></span>
                                </td>
                                <td class="!text-sm py-4 md:px-5 px-3">
                                    <span x-text="row.user_id"></span>
                                </td>
                                <td class="!text-sm py-4 md:px-5 px-3">
                                    <span x-text="row.email"></span>
                                </td>
                                <td class="!text-sm py-4 md:px-5 px-3">
                                    <span x-text="row.province_city"></span>
                                </td>
                                <td class="!text-sm py-4 md:px-5 px-3">
                                    <span x-text="row.orders"></span>
                                </td>
                                <td class="!text-sm py-4 md:px-5 px-3">
                                    <span x-text="pwFormatPrice(row.total_spent)"></span>
                                </td>
                                <td class="!text-sm py-4 md:px-5 px-3">
                                    <span dir="ltr" x-text="row.last_order_date"></span>
                                </td>
                            </tr>
                        </template>
                        </tbody>
                    </table>
                </div>

                <div
                        x-show="!table.loading && table.data.length < 1"
                        x-cloak
                        class="flex flex-col items-center justify-center text-center py-14 px-8"
                >
                    <div class="mb-4">
                        <img src="<?php echo PW_URL . 'assets'; ?>/images/empty.png" class="block mx-auto">
                    </div>
                    <div>مشتری برای نمایش وجود ندارد</div>
                </div>

                <!-- table pagination -->
                <div
                        x-cloak
                        x-show="table.data.length > 0"
                        class="flex items-center justify-end flex-wrap gap-1.5 text-sm text-gray-600 font-normal p-4"
                >

                    <!-- next page -->
                    <button
                            @click="changePage(table.pagination.currentPage - 1)"
                            class="sm:size-7 size-6 flex items-center justify-center border border-gray-200 hover:bg-gray-100 rounded-md rotate-180 disabled:opacity-50"
                            :disabled="((table.pagination.totalPage - (table.pagination.totalPage - 1)) === table.pagination.currentPage)"
                    >
                        <img src="<?php echo PW_URL . 'assets'; ?>/images/icons/perv.svg">
                    </button>

                    <template x-for="(pageNumber, index) in table.pagination.items">
                        <div>
                            <template x-if="pageNumber !== '...'">
                                <button
                                        @click="changePage(pageNumber)"
                                        class="sm:h-7 h-6 sm:min-w-7 min-w-6 flex items-center justify-center border border-gray-200 hover:bg-gray-100 rounded-md leading-none pt-0.5 px-1"
                                        :class="{'border-primary-500 text-primary-500' : (pageNumber === table.pagination.currentPage)}"
                                >
                                    <span x-text="pageNumber"></span>
                                </button>
                            </template>
                            <template x-if="pageNumber === '...'">
                                <span>...</span>
                            </template>
                        </div>
                    </template>

                    <!-- prev page -->
                    <button
                            @click="changePage(table.pagination.currentPage + 1)"
                            class="sm:size-7 size-6 flex items-center justify-center border border-gray-200 hover:bg-gray-100 rounded-md disabled:opacity-50"
                            :disabled="(table.pagination.totalPage === table.pagination.currentPage)"
                    >
                        <img src="<?php echo PW_URL . 'assets'; ?>/images/icons/perv.svg">
                    </button>

                </div>

            </div>

        </div>
    </section>
</section>
