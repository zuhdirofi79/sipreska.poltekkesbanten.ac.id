<?php

namespace Tests\Unit;

use App\Enums\ReservedSlug;
use PHPUnit\Framework\TestCase;

class ReservedSlugTest extends TestCase
{
    public function test_reserved_slugs_are_detected(): void
    {
        $this->assertTrue(ReservedSlug::isReserved('login'));
        $this->assertTrue(ReservedSlug::isReserved('dashboard'));
        $this->assertFalse(ReservedSlug::isReserved('registerEventMenulisEbook'));
    }
}
