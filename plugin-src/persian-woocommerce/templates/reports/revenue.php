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
wp_enqueue_script('page-script', PW_URL . 'assets/js/pages/revenue.js', [], PW_VERSION, true);

wp_localize_script('global-script', 'PersianWooCommerce', [
	'assetsFolder' => PW_URL . 'assets/',
	'root' => esc_url_raw(rest_url()),
	'nonce' => wp_create_nonce('wp_rest'),
]);
?>

<section x-data="revenue()"  class="woo-report-container">
    <section class="bg-gray-50 text-base py-5">
        <div class="container">

            <div class="mb-6">
                <div class="flex items-center flex-wrap gap-2">
                    <div class="font-semibold text-lg">
                        گزارش درآمد
                    </div>

                    <!-- date -->
                    <div x-cloak x-show="!revenue.loading && !chart.loading && !topSellers.loading && !table.loading" id="rangeDateFilter" class="mr-auto">
                        <div
                                @click="modals.rangeDate.active = true"
                                class="filter-range-date flex items-center border border-gray-300 shadow-[0_1px_2px_0_#1018280D] rounded-lg duration-300 cursor-pointer bg-white hover:bg-primary-50"
                        >
                            <div class="border-l border-gray-300 min-w-9 p-2">
                                <img src="<?php echo PW_URL . 'assets'; ?>/images/icons/calendar.svg">
                            </div>
                            <div class="show-value text-xs font-normal flex items-center gap-2 p-2.5">
                                <template x-if="!revenue.current.filters.from_date && !revenue.current.filters.to_date">
                                    <span class="text-gray-500">انتخاب زمان دلخواه</span>
                                </template>

                                <div class="flex items-center gap-1">
                                    <template x-if="revenue.current.filters.from_date">
                                        <span>
                                            <span class='text-gray-400'>از</span>
                                            <span x-text="pwFormatDate(revenue.current.filters.from_date, 'YYYY/MM/DD')"></span>
                                        </span>
                                    </template>
                                    <template x-if="revenue.current.filters.to_date">
                                        <span>
                                            <span class='text-gray-400'>تا</span>
                                            <span x-text="pwFormatDate(revenue.current.filters.to_date, 'YYYY/MM/DD')"></span>
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

                    <template x-if="revenue.loading || chart.loading || topSellers.loading || table.loading">
                        <div class="skeleton w-56 h-[42px] rounded-md mr-auto"></div>
                    </template>

                </div>
            </div>

            <div class="mb-6">
                <!-- skeleton -->
                <template x-if="revenue.loading">
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

                <div x-show="!revenue.loading">
                    <div class="grid grid-cols-12 gap-6">
                        <div class="xl:col-span-3 sm:col-span-6 col-span-full">
                            <div class="bg-white rounded-lg border border-light-border p-5">
                                <div class="flex items-center text-light-primary text-sm mb-2">
                                    <div class="flex gap-1">
                                        <span>فروش خالص</span>
                                        <button class="tooltip-btn"
                                                tooltip-text="مجموع مبلغ فروش پس از کسر هزینه حمل و نقل، تخفیف‌ها، مرجوعی‌ها و مالیات.">
                                            <img src="<?php echo PW_URL . 'assets'; ?>/images/icons/interface.svg">
                                        </button>
                                    </div>
                                    <div class="size-9 flex items-center justify-center rounded-lg border border-[#D9E1E7] mr-auto">
                                        <img src="<?php echo PW_URL . 'assets'; ?>/images/icons/money.svg"/>
                                    </div>
                                </div>
                                <div class="text-2xl font-bold mb-3">
                                    <span x-text="pwFormatPrice(revenue.current.data?.net_sales)"></span>
                                    <template x-if="revenue.current.data?.net_sales">
                                        <span x-text="(revenue.current.data?.currency || '')"></span>
                                    </template>
                                </div>
                                <div class="flex">
                                    <div
                                            x-html="compare(revenue.previous.data?.net_sales, revenue.current.data?.net_sales)"
                                            class="tooltip-btn"
                                            :tooltip-text="pwFormatPrice(revenue.previous.data?.net_sales) + ' ' + (revenue.current.data?.currency || '')"
                                    ></div>
                                </div>
                            </div>
                        </div>
                        <div class="xl:col-span-3 sm:col-span-6 col-span-full">
                            <div class="bg-white rounded-lg border border-light-border p-5">
                                <div class="flex items-center text-light-primary text-sm mb-2">
                                    <div class="flex gap-1">
                                        <span>تعداد سفارش ثبت شده</span>
                                    </div>
                                    <div class="size-9 flex items-center justify-center rounded-lg border border-[#D9E1E7] mr-auto">
                                        <img src="<?php echo PW_URL . 'assets'; ?>/images/icons/receipt.svg"/>
                                    </div>
                                </div>
                                <div class="text-2xl font-bold mb-3">
                                    <span x-text="pwFormatPrice(revenue.current.data?.order_count)"></span>
                                </div>
                                <div class="flex">
                                    <div
                                            x-html="compare(revenue.previous.data?.order_count, revenue.current.data?.order_count)"
                                            class="tooltip-btn"
                                            :tooltip-text="pwFormatPrice(revenue.previous.data?.order_count)"
                                    ></div>
                                </div>
                            </div>
                        </div>
                        <div class="xl:col-span-3 sm:col-span-6 col-span-full">
                            <div class="bg-white rounded-lg border border-light-border p-5">
                                <div class="flex items-center text-light-primary text-sm mb-2">
                                    <div class="flex gap-1">
                                        <span>میانگین فروش خالص روزانه</span>
                                        <button class="tooltip-btn"
                                                tooltip-text="میانگین فروش خالص ثبت‌شده در بازه‌ی زمانی مشخص شده.">
                                            <img src="<?php echo PW_URL . 'assets'; ?>/images/icons/interface.svg">
                                        </button>
                                    </div>
                                    <div class="size-9 flex items-center justify-center rounded-lg border border-[#D9E1E7] mr-auto">
                                        <img src="<?php echo PW_URL . 'assets'; ?>/images/icons/percentage-square.svg"/>
                                    </div>
                                </div>
                                <div class="text-2xl font-bold mb-3">
                                    <span x-text="pwFormatPrice(revenue.current.data?.avg_daily_net_sales)"></span>
                                    <template x-if="revenue.current.data?.avg_daily_net_sales">
                                        <span x-text="(revenue.current.data?.currency || '')"></span>
                                    </template>
                                </div>
                                <div class="flex">
                                    <div
                                            x-html="compare(revenue.previous.data?.avg_daily_net_sales, revenue.current.data?.avg_daily_net_sales)"
                                            class="tooltip-btn"
                                            :tooltip-text="pwFormatPrice(revenue.previous.data?.avg_daily_net_sales) + ' ' + (revenue.current.data?.currency || '')"
                                    ></div>
                                </div>
                            </div>
                        </div>
                        <div class="xl:col-span-3 sm:col-span-6 col-span-full">
                            <div class="bg-white rounded-lg border border-light-border p-5">
                                <div class="flex items-center text-light-primary text-sm mb-2">
                                    <div class="flex gap-1">
                                        <span>کل هزینه استرداد شده</span>
                                    </div>
                                    <div class="size-9 flex items-center justify-center rounded-lg border border-[#D9E1E7] mr-auto">
                                        <img src="<?php echo PW_URL . 'assets'; ?>/images/icons/user-tick.svg"/>
                                    </div>
                                </div>
                                <div class="text-2xl font-bold mb-3">
                                    <span x-text="pwFormatPrice(revenue.current.data?.refund_total)"></span>
                                    <template x-if="revenue.current.data?.refund_total">
                                        <span x-text="(revenue.current.data?.currency || '')"></span>
                                    </template>
                                </div>
                                <div class="flex">
                                    <div
                                            x-html="compare(revenue.previous.data?.refund_total, revenue.current.data?.refund_total)"
                                            class="tooltip-btn"
                                            :tooltip-text="pwFormatPrice(revenue.previous.data?.refund_total) + ' ' + (revenue.current.data?.currency || '')"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-6">
                <div class="grid grid-cols-12 gap-5">
                    <!-- skeleton -->
                    <template x-if="revenue.loading">
                        <div class="md:col-span-6 col-span-full">
                            <div class="bg-white h-full rounded-lg border border-light-border p-5">
                                <div class="mb-3">
                                    <div class="skeleton h-7 w-32 rounded-full"></div>
                                </div>
                                <div class="skeleton h-8 w-40 rounded-full mb-9"></div>
                                <div class="skeleton h-3 rounded-full mb-8"></div>
                                <div class="grid grid-cols-12 gap-x-2 gap-y-8">
                                    <template x-for="item in [1,2,3,4]">
                                        <div class="xl:col-span-3 sm:col-span-6 col-span-full">
                                            <div class="skeleton h-5 w-24 rounded-full mb-3"></div>
                                            <div class="flex items-center gap-2">
                                                <div class="skeleton h-2 w-3 rounded-full"></div>
                                                <div class="skeleton h-5 w-16 rounded-full"></div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>

                    <template x-if="!revenue.loading">
                        <div class="md:col-span-6 col-span-full">
                            <div class="bg-white h-full rounded-lg border border-light-border p-5">
                                <div class="flex items-center gap-2 mb-3">
                                    <span class="text-dark-primary text-lg font-semibold">کل درآمد (ناخالص)</span>
                                </div>
                                <div class="text-2xl font-bold mb-9">
                                    <span x-text="pwFormatPrice(revenue.current.data?.total_sales)"></span>
                                    <template x-if="revenue.current.data?.total_sales">
                                        <span x-text="(revenue.current.data?.currency || '')"></span>
                                    </template>
                                </div>

                                <template x-if="!revenue.current.data?.total_sales">
                                    <div class="flex gap-[1px] h-3 bg-gray-300 rounded-full overflow-hidden mb-8"></div>
                                </template>

                                <template x-if="revenue.current.data?.total_sales">
                                    <div class="flex gap-[1px] bg-white rounded-full overflow-hidden mb-8">
                                        <div
                                                x-show="revenue.current.data?.net_sales > 0"
                                                :style="`width: ${(revenue.current.data?.net_sales * 100 ) / revenue.current.data?.total_sales}%`"
                                                class="h-3 bg-primary-300"
                                        >
                                        </div>
                                        <div
                                                x-show="revenue.current.data?.shipping_amount > 0"
                                                :style="`width: ${(revenue.current.data?.shipping_amount * 100 ) / revenue.current.data?.total_sales}%`"
                                                class="h-3 bg-warning-300"
                                        >
                                        </div>
                                        <div
                                                x-show="revenue.current.data?.discount_amount > 0"
                                                :style="`width: ${(revenue.current.data?.discount_amount * 100 ) / revenue.current.data?.total_sales}%`"
                                                class="h-3 bg-error-300"
                                        >
                                        </div>
                                        <div
                                                x-show="revenue.current.data?.tax_amount > 0"
                                                :style="`width: ${(revenue.current.data?.tax_amount * 100 ) / revenue.current.data?.total_sales}%`"
                                                class="h-3 bg-indigo-300"
                                        >
                                        </div>
                                    </div>
                                </template>

                                <div class="grid grid-cols-12 gap-x-2 gap-y-8">
                                    <div class="xl:col-span-3 sm:col-span-6 col-span-full">
                                        <div class="text-sm font-bold mb-3">
                                            <span x-text="pwFormatPrice(revenue.current.data?.net_sales)"></span>
                                            <span x-text="(revenue.current.data?.currency || '')"></span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <div class="h-2 w-3 bg-primary-300 rounded-full"></div>
                                            <div class="text-gray-600 text-sm">درآمد خالص</div>
                                        </div>
                                    </div>
                                    <div class="xl:col-span-3 sm:col-span-6 col-span-full">
                                        <div class="text-sm font-bold mb-3">
                                            <span x-text="pwFormatPrice(revenue.current.data?.shipping_amount)"></span>
                                            <span x-text="(revenue.current.data?.currency || '')"></span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <div class="h-2 w-3 bg-warning-300 rounded-full"></div>
                                            <div class="text-gray-600 text-sm">هزینه ارسال </div>
                                        </div>
                                    </div>
                                    <div class="xl:col-span-3 sm:col-span-6 col-span-full">
                                        <div class="text-sm font-bold mb-3">
                                            <span x-text="pwFormatPrice(revenue.current.data?.discount_amount)"></span>
                                            <span x-text="(revenue.current.data?.currency || '')"></span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <div class="h-2 w-3 bg-error-300 rounded-full"></div>
                                            <div class="text-gray-600 text-sm">تخفیف</div>
                                        </div>
                                    </div>
                                    <div class="xl:col-span-3 sm:col-span-6 col-span-full">
                                        <div class="text-sm font-bold mb-3">
                                            <span x-text="pwFormatPrice(revenue.current.data?.tax_amount)"></span>
                                            <span x-text="(revenue.current.data?.currency || '')"></span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <div class="h-2 w-3 bg-indigo-300 rounded-full"></div>
                                            <div class="text-gray-600 text-sm"> مالیات</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- skeleton -->
                    <template x-if="topSellers.loading">
                        <div class="md:col-span-6 col-span-full">
                            <div class="bg-white h-full rounded-lg border border-light-border p-5">
                                <div class="flex items-center gap-2 mb-5">
                                    <div class="skeleton h-7 w-32 rounded-full"></div>
                                </div>

                                <div>
                                    <div class="h-6 skeleton"></div>
                                    <div class="h-6 w-2/3 skeleton"></div>
                                    <div class="h-6 w-1/2 skeleton"></div>
                                    <div class="h-6 w-1/3 skeleton"></div>
                                    <div class="h-6 w-1/4 skeleton"></div>
                                    <div class="h-6 w-1/5 skeleton"></div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <template x-if="!topSellers.loading">
                        <div class="md:col-span-6 col-span-full">
                            <div class="bg-white h-full rounded-lg border border-light-border p-5">
                                <div class="flex flex-wrap self-start gap-2 mb-5">
                                    <div class="flex items-center gap-2">
                                        <span class="text-dark-primary text-lg font-semibold">پرفروش‌ترین </span>
                                    </div>

                                    <div class="flex text-sm border border-gray-300 rounded-lg overflow-hidden mr-auto">
                                        <div
                                                @click="topSellers.filters.type = 'product'"
                                                class="border-l border-gray-300 cursor-pointer py-2 px-3.5"
                                                :class="{'!bg-gray-50': (topSellers.filters.type === 'product')}"
                                        >
                                            محصولات
                                        </div>
                                        <div
                                                @click="topSellers.filters.type = 'category'"
                                                class="cursor-pointer py-2 px-3.5"
                                                :class="{'!bg-gray-50': (topSellers.filters.type === 'category')}"
                                        >
                                            دسته‌بندی‌ها
                                        </div>
                                    </div>
                                </div>

                                <div
                                        x-data="{ width: 0 }"
                                        x-init="
                                            width = $refs.parentLine.offsetWidth;
                                            window.addEventListener('resize', () => {
                                                width = $refs.parentLine.offsetWidth;
                                            });
                                        "
                                        x-ref="parentLine"
                                        class="text-sm text-gray-800"
                                >

                                    <template x-if="topSellers.filters.type === 'product'">
                                        <div>
                                            <template x-if="topSellers.products.items.length === 0">
                                                <div class="text-center">
                                                    <div class="mb-4">
                                                        <img src="<?php echo PW_URL . 'assets'; ?>/images/empty.png" class="block mx-auto">
                                                    </div>
                                                    <div>محصولی برای نمایش وجود ندارد</div>
                                                </div>
                                            </template>
                                            <template x-if="topSellers.products.items.length > 0">
                                                <template x-for="(product, index) in topSellers.products.items">
                                                    <div class="relative z-0 flex gap-1 font-normal py-0.5 px-2">
                                                        <span x-text="product.name"></span>
                                                        <span  x-text="pwFormatPrice(product.count)" class="text-gray-500 mr-auto"></span>

                                                        <div class="absolute right-0 top-0 h-full w-full">
                                                            <div
                                                                    :style="`width: ${(product.count / topSellers.products.max) * 100}%`"
                                                                    class="overflow-hidden"
                                                            >
                                                                <div
                                                                        class="flex gap-1 text-white font-normal z-10 py-0.5 px-2"
                                                                        :class="`bg-primary-${(6 - index) * 100}`"
                                                                        :style="`min-width: ${width}px`"
                                                                >
                                                                    <span x-text="product.name"></span>
                                                                    <span x-text="pwFormatPrice(product.count)" class="mr-auto"></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>
                                            </template>
                                        </div>
                                    </template>

                                    <template x-if="topSellers.filters.type === 'category'">
                                        <div>
                                            <template x-if="topSellers.categories.items.length === 0">
                                                <div class="text-center">
                                                    <div class="mb-4">
                                                        <img src="<?php echo PW_URL . 'assets'; ?>/images/empty.png" class="block mx-auto">
                                                    </div>
                                                    <div>دسته‌بندی برای نمایش وجود ندارد</div>
                                                </div>
                                            </template>
                                            <template x-if="topSellers.categories.items.length > 0">
                                                <template x-for="(category, index) in topSellers.categories.items">
                                                    <div class="relative z-0 flex gap-1 font-normal py-0.5 px-2">
                                                        <span x-text="category.name"></span>
                                                        <span  x-text="pwFormatPrice(category.count)" class="text-gray-500 mr-auto"></span>

                                                        <div class="absolute right-0 top-0 h-full w-full">
                                                            <div
                                                                    :style="`width: ${(category.count / topSellers.categories.max) * 100}%`"
                                                                    class="overflow-hidden"
                                                            >
                                                                <div
                                                                        class="flex gap-1 text-white font-normal z-10 py-0.5 px-2"
                                                                        :class="`bg-primary-${(6 - index) * 100}`"
                                                                        :style="`min-width: ${width}px`"
                                                                >
                                                                    <span x-text="category.name"></span>
                                                                    <span x-text="pwFormatPrice(category.count)" class="mr-auto"></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>
                                            </template>
                                        </div>
                                    </template>

                                    <template x-if="false">
                                        <div class="relative z-0 flex gap-1 font-normal py-0.5 px-2">
                                            محصول یک
                                            <span class="text-gray-500 mr-auto">1,782</span>

                                            <div class="absolute right-0 top-0 h-full w-full">
                                                <div style="width: 65%" class="overflow-hidden">
                                                    <div
                                                            class="flex gap-1 text-white font-normal bg-primary-400 z-10 py-0.5 px-2"
                                                            :style="`min-width: ${width}px`"
                                                    >
                                                        محصول یک
                                                        <span class="mr-auto">1,782</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="relative z-0 flex gap-1 font-normal py-0.5 px-2">
                                            محصول یک
                                            <span class="text-gray-500 mr-auto">1,782</span>

                                            <div class="absolute right-0 top-0 h-full w-full">
                                                <div style="width: 50%" class="overflow-hidden">
                                                    <div
                                                            class="flex gap-1 text-white font-normal bg-primary-300 z-10 py-0.5 px-2"
                                                            :style="`min-width: ${width}px`"
                                                    >
                                                        محصول یک
                                                        <span class="mr-auto">1,782</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="relative z-0 flex gap-1 font-normal py-0.5 px-2">
                                            محصول یک
                                            <span class="text-gray-500 mr-auto">1,782</span>

                                            <div class="absolute right-0 top-0 h-full w-full">
                                                <div style="width: 30%" class="overflow-hidden">
                                                    <div
                                                            class="flex gap-1 font-normal bg-primary-200 z-10 py-0.5 px-2"
                                                            :style="`min-width: ${width}px`"
                                                    >
                                                        محصول یک
                                                        <span class="text-gray-500 mr-auto">1,782</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="relative z-0 flex gap-1 font-normal py-0.5 px-2">
                                            محصول یک
                                            <span class="text-gray-500 mr-auto">1,782</span>

                                            <div class="absolute right-0 top-0 h-full w-full">
                                                <div style="width: 20%" class="overflow-hidden">
                                                    <div
                                                            class="flex gap-1 font-normal bg-primary-100 z-10 py-0.5 px-2"
                                                            :style="`min-width: ${width}px`"
                                                    >
                                                        محصول یک
                                                        <span class="text-gray-500 mr-auto">1,782</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="relative z-0 flex gap-1 font-normal py-0.5 px-2">
                                            محصول یک
                                            <span class="text-gray-500 mr-auto">1,782</span>

                                            <div class="absolute right-0 top-0 h-full w-full">
                                                <div style="width: 10%" class="overflow-hidden">
                                                    <div
                                                            class="flex gap-1 font-normal bg-primary-50 z-10 py-0.5 px-2"
                                                            :style="`min-width: ${width}px`"
                                                    >
                                                        محصول یک
                                                        <span class="text-gray-500 mr-auto">1,782</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>

                                </div>

                            </div>
                        </div>
                    </template>
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
                            <div class="text-dark-primary font-semibold text-lg mb-4">
                                <span x-text="chart.filters.type.label"></span>
                            </div>
                            <div class="flex items-center flex-wrap gap-x-6 gap-y-3">
                                <div class="flex items-center flex-wrap gap-2">
                                    <template x-if="revenue.current.data">
                                        <div class="text-2xl font-bold">
                                            <span x-text="pwFormatPrice(revenue.current.data[chart.filters.type.value])"></span>
                                            <template x-if="revenue.current.data[chart.filters.type.value]">
                                                <span x-text="(revenue.current.data?.currency || '')"></span>
                                            </template>
                                        </div>
                                        <div class="flex">
                                            <div
                                                    x-html="compare(revenue.previous.data[chart.filters.type.value], revenue.current.data[chart.filters.type.value])"
                                                    class="tooltip-btn"
                                                    :tooltip-text="pwFormatPrice(revenue.previous.data[chart.filters.type.value]) + ' ' + (revenue.current.data?.currency || '')"
                                            ></div>
                                        </div>
                                    </template>
                                </div>
                                <div class="flex items-center flex-wrap gap-x-5 gap-y-2">
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 w-3 bg-primary-300 rounded-full"></div>
                                        <div class="flex items-center gap-1 text-sm">
                                            <div>
                                                <span class='text-gray-400'>از</span>
                                                <span x-text="pwFormatDate(revenue.current.filters.from_date, 'YYYY/MM/DD')"></span>
                                            </div>
                                            <div>
                                                <span class='text-gray-400'>تا</span>
                                                <span x-text="pwFormatDate(revenue.current.filters.to_date, 'YYYY/MM/DD')"></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 w-3 bg-error-300 rounded-full"></div>
                                        <div class="flex items-center gap-1 text-sm">
                                            <div>
                                                <span class='text-gray-400'>از</span>
                                                <span x-text="pwFormatDate(revenue.previous.filters.from_date, 'YYYY/MM/DD')"></span>
                                            </div>
                                            <div>
                                                <span class='text-gray-400'>تا</span>
                                                <span x-text="pwFormatDate(revenue.previous.filters.to_date, 'YYYY/MM/DD')"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center flex-wrap gap-2 mr-auto">
                                    <div class="text-sm border border-gray-300 shadow-[0_1px_2px_0_#1018280D] bg-white rounded-lg">
                                        <!--dropdown-->
                                        <div
                                                x-data="{open: false}"
                                                @click.outside="open = false"
                                                class="relative h-full"
                                        >
                                            <!--active value-->
                                            <div
                                                    @click="open = !open"
                                                    class="flex items-center gap-3 cursor-pointer py-2 px-3"
                                            >
                                                <div
                                                        x-text="chart.filters.type.label"
                                                        class="min-w-10 line-clamp-1"
                                                >
                                                </div>

                                                <div
                                                        class="duration-300 mr-auto"
                                                        :class="{'rotate-180' : open}"
                                                >
                                                    <svg width="12" height="8" viewBox="0 0 12 8" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <path d="M1 1.5L6 6.5L11 1.5" stroke="#667085" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round"/>
                                                    </svg>
                                                </div>
                                            </div>

                                            <!-- dropdown items-->
                                            <div
                                                    class="max-h-0 w-[calc(100%+2px)] absolute z-[1] top-[calc(100%+4px)] -left-[1px] border border-gray-200 border-opacity-0 rounded overflow-auto custom-scrollbar duration-300"
                                                    :class="{'!max-h-40 !border-opacity-100 shadow bg-white z-[2]' : open}"
                                            >
                                                <div class="bg-white pt-0.5">
                                                    <template x-for="(item, index) in chart.types">
                                                        <div
                                                                @click="chart.filters.type = item; open = false; drawingChart()"
                                                                class="flex gap-2 items-center font-normal cursor-pointer hover:text-primary-300 duration-300 p-1.5 mx-1"
                                                                :class="{'border-b' : (index+1 !== 4 ), 'text-primary-300': (chart.filters.type.value === item.value)}"
                                                        >
                                                            <span x-text="item.label"></span>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-sm border border-gray-300 shadow-[0_1px_2px_0_#1018280D] bg-white rounded-lg">
                                        <!--dropdown-->
                                        <div
                                                x-data="{open: false}"
                                                @click.outside="open = false"
                                                class="relative h-full"
                                        >
                                            <!--active value-->
                                            <div
                                                    @click="open = !open"
                                                    class="flex items-center gap-3 cursor-pointer py-2 px-3"
                                            >
                                                <div
                                                        x-text="chart.filters.interval.label"
                                                        class="min-w-10 line-clamp-1"
                                                >
                                                </div>

                                                <div
                                                        class="duration-300 mr-auto"
                                                        :class="{'rotate-180' : open}"
                                                >
                                                    <svg width="12" height="8" viewBox="0 0 12 8" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <path d="M1 1.5L6 6.5L11 1.5" stroke="#667085" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round"/>
                                                    </svg>
                                                </div>
                                            </div>

                                            <!-- dropdown items-->
                                            <div
                                                    class="max-h-0 w-[calc(100%+2px)] absolute z-[1] top-[calc(100%+4px)] -left-[1px] border border-gray-200 border-opacity-0 rounded overflow-auto custom-scrollbar duration-300"
                                                    :class="{'!max-h-40 !border-opacity-100 shadow bg-white z-[2]' : open}"
                                            >
                                                <div class="bg-white pt-0.5">
                                                    <template x-for="(item, index) in chart.intervals">
                                                        <div
                                                                @click="chart.filters.interval = item; open = false; getChartData()"
                                                                class="flex gap-2 items-center font-normal cursor-pointer hover:text-primary-300 duration-300 p-1.5 mx-1"
                                                                :class="{'border-b' : (index+1 !== 4 ), 'text-primary-300': (chart.filters.interval.value === item.value)}"
                                                        >
                                                            <span x-text="item.label"></span>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
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
                        درآمد
                    </div>
                </div>

                <div class="overflow-auto custom-scrollbar">
                    <table class="w-full">
                        <thead class="!text-xs text-gray-600 text-nowrap">
                        <tr class="border-y bg-white border-gray-200">
                            <td class="bg-gray-50 py-3 px-5">
                                تاریخ
                            </td>
                            <td class="bg-gray-50 py-3 px-5">
                                سفارش‌ها
                            </td>
                            <td class="bg-gray-50 py-3 px-5">
                                فروش ناخالص
                            </td>
                            <td class="bg-gray-50 py-3 px-5">
                                برگشتی
                            </td>
                            <td class="bg-gray-50 py-3 px-5">
                                کدهای تخفیف
                            </td>
                            <td class="bg-gray-50 py-3 px-5">
                                فروش خالص
                            </td>
                            <td class="bg-gray-50 py-3 px-5">
                                مالیات
                            </td>
                            <td class="bg-gray-50 py-3 px-5">
                                حمل و نقل
                            </td>
                            <td class="bg-gray-50 py-3 px-5">
                                فروش کل
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
                                    <div class="skeleton w-20 h-5 rounded-full"></div>
                                </td>
                                <td class="py-4 md:px-5 px-3">
                                    <div class="skeleton w-20 h-5 rounded-full"></div>
                                </td>
                                <td class="py-4 md:px-5 px-3">
                                    <div class="skeleton w-16 h-5 rounded-full"></div>
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
                            <tr class="border-b bg-white border-gray-200 text-gray-600">
                                <td class="!text-sm py-4 md:px-5 px-3">
                                    <span x-text="row.date"></span>
                                </td>
                                <td class="!text-sm py-4 md:px-5 px-3">
                                    <span x-text="pwFormatPrice(row.order_count)"></span>
                                </td>
                                <td class="!text-sm py-4 md:px-5 px-3">
                                    <span x-text="pwFormatPrice(row.gross_sales) + ' ' + (revenue.current.data?.currency || '')"></span>
                                </td>
                                <td class="!text-sm py-4 md:px-5 px-3">
                                    <span x-text="pwFormatPrice(row.refund_amount) + ' ' + (revenue.current.data?.currency || '')"></span>
                                </td>
                                <td class="!text-sm py-4 md:px-5 px-3">
                                    <span x-text="pwFormatPrice(row.discount_amount) + ' ' + (revenue.current.data?.currency || '')"></span>
                                </td>
                                <td class="!text-sm py-4 md:px-5 px-3">
                                    <span x-text="pwFormatPrice(row.net_sales) + ' ' + (revenue.current.data?.currency || '')"></span>
                                </td>
                                <td class="!text-sm py-4 md:px-5 px-3">
                                    <span x-text="pwFormatPrice(row.tax_amount) + ' ' + (revenue.current.data?.currency || '')"></span>
                                </td>
                                <td class="!text-sm py-4 md:px-5 px-3">
                                    <span x-text="pwFormatPrice(row.shipping_amount) + ' ' + (revenue.current.data?.currency || '')"></span>
                                </td>
                                <td class="!text-sm py-4 md:px-5 px-3">
                                    <span x-text="pwFormatPrice(row.total_sales) + ' ' + (revenue.current.data?.currency || '')"></span>
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
