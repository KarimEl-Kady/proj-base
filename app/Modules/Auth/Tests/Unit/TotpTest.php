<?php

namespace App\Modules\Auth\Tests\Unit;

use App\Modules\Auth\Support\Totp;
use Tests\TestCase;

class TotpTest extends TestCase
{
    /**
     * RFC 6238 Appendix B test vector: ASCII secret "12345678901234567890"
     * (base32: GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ) at T=59 gives 94287082;
     * the 6-digit code is the last six digits: 287082.
     */
    public function test_rfc6238_test_vector(): void
    {
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

        $this->assertSame('287082', Totp::code($secret, 59));
        $this->assertSame('081804', Totp::code($secret, 1111111109));
    }

    public function test_verify_accepts_adjacent_windows_and_rejects_bad_codes(): void
    {
        $secret = Totp::generateSecret();

        $this->assertTrue(Totp::verify($secret, Totp::code($secret)));
        $this->assertTrue(Totp::verify($secret, Totp::code($secret, time() - 30)));
        $this->assertFalse(Totp::verify($secret, '000000'));
    }

    public function test_uri_contains_secret_and_issuer(): void
    {
        $uri = Totp::uri('ABC234', 'user@example.com', 'MyApp');

        $this->assertStringStartsWith('otpauth://totp/MyApp:user%40example.com?', $uri);
        $this->assertStringContainsString('secret=ABC234', $uri);
        $this->assertStringContainsString('issuer=MyApp', $uri);
    }
}
