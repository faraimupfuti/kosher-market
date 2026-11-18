from decimal import Decimal
from django.contrib import messages
from django.contrib.auth import login
from django.contrib.auth.decorators import login_required
from django.db import transaction
from django.shortcuts import get_object_or_404, redirect, render
from django.views.decorators.http import require_POST
from .forms import RegistrationForm
from .models import Cart, CartItem, Order, OrderItem, Product, VendorProfile

def catalogue(request):
    products = Product.objects.filter(status=Product.Status.PUBLISHED, vendor__status=VendorProfile.Status.APPROVED).select_related("category", "vendor")
    return render(request, "store/catalogue.html", {"products": products})

def product_detail(request, slug):
    product = get_object_or_404(
        Product.objects.select_related("category", "vendor"),
        slug=slug, status=Product.Status.PUBLISHED, vendor__status=VendorProfile.Status.APPROVED,
    )
    return render(request, "store/product_detail.html", {"product": product})

def register(request):
    if request.method == "POST":
        form = RegistrationForm(request.POST)
        if form.is_valid():
            user = form.save()
            login(request, user)
            return redirect("catalogue")
    else:
        form = RegistrationForm()
    return render(request, "store/register.html", {"form": form})

@login_required
def cart_detail(request):
    cart, _ = Cart.objects.get_or_create(customer=request.user)
    items = cart.items.select_related("product", "product__vendor")
    total = sum((item.product.price_usd * item.quantity for item in items), Decimal("0.00"))
    return render(request, "store/cart.html", {"cart": cart, "items": items, "total": total})

@login_required
@require_POST
def cart_add(request, product_id):
    product = get_object_or_404(Product, pk=product_id, status=Product.Status.PUBLISHED, vendor__status=VendorProfile.Status.APPROVED)
    cart, _ = Cart.objects.get_or_create(customer=request.user)
    item, created = CartItem.objects.get_or_create(cart=cart, product=product)
    if not created:
        item.quantity += 1
        item.save(update_fields=["quantity"])
    messages.success(request, "Product added to your cart.")
    return redirect("cart_detail")

@login_required
@require_POST
def cart_remove(request, item_id):
    cart = get_object_or_404(Cart, customer=request.user)
    get_object_or_404(CartItem, cart=cart, pk=item_id).delete()
    return redirect("cart_detail")

@login_required
@require_POST
def create_order(request):
    cart = get_object_or_404(Cart, customer=request.user)
    with transaction.atomic():
        items = list(cart.items.select_related("product", "product__vendor").select_for_update())
        if not items:
            messages.error(request, "Your cart is empty.")
            return redirect("cart_detail")
        if any(i.product.status != Product.Status.PUBLISHED or i.product.vendor.status != VendorProfile.Status.APPROVED for i in items):
            messages.error(request, "A product in your cart is no longer available.")
            return redirect("cart_detail")
        order = Order.objects.create(customer=request.user)
        subtotal = Decimal("0.00")
        for item in items:
            price = item.product.price_usd
            commission = Decimal("0.1000")
            vendor_earning = (price * item.quantity * (Decimal("1.0000") - commission)).quantize(Decimal("0.01"))
            subtotal += price * item.quantity
            latest = item.product.versions.filter(is_latest=True).first()
            OrderItem.objects.create(
                order=order, product=item.product, product_title=item.product.title,
                vendor_name=item.product.vendor.store_name, unit_price_usd=price,
                commission_rate=commission, vendor_earning_usd=vendor_earning,
                product_version=latest.version if latest else "",
            )
        order.subtotal_usd = subtotal
        order.save(update_fields=["subtotal_usd"])
        cart.items.all().delete()
    return render(request, "store/order_created.html", {"order": order})
