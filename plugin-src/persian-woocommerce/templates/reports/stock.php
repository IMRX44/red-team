<?php

defined( 'ABSPATH' ) || exit;

wp_enqueue_style('custom-style', PW_URL . 'assets/css/style.css', [], PW_VERSION);

wp_enqueue_style('notyf-style', PW_URL . 'assets/css/notyf.min.css', [], PW_VERSION);

wp_enqueue_script('popper-script', PW_URL . 'assets/js/popper.min.js', [], PW_VERSION, true);
wp_enqueue_script('tippy-script', PW_URL . 'assets/js/tippy-bundle.umd.min.js', ['popper-script'], PW_VERSION, true);

wp_enqueue_script( 'alpine' );
wp_enqueue_script('notyf-script', PW_URL . 'assets/js/notyf.min.js', [], PW_VERSION, true);
wp_enqueue_script('global-script', PW_URL . 'assets/js/global.js', ['notyf-script'], PW_VERSION, true);
wp_enqueue_script('page-script', PW_URL . 'assets/js/pages/stock.js', [], PW_VERSION, true);

wp_localize_script('global-script', 'PersianWooCommerce', [
	'assetsFolder' => PW_URL . 'assets/',
	'root' => esc_url_raw(rest_url()),
	'nonce' => wp_create_nonce('wp_rest'),
]);
?>

