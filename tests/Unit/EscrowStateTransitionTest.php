<?php

namespace Tests\Unit;

use App\Models\EscrowTransaction;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class EscrowStateTransitionTest extends TestCase
{
    public function test_funded_escrow_can_be_released_or_disputed(): void
    {
        $escrow = new EscrowTransaction(['status' => 'funded']);

        $this->assertTrue($escrow->canTransitionTo('released'));
        $this->assertTrue($escrow->canTransitionTo('disputed'));
        $this->assertFalse($escrow->canTransitionTo('paid'));
    }

    public function test_refund_pending_can_only_become_refunded(): void
    {
        $escrow = new EscrowTransaction(['status' => 'refund_pending']);

        $this->assertTrue($escrow->canTransitionTo('refunded'));
        $this->assertFalse($escrow->canTransitionTo('released'));
    }

    public function test_invalid_transition_throws(): void
    {
        $escrow = new EscrowTransaction(['status' => 'pending']);

        $this->expectException(RuntimeException::class);
        $escrow->transitionTo('paid');
    }
}
