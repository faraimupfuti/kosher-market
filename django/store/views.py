from django.shortcuts import get_object_or_404, render
from .models import Product

def catalogue(request):
    products = Product.objects.filter(status=Product.Status.PUBLISHED).select_related("category", "vendor")
    return render(request, "store/catalogue.html", {"products": products})

def product_detail(request, slug):
    product = get_object_or_404(
        Product.objects.select_related("category", "vendor"),
        slug=slug, status=Product.Status.PUBLISHED,
    )
    return render(request, "store/product_detail.html", {"product": product})