<section x-data="stock()" class="woo-report-container">
	<section
		class="hidden page-loader fixed top-0 left-0 h-full w-full z-10 bg-gray-300 bg-opacity-90 items-center justify-center p-4"
	>
		<span class="loader"></span>
	</section>

	<section class="bg-gray-50 text-base py-5">
		<div class="container">

			<div class="mb-6">
				<div class="flex flex-wrap gap-2">
					<div class="font-semibold text-gray-900 text-lg">
						گزارشات انبار
					</div>
				</div>
			</div>

			<div class="mb-6">
				<!-- skeleton -->
				<template x-if="stock.loading">
					<div class="grid grid-cols-12 gap-6">
						<template x-for="item in [1, 2, 3, 4]">
							<div :key="item" class="xl:col-span-3 sm:col-span-6 col-span-full">
								<div class="bg-white rounded-lg border border-light-border p-5">
									<div class="flex items-center text-light-primary text-sm mb-2">
										<div class="skeleton w-24 h-5 rounded-full"></div>
										<div class="skeleton size-9 rounded-lg mr-auto"></div>
									</div>
									<div class="skeleton w-40 max-w-full h-8 rounded-lg"></div>
								</div>
							</div>
						</template>
					</div>
				</template>

				<div x-show="!stock.loading">
					<div class="grid grid-cols-12 gap-6">
						<div class="xl:col-span-3 sm:col-span-6 col-span-full">
							<div class="bg-white h-full rounded-lg border border-light-border p-5">
								<div class="flex items-center text-light-primary text-sm mb-2">
									<div class="flex gap-1">
										<span>تعداد محصولات در انبار</span>
									</div>
									<div class="size-9 flex items-center justify-center rounded-lg border border-[#D9E1E7] mr-auto">
										<img src="<?php echo PW_URL . 'assets'; ?>/images/icons/box.svg"/>
									</div>
								</div>
								<div class="text-2xl font-bold">
                                    <span x-text="pwFormatPrice(stock.data?.instock_count)"></span>
								</div>
							</div>
						</div>
						<div class="xl:col-span-3 sm:col-span-6 col-span-full">
							<div class="bg-white h-full rounded-lg border border-light-border p-5">
								<div class="flex items-center text-light-primary text-sm mb-2">
									<div class="flex gap-1">
										<span>تنوع محصولات در انبار</span>
                                        <button class="tooltip-btn"
                                                tooltip-text="تعداد محصولات منحصربه‌فردی که در حال حاضر در انبار موجود هستند. این شاخص نشان‌دهنده‌ی گستردگی تنوع انتخاب برای مشتریان است.">
                                            <img src="<?php echo PW_URL . 'assets'; ?>/images/icons/interface.svg">
                                        </button>
									</div>
									<div class="size-9 flex items-center justify-center rounded-lg border border-[#D9E1E7] mr-auto">
										<img src="<?php echo PW_URL . 'assets'; ?>/images/icons/box-2.svg"/>
									</div>
								</div>
								<div class="text-2xl font-bold">
                                    <span x-text="pwFormatPrice(stock.data?.total_stock_units)"></span>
								</div>
							</div>
						</div>
						<div class="xl:col-span-3 sm:col-span-6 col-span-full">
							<div class="bg-white h-full rounded-lg border border-light-border p-5">
								<div class="flex items-center text-light-primary text-sm mb-2">
									<div class="flex gap-1">
										<span>ارزش محصولات در انبار </span>
                                        <button class="tooltip-btn"
                                                tooltip-text="مجموع ارزش ریالی تمام محصولات موجود در انبار بر اساس قیمت فعلی آن‌ها.">
                                            <img src="<?php echo PW_URL . 'assets'; ?>/images/icons/interface.svg">
                                        </button>
									</div>
									<div class="size-9 flex items-center justify-center rounded-lg border border-[#D9E1E7] mr-auto">
										<img src="<?php echo PW_URL . 'assets'; ?>/images/icons/money-change.svg"/>
									</div>
								</div>
								<div class="text-2xl font-bold">
                                    <span x-text="pwFormatPrice(stock.data?.total_stock_value)"></span>
                                    <template x-if="stock.data?.total_stock_value">
                                        <span x-text="(stock.data?.currency || '')"></span>
                                    </template>
								</div>
							</div>
						</div>
						<div class="xl:col-span-3 sm:col-span-6 col-span-full">
							<div class="bg-white h-full rounded-lg border border-light-border p-5">
								<div class="flex items-center text-light-primary text-sm mb-2">
									<div class="flex gap-1">
										<span>تعداد محصولات کمبود موجودی</span>
									</div>
									<div class="size-9 flex items-center justify-center rounded-lg border border-[#D9E1E7] mr-auto">
										<img src="<?php echo PW_URL . 'assets'; ?>/images/icons/box-time.svg"/>
									</div>
								</div>
								<div class="text-2xl font-bold">
                                    <span x-text="pwFormatPrice(stock.data?.lowstock_count)"></span>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!--table-->
			<div class="bg-white border border-light-border rounded-xl overflow-hidden mb-5">

				<div class="flex items-center flex-wrap gap-3 p-4">
					<div class="text-lg text-gray-900 font-semibold order-first ml-auto">
						وضعیت انبار
					</div>
				</div>

				<div class="border-y border-gray-200 py-3 px-4">
					<!--skeleton-->
					<template x-if="table.loading">
						<div class="inline-flex md:gap-0 gap-2 max-w-full md:border md:rounded-[8px] text-sm text-nowrap font-semibold md:overflow-hidden overflow-auto hidden-scrollbar">
							<template x-for="item in [1,2,3,4,5]">
								<div class="skeleton w-32 h-10 md:rounded-none rounded-full"></div>
							</template>
						</div>
					</template>

					<template x-if="!table.loading">
						<div class="inline-flex md:gap-0 gap-2 max-w-full md:border md:rounded-[8px] text-sm text-nowrap font-semibold md:overflow-hidden overflow-auto hidden-scrollbar">
							<template x-for="(item, index) in statuses">
								<button
									@click="table.filters.status = item.key; getProducts()"
									class="flex items-center gap-2 md:border-0  border border-gray-300 hover:bg-gray-100 md:rounded-none rounded-full md:py-2.5 py-2 md:px-4 px-3.5"
									:class="{'md:!border-l': (index < statuses.length -1), 'bg-gray-100' : (table.filters.status === item.key)}"
								>
									<span x-text="item.value"></span>
									<span
										x-text="item.count"
										class="bg-primary-50 text-primary-700 rounded-full text-xs py-0.5 px-2"
									>
                                </span>
								</button>
							</template>
						</div>
					</template>
				</div>

				<div class="overflow-auto custom-scrollbar">
					<table class="w-full">
						<thead class="!text-xs text-gray-600 text-nowrap">
						<tr class="border-y bg-white border-gray-200">
							<td class="bg-gray-50 py-3 px-5">
								نام محصول - متغیر
							</td>
							<td class="bg-gray-50 py-3 px-5">
								شناسه محصول
							</td>
							<td class="bg-gray-50 py-3 px-5">
								وضعیت
							</td>
							<td class="bg-gray-50 py-3 px-5">
								موجودی انبار
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
                                    <div class="flex items-center gap-3">
                                        <a
                                            :href="row.edit_url"
                                            target="_blank"
                                        >
                                            <span x-text="row.name"></span>
                                        </a>
                                        <a
                                            :href="row.permalink"
                                            target="_blank"
                                        >
                                            <img src="<?php echo PW_URL . 'assets'; ?>/images/icons/link-external.svg"/>
                                        </a>
                                    </div>
								</td>
								<td class="!text-sm py-4 md:px-5 px-3">
									<span x-text="row.id"></span>
								</td>
								<td class="!text-sm py-4 md:px-5 px-3">
									<template x-if="row.status === 'instock'">
										<div class="inline-block rounded-full bg-success-50 text-xs text-nowrap text-success-700 px-2 py-1">
											موجود
										</div>
									</template>
									<template x-if="row.status === 'outofstock'">
										<div class="inline-block rounded-full bg-error-50 text-xs text-nowrap text-error-700 px-2 py-1">
											ناموجود
										</div>
									</template>
									<template x-if="row.status === 'lowstock'">
										<div class="inline-block rounded-full bg-warning-50 text-xs text-nowrap text-warning-700 px-2 py-1">
											کمبود موجودی
										</div>
									</template>
									<template x-if="row.status === 'onbackorder'">
										<div class="inline-block rounded-full bg-blue-50 text-xs text-nowrap text-blue-700 px-2 py-1">
											در پیش خرید
										</div>
									</template>
									<template x-if="!row.status">
										<div class="inline-block rounded-full bg-gray-100 text-xs text-nowrap text-gray-700 px-2 py-1">
											نامشخص
										</div>
									</template>
								</td>
								<td class="!text-sm py-4 md:px-5 px-3">
									<template x-if="row.stock_count">
										<span x-text="row.stock_count"></span>
									</template>
									<template x-if="!row.stock_count">
										<span>نامعلوم</span>
									</template>
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
                    <div>محصولی برای نمایش وجود ندارد</div>
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