<?php

namespace ProudlySnoring\Seat\Comlink\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ProudlySnoring\Seat\Comlink\Drivers\Discord\NicknameRenderer;

class NicknameRendererTest extends TestCase
{
    private function render(string $format, array $values): string
    {
        return (new NicknameRenderer())->render($format, $values);
    }

    public function testTokensAreReplaced()
    {
        $values = ['main_name' => 'John Doe', 'corp_ticker' => 'CORP', 'corp_name' => 'My Corp', 'alliance_ticker' => 'ALLY'];

        $this->assertSame('[CORP] John Doe', $this->render('[{corp_ticker}] {main_name}', $values));
        $this->assertSame('ALLY/My Corp', $this->render('{alliance_ticker}/{corp_name}', $values));
        $this->assertSame('{unknown} John Doe', $this->render('{unknown} {main_name}', $values));
    }

    public function testMissingValuesAreDashed()
    {
        $this->assertSame('[-] -', $this->render('[{corp_ticker}] {main_name}', []));
        $this->assertSame('[CORP] - John', $this->render('[{corp_ticker}] {alliance_ticker} {main_name}',
            ['main_name' => 'John', 'corp_ticker' => 'CORP', 'alliance_ticker' => '']));
    }

    public function testWhitespacesAreCollapsed()
    {
        $this->assertSame('a b c', NicknameRenderer::normalize("  a \t\n b\u{00A0}\u{3000}c  "));
    }

    public function testForbiddenCharactersAreRemoved()
    {
        $this->assertSame('abc', NicknameRenderer::normalize("a\u{200B}b\u{FEFF}c\u{2800}"));
        $this->assertSame('ab', NicknameRenderer::normalize("a\u{0007}\u{3164}b"));
        // emoji sequences are kept
        $this->assertSame("\u{1F468}\u{200D}\u{1F680} x", NicknameRenderer::normalize("\u{1F468}\u{200D}\u{1F680} x"));
    }

    public function testTruncatedTo32Characters()
    {
        $nickname = NicknameRenderer::normalize(str_repeat('é', 40));
        $this->assertSame(32, mb_strlen($nickname));

        // no trailing space after truncation
        $this->assertSame(str_repeat('a', 31), NicknameRenderer::normalize(str_repeat('a', 31) . ' bbbb'));
    }

    public function testEmptyBecomesDash()
    {
        $this->assertSame('-', NicknameRenderer::normalize(''));
        $this->assertSame('-', NicknameRenderer::normalize("\u{200B} "));
        $this->assertSame('-', NicknameRenderer::normalize(null));
    }
}
