<?php

namespace Tests\Feature\Phase1A;

use App\Support\DateTimeFormatter;
use Carbon\Carbon;
use Tests\TestCase;

class DateTimeFormatterTest extends TestCase
{
    public function test_displays_timestamp_as_wib(): void
    {
        config(['sipreska.display_timezone' => 'Asia/Jakarta']);

        $formatted = DateTimeFormatter::display(
            Carbon::parse('2026-07-13 08:01:12', 'Asia/Jakarta')
        );

        $this->assertStringContainsString('WIB', $formatted);
        $this->assertStringContainsString('2026', $formatted);
        $this->assertStringContainsString('08:01:12', $formatted);
    }
}
