@extends('themes.xylo.layouts.master')
@section('content')
    @php $currency = activeCurrency(); @endphp
    {{-- Banner Section Start --}}
    <section class="banner-area py-5 animate__animated animate__fadeIn">
        <div class="container h-100 banner-slider">
            @foreach ($banners as $banner)
            <div>
                <div class="row h-100 align-items-center">
                    <div class="col-md-6">
                        <h1 class="mt-5"><span>{{ $banner->translation ? $banner->translation->title : $banner->title }}</span>
                        </h1>
                        <p class="mt-3 mb-4">{{ __('store.home.banner_text') }}</p>
                       <a href="{{ route('shop.index') }}" class="btn btn-primary">{{ __('store.home.shop_now') }}</a>

                        <div class="mt-5">
                            <img src="assets/images/slide-smallimages.png" alt="" style="width: 200px;">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="rightimg-banner rightimg-banner1">
                            <img src="{{ Storage::url(optional($banner->translation)->image_url ?? 'default.jpg') }}" class="img-fluid shoes-img" alt="{{ $banner->translation ? $banner->translation->title : $banner->title }}">
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </section>
    {{-- Banner Section End --}}
    <section class="cat-slider animate-on-scroll">
        <div class="container">
            <h2 class="text-start pb-5 sec-heading">{{ __('store.home.explore_popular_categories') }}</h2>
            <div class="category-slider">
                @foreach($categories as $category)
                <div>
                    <div class="cat-card">
                        <a href="{{ route('category.show', $category->slug) }}">
                            <h3>{{ $category->translation->name ?? 'No Translation' }}</h3>
                            <div class="catcard-img">
                                <img src="{{ Storage::url(optional($category->translation)->image_url ?? 'default.jpg') }}" alt="{{ $category->translation->name ?? 'No Translation' }}">
                            </div>
                        </a>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="trending-products animate-on-scroll">
        <div class="container position-relative">
            <h1 class="text-start pb-5 sec-heading">{{ __('store.home.trending_products') }}</h1>

            <div class="product-slider">
                @foreach ($products as $product)
                    <div class="product-card">
                        <div class="product-img">
                            <img src="{{ Storage::url(optional($product->thumbnail)->image_url ?? 'default.jpg') }}" 
                                alt="{{ $product->translation->name ?? 'Product Name Not Available' }}">
                                <button class="wishlist-btn" data-product-id="{{ $product->id }}">
                                    <i class="fa-solid fa-heart"></i>
                                </button>
                        </div>
                        <div class="product-info mt-4">
                            <div class="top-info">
                                <div class="reviews">
                                    <i class="fa-solid fa-star"></i> ({{ $product->reviews_count }} {{ __('store.home.reviews') }})
                                </div>
                            </div>
                            <div class="bottom-info">
                                <div class="left">
                                    <h3>
                                        <a href="{{ route('product.show', $product->slug) }}" class="product-title">
                                            {{ $product->translation->name ?? 'Product Name Not Available' }}
                                        </a>
                                    </h3>
                                    <p class="price">
                                        <span class="original {{ optional($product->primaryVariant)->converted_discount_price ? 'has-discount' : '' }}">
                                            {{ $currency->symbol }}{{ optional($product->primaryVariant)->converted_price ?? 'N/A' }}
                                        </span>

                                        @if(optional($product->primaryVariant)->converted_discount_price)
                                            <span class="discount"> 
                                                {{ $currency->symbol }}{{ $product->primaryVariant->converted_discount_price }}
                                            </span>
                                        @endif
                                    </p>
                                </div>
                                <button class="cart-btn" onclick="addToCart({{ $product->id }})">
                                    <i class="fa fa-shopping-bag"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="custom-arrows">
                <button class="prev"><i class="fa-solid fa-chevron-left"></i></button>
                <button class="next"><i class="fa-solid fa-chevron-right"></i></button>
            </div>
        </div>
    </section>

    {{-- Vendor Performance --}}
    <section class="vendor-performance py-5 animate-on-scroll">
        <div class="container">
            <div class="text-center mb-5">
                <h1 class="sec-heading mb-2">Top Vendors</h1>
                <p class="text-muted mb-0">Discover the vendors performing best across the marketplace.</p>
            </div>

            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="vendor-ranking-card h-100">
                        <div class="vendor-ranking-header">
                            <div>
                                <span class="vendor-ranking-kicker">SALES</span>
                                <h3>Best Selling Vendors</h3>
                            </div>
                            <i class="fa-solid fa-chart-line"></i>
                        </div>
                        @forelse($bestSellingVendors as $vendor)
                            <a href="{{ route('vendors.profile', $vendor->pseudonym) }}" class="vendor-ranking-row">
                                <span class="vendor-rank">{{ $loop->iteration }}</span>
                                <img src="{{ $vendor->profile_image ?: '/images/default-avatar.png' }}" alt="{{ $vendor->pseudonym }}" class="vendor-avatar">
                                <span class="vendor-ranking-main">
                                    <strong>{{ $vendor->pseudonym }}</strong>
                                    <small>{{ $vendor->completed_orders_count }} completed {{ $vendor->completed_orders_count === 1 ? 'order' : 'orders' }}</small>
                                </span>
                                <span class="vendor-ranking-value">{{ number_format((float) ($vendor->completed_sales ?? 0), 8) }} BTC</span>
                            </a>
                        @empty
                            <p class="text-muted mb-0">No completed sales yet.</p>
                        @endforelse
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="vendor-ranking-card h-100">
                        <div class="vendor-ranking-header">
                            <div>
                                <span class="vendor-ranking-kicker">REVIEWS</span>
                                <h3>Best Rated Vendors</h3>
                            </div>
                            <i class="fa-solid fa-star"></i>
                        </div>
                        @forelse($bestRatedVendors as $vendor)
                            <a href="{{ route('vendors.profile', $vendor->pseudonym) }}" class="vendor-ranking-row">
                                <span class="vendor-rank">{{ $loop->iteration }}</span>
                                <img src="{{ $vendor->profile_image ?: '/images/default-avatar.png' }}" alt="{{ $vendor->pseudonym }}" class="vendor-avatar">
                                <span class="vendor-ranking-main">
                                    <strong>{{ $vendor->pseudonym }}</strong>
                                    <small>{{ $vendor->approved_reviews_count }} approved {{ $vendor->approved_reviews_count === 1 ? 'review' : 'reviews' }}</small>
                                </span>
                                <span class="vendor-rating">★ {{ number_format((float) $vendor->approved_reviews_avg_rating, 2) }}</span>
                            </a>
                        @empty
                            <p class="text-muted mb-0">Vendors need at least 3 approved reviews to appear here.</p>
                        @endforelse
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="vendor-ranking-card h-100">
                        <div class="vendor-ranking-header">
                            <div>
                                <span class="vendor-ranking-kicker">PERFORMANCE</span>
                                <h3>Worst Selling Vendors</h3>
                            </div>
                            <i class="fa-solid fa-arrow-down"></i>
                        </div>
                        @forelse($worstSellingVendors as $vendor)
                            <a href="{{ route('vendors.profile', $vendor->pseudonym) }}" class="vendor-ranking-row">
                                <span class="vendor-rank">{{ $loop->iteration }}</span>
                                <img src="{{ $vendor->profile_image ?: '/images/default-avatar.png' }}" alt="{{ $vendor->pseudonym }}" class="vendor-avatar">
                                <span class="vendor-ranking-main">
                                    <strong>{{ $vendor->pseudonym }}</strong>
                                    <small>{{ $vendor->completed_orders_count }} completed {{ $vendor->completed_orders_count === 1 ? 'order' : 'orders' }}</small>
                                </span>
                                <span class="vendor-ranking-value">{{ number_format((float) ($vendor->completed_sales ?? 0), 8) }} BTC</span>
                            </a>
                        @empty
                            <p class="text-muted mb-0">No active vendors to rank.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <p class="text-center text-muted small mt-4 mb-0">Sales rankings use completed, delivered and released orders. Ratings use approved product reviews.</p>
        </div>
    </section>

    <section class="sale-banner pt-5 pb-5 animate-on-scroll">
        <img src="assets/images/homesale-banner.png" alt="">
    </section>

    <section class="products-home py-5 animate-on-scroll">
        <div class="container">
            <h1 class="sec-heading mb-5">{{ __('store.home.featured_products') }}</h1>
            <div class="row">
                @foreach ($products as $product)
                <div class="col-md-3">
                    <div class="product-card">
                        <div class="product-img">
                            <img src="{{ Storage::url(optional($product->thumbnail)->image_url ?? 'default.jpg') }}" alt="{{ $product->translation->name ?? 'Product Name Not Available' }}">
                            <button class="wishlist-btn"><i class="fa-solid fa-heart"></i></button>
                        </div>
                        <div class="product-info mt-4">
                            <div class="top-info">
                                <div class="reviews"><i class="fa-solid fa-star"></i>({{ $product->reviews_count }} {{ __('store.home.reviews') }})</div>
                            </div>
                            <div class="bottom-info">
                                <div class="left">
                                    <h3>
                                        <a href="{{ route('product.show', $product->slug) }}" class="product-title">
                                            {{ $product->translation->name ?? 'Product Name Not Available' }}
                                        </a>
                                    </h3>
                                    <p class="price">
                                        <span class="original {{ optional($product->primaryVariant)->converted_discount_price ? 'has-discount' : '' }}">
                                            {{ $currency->symbol }}{{ optional($product->primaryVariant)->converted_price ?? 'N/A' }}
                                        </span>

                                        @if(optional($product->primaryVariant)->converted_discount_price)
                                            <span class="discount"> 
                                                {{ $currency->symbol }}{{ $product->primaryVariant->converted_discount_price }}
                                            </span>
                                        @endif
                                    </p>
                                </div>
                                <button class="cart-btn" onclick="addToCart({{ $product->id }})">
                                    <i class="fa fa-shopping-bag"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            <div class="view-button text-center mt-4">
                <a href="{{ route('shop.index') }}" class="read-more pe-4 ps-4">{{ __('store.home.view_all') }}</a>
            </div>

        </div>
    </section>

    <section class="why-choose-us py-5 animate-on-scroll">
        <div class="container">
            <h1 class="sec-heading text-start mb-5">{{ __('store.home.why_choose_us') }}</h1>
            <div class="row">
                <div class="col-md-3">
                    <div class="feature-box text-start">
                        <div class="feature-icon">
                            <img src="https://i.ibb.co/WNQXhLnP/choose-icon1.png" alt="">
                        </div>
                        <h3>{{ __('store.home.fast_delivery_title') }}</h3>
                        <p>{{ __('store.home.fast_delivery_text') }}</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="feature-box text-start">
                        <div class="feature-icon">
                            <img src="https://i.ibb.co/FkmgGPrr/choose-icon2.png" alt="">
                        </div>
                        <h3>{{ __('store.home.customer_support_title') }}</h3>
                        <p>{{ __('store.home.customer_support_text') }}</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="feature-box text-start">
                        <div class="feature-icon">
                            <img src="https://i.ibb.co/CffNqX9/choose-icon3.png" alt="">
                        </div>
                        <h3>{{ __('store.home.trusted_worldwide_title') }}</h3>
                        <p>{{ __('store.home.trusted_worldwide_text') }}</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="feature-box text-start">
                        <div class="feature-icon">
                            <img src="https://i.ibb.co/XPvjQGG/choose-icon4.png" alt="">
                        </div>
                        <h3>{{ __('store.home.ten_years_services_title') }}</h3>
                        <p>{{ __('store.home.ten_years_services_text') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('js')
<style>
.vendor-ranking-card { background:#fff; border:1px solid #e9ecef; border-radius:18px; padding:22px; box-shadow:0 8px 28px rgba(0,0,0,.05); }
.vendor-ranking-header { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; padding-bottom:14px; margin-bottom:8px; border-bottom:1px solid #eef0f3; }
.vendor-ranking-header h3 { margin:3px 0 0; font-size:20px; font-weight:800; }
.vendor-ranking-header > i { font-size:22px; margin-top:4px; }
.vendor-ranking-kicker { font-size:10px; font-weight:800; letter-spacing:.14em; color:#6c757d; }
.vendor-ranking-row { display:flex; align-items:center; gap:10px; padding:12px 0; color:inherit; text-decoration:none; border-bottom:1px solid #f1f3f5; }
.vendor-ranking-row:last-child { border-bottom:0; }
.vendor-ranking-row:hover { color:inherit; transform:translateX(2px); }
.vendor-rank { width:22px; font-weight:800; color:#6c757d; text-align:center; }
.vendor-avatar { width:40px; height:40px; border-radius:50%; object-fit:cover; background:#f1f3f5; flex:0 0 40px; }
.vendor-ranking-main { min-width:0; flex:1; display:flex; flex-direction:column; }
.vendor-ranking-main strong { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:14px; }
.vendor-ranking-main small { color:#6c757d; font-size:11px; margin-top:2px; }
.vendor-ranking-value { font-size:11px; font-weight:700; white-space:nowrap; }
.vendor-rating { font-size:12px; font-weight:800; white-space:nowrap; }
@media(max-width:575px){ .vendor-ranking-card{padding:18px;} .vendor-ranking-value{font-size:10px;} }
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script>
    function addToCart(productId) {
        fetch("{{ route('cart.add') }}", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": "{{ csrf_token() }}"
            },
            body: JSON.stringify({ product_id: productId, quantity: 1 })
        })
        .then(response => response.json())
        .then(data => {
            if (data.message) {
                toastr[data.cart ? 'success' : 'error'](data.message, '', {
                    closeButton: true, progressBar: true, positionClass: "toast-top-right", timeOut: 5000
                });
            }
            if (data.cart) updateCartCount(data.cart);
        })
        .catch(error => console.error("Error:", error));
    }

    function updateCartCount(cart) {
        let totalCount = Object.values(cart).reduce((sum, item) => sum + item.quantity, 0);
        const cartCount = document.getElementById("cart-count");
        if (cartCount) cartCount.textContent = totalCount;
    }
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.wishlist-btn').forEach(button => {
        button.addEventListener('click', function () {
            const productId = this.getAttribute('data-product-id');
            if (!productId) return;
            fetch('/customer/wishlist', {
                method: 'POST',
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": "{{ csrf_token() }}",
                    "Accept": "application/json",
                },
                body: JSON.stringify({ product_id: productId })
            })
            .then(response => {
                if (response.status === 401) {
                    window.location.href = '/customer/login';
                } else if (response.ok) {
                    return response.json();
                }
                throw new Error('Something went wrong');
            })
            .then(data => { if (data?.message) alert(data.message); })
            .catch(error => console.error('Error:', error));
        });
    });
});
</script>
@endsection
