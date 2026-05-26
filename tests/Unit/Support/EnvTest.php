<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Env;
use PHPUnit\Framework\TestCase;

final class EnvTest extends TestCase
{
    protected function setUp(): void
    {
        // Limpa quaisquer chaves de teste de execucoes anteriores.
        unset($_SERVER['TEST_KEY'], $_ENV['TEST_KEY']);
        putenv('TEST_KEY');
    }

    public function testGetFromServer(): void
    {
        $_SERVER['TEST_KEY'] = 'value';
        self::assertSame('value', Env::get('TEST_KEY'));
    }

    public function testGetFallsBackToDefault(): void
    {
        self::assertSame('default', Env::get('NONEXISTENT_KEY', 'default'));
    }

    public function testBoolTrueVariants(): void
    {
        $_SERVER['TEST_KEY'] = 'true';
        self::assertTrue(Env::bool('TEST_KEY'));

        $_SERVER['TEST_KEY'] = '1';
        self::assertTrue(Env::bool('TEST_KEY'));
    }

    public function testBoolFalseVariants(): void
    {
        $_SERVER['TEST_KEY'] = 'false';
        self::assertFalse(Env::bool('TEST_KEY'));

        $_SERVER['TEST_KEY'] = '0';
        self::assertFalse(Env::bool('TEST_KEY'));
    }

    public function testBoolDefault(): void
    {
        self::assertFalse(Env::bool('NONEXISTENT_KEY'));
        self::assertTrue(Env::bool('NONEXISTENT_KEY', true));
    }

    public function testInt(): void
    {
        $_SERVER['TEST_KEY'] = '42';
        self::assertSame(42, Env::int('TEST_KEY'));
        self::assertSame(0, Env::int('NONEXISTENT'));
        self::assertSame(7, Env::int('NONEXISTENT', 7));
    }

    public function testString(): void
    {
        $_SERVER['TEST_KEY'] = 'hello';
        self::assertSame('hello', Env::string('TEST_KEY'));
        self::assertSame('fallback', Env::string('NONEXISTENT', 'fallback'));
    }
}
