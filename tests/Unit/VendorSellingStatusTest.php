<?php

namespace Tests\Unit;

use App\Models\Vendor;
use PHPUnit\Framework\TestCase;

class VendorSellingStatusTest extends TestCase
{
    public function test_active_vendor_can_sell(): void
    {
        $vendor = new Vendor(['status' => 'active']);

        $this->assertTrue($vendor->isSellingEnabled());
    }

    public function test_blocked_vendor_cannot_sell(): void
    {
        $vendor = new Vendor(['status' => 'inactive']);

        $this->assertFalse($vendor->isSellingEnabled());
    }

    public function test_banned_vendor_cannot_sell(): void
    {
        $vendor = new Vendor(['status' => 'banned']);

        $this->assertFalse($vendor->isSellingEnabled());
    }
}
