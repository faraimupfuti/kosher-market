from django.contrib import admin
from django.contrib.auth.admin import UserAdmin
from .models import User, VendorProfile, Category, Product, ProductVersion, Cart, CartItem, Order, OrderItem, PaymentAttempt, WebhookEvent, LedgerEntry

admin.site.register(User, UserAdmin)
admin.site.register(VendorProfile)
admin.site.register(Category)
admin.site.register(Product)
admin.site.register(ProductVersion)
admin.site.register(Cart)
admin.site.register(CartItem)
admin.site.register(Order)
admin.site.register(OrderItem)
admin.site.register(PaymentAttempt)
admin.site.register(WebhookEvent)
admin.site.register(LedgerEntry)
