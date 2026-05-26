<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    public function testNormalizeEmailLowercasesAndTrims(): void
    {
        self::assertSame('foo@bar.com', Validator::normalizeEmail('  FOO@bar.COM  '));
    }

    public function testNormalizeCpfStripsNonDigits(): void
    {
        self::assertSame('12345678909', Validator::normalizeCpf('123.456.789-09'));
        self::assertSame('', Validator::normalizeCpf('abc'));
    }

    public function testIsValidCpfAcceptsKnownValid(): void
    {
        // CPF valido conhecido (digitos verificadores corretos)
        self::assertTrue(Validator::isValidCpf('390.533.447-05'));
    }

    public function testIsValidCpfRejectsRepeated(): void
    {
        self::assertFalse(Validator::isValidCpf('111.111.111-11'));
        self::assertFalse(Validator::isValidCpf('000.000.000-00'));
    }

    public function testIsValidCpfRejectsWrongDigit(): void
    {
        self::assertFalse(Validator::isValidCpf('390.533.447-06'));
    }

    public function testIsValidCpfRejectsTooShort(): void
    {
        self::assertFalse(Validator::isValidCpf('123'));
    }

    public function testIsValidEmail(): void
    {
        self::assertTrue(Validator::isValidEmail('user@example.com'));
        self::assertFalse(Validator::isValidEmail('not-an-email'));
        self::assertFalse(Validator::isValidEmail(''));
    }

    public function testNormalizeBrPhoneAdds55ToElevenDigitNumber(): void
    {
        self::assertSame('5569999998888', Validator::normalizeBrPhone('(69) 99999-8888'));
    }

    public function testNormalizeBrPhoneKeepsThirteenDigitsWith55(): void
    {
        self::assertSame('5569999998888', Validator::normalizeBrPhone('5569999998888'));
    }

    public function testNormalizeBrPhoneFixesPrefixedThirteenDigits(): void
    {
        // 13 digitos sem prefixo 55 -> mantem os ultimos 11 e adiciona 55
        self::assertSame('5511987654321', Validator::normalizeBrPhone('9911987654321'));
    }

    public function testIsValidBrPhoneAccepts(): void
    {
        self::assertTrue(Validator::isValidBrPhone('(11) 98765-4321'));
        self::assertTrue(Validator::isValidBrPhone('5569999998888'));
    }

    public function testIsValidBrPhoneRejectsBadDdd(): void
    {
        // DDD 10 (abaixo do minimo 11)
        self::assertFalse(Validator::isValidBrPhone('5510987654321'));
    }

    public function testIsValidBrPhoneRejectsTooShort(): void
    {
        self::assertFalse(Validator::isValidBrPhone('123'));
    }
}
