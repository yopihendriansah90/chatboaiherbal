<?php

namespace Tests\Unit;

use App\Support\CurrencyFormatter;
use PHPUnit\Framework\TestCase;

class CurrencyFormatterTest extends TestCase
{
    public function test_usd_uses_database_precision(): void
    {
        $this->assertSame('$0.0001946500', CurrencyFormatter::usd('0.0001946500'));
    }

    public function test_currency_formatters_keep_snapshot_precision(): void
    {
        $this->assertSame('$0.0000000000', CurrencyFormatter::usd(0));
        $this->assertSame('$1,234.5670000000', CurrencyFormatter::usd(1234.567));
        $this->assertSame('Rp3,5317', CurrencyFormatter::idr('3.5317'));
        $this->assertNull(CurrencyFormatter::usd(null));
    }
}
