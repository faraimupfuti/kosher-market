from decimal import Decimal
from django.test import TestCase
from django.urls import reverse
from .models import Category, Product, User, VendorProfile

class CatalogueTests(TestCase):
    def setUp(self):
        self.user = User.objects.create_user(
            username="vendor", email="vendor@example.com", password="Test-password-123"
        )
        self.vendor = VendorProfile.objects.create(
            user=self.user, store_name="Example Tools", country="ZW",
            status=VendorProfile.Status.APPROVED,
        )
        self.category = Category.objects.create(name="Developer Tools", slug="developer-tools")
        self.product = Product.objects.create(
            vendor=self.vendor, category=self.category, title="CLI Toolkit",
            slug="cli-toolkit", summary="A useful command-line tool",
            description="Product description", price_usd=Decimal("19.00"),
            status=Product.Status.PUBLISHED,
        )

    def test_published_product_is_visible(self):
        response = self.client.get(reverse("catalogue"))
        self.assertContains(response, "CLI Toolkit")

    def test_unapproved_vendor_product_is_hidden(self):
        self.vendor.status = VendorProfile.Status.PENDING
        self.vendor.save()
        response = self.client.get(reverse("catalogue"))
        self.assertNotContains(response, "CLI Toolkit")
