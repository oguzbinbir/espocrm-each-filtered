<?php

declare(strict_types=1);

namespace Espo\Modules\PdfFilterHelper\Tests\Filter;

use Espo\Modules\PdfFilterHelper\Filter\FilterParser;
use Espo\Modules\PdfFilterHelper\Filter\Exception\FilterParseException;
use PHPUnit\Framework\TestCase;

class FilterParserTest extends TestCase
{
    private FilterParser $parser;

    protected function setUp(): void
    {
        $this->parser = new FilterParser();
    }

    // -----------------------------------------------------------------------
    // Simple comparisons
    // -----------------------------------------------------------------------

    public function testEquality(): void
    {
        $result = $this->parser->parse('status == "Planned"');
        $this->assertSame(['status' => 'Planned'], $result);
    }

    public function testNotEqual(): void
    {
        $result = $this->parser->parse('cancelled != true');
        $this->assertSame(['cancelled!=' => true], $result);
    }

    public function testGreaterThanOrEqual(): void
    {
        $result = $this->parser->parse('amount >= 100');
        $this->assertSame(['amount>=' => 100], $result);
    }

    public function testLessThan(): void
    {
        $result = $this->parser->parse('priority < 3');
        $this->assertSame(['priority<' => 3], $result);
    }

    public function testNullLiteral(): void
    {
        $result = $this->parser->parse('assignedUser == null');
        $this->assertSame(['assignedUser' => null], $result);
    }

    // -----------------------------------------------------------------------
    // Special values
    // -----------------------------------------------------------------------

    public function testTodaySpecial(): void
    {
        $result = $this->parser->parse('dateStart >= today');
        $this->assertArrayHasKey('dateStart>=', $result);
        // Value should be today's date in Y-m-d format
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $result['dateStart>=']);
    }

    public function testNowSpecial(): void
    {
        $result = $this->parser->parse('createdAt <= now');
        $this->assertArrayHasKey('createdAt<=', $result);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $result['createdAt<=']);
    }

    // -----------------------------------------------------------------------
    // IN operator
    // -----------------------------------------------------------------------

    public function testInOperator(): void
    {
        $result = $this->parser->parse('status in ["Planned","Confirmed"]');
        $this->assertSame(['status' => ['Planned', 'Confirmed']], $result);
    }

    public function testInOperatorWithNumbers(): void
    {
        $result = $this->parser->parse('priority in [1, 2, 3]');
        $this->assertSame(['priority' => [1, 2, 3]], $result);
    }

    public function testInOperatorSingleItem(): void
    {
        $result = $this->parser->parse('type in ["Call"]');
        $this->assertSame(['type' => ['Call']], $result);
    }

    // -----------------------------------------------------------------------
    // Logical operators
    // -----------------------------------------------------------------------

    public function testAndOperator(): void
    {
        $result = $this->parser->parse('status == "Planned" && dateStart >= today');

        $this->assertArrayHasKey('AND', $result);
        $this->assertCount(2, $result['AND']);
        $this->assertSame(['status' => 'Planned'], $result['AND'][0]);
    }

    public function testOrOperator(): void
    {
        $result = $this->parser->parse('status == "Planned" || status == "Confirmed"');

        $this->assertArrayHasKey('OR', $result);
        $this->assertCount(2, $result['OR']);
    }

    public function testComplexExpression(): void
    {
        $result = $this->parser->parse(
            'dateStart >= today && status in ["Planned","Confirmed"] && cancelled == false'
        );

        // Outermost should be AND
        $this->assertArrayHasKey('AND', $result);
    }

    public function testParentheses(): void
    {
        $result = $this->parser->parse('(status == "Planned" || status == "Confirmed") && cancelled == false');

        $this->assertArrayHasKey('AND', $result);
        $this->assertArrayHasKey('OR', $result['AND'][0]);
    }

    // -----------------------------------------------------------------------
    // Edge cases
    // -----------------------------------------------------------------------

    public function testSingleQuoteStrings(): void
    {
        $result = $this->parser->parse("status == 'Planned'");
        $this->assertSame(['status' => 'Planned'], $result);
    }

    public function testFloatNumber(): void
    {
        $result = $this->parser->parse('amount >= 9.99');
        $this->assertSame(['amount>=' => 9.99], $result);
    }

    public function testNegativeNumber(): void
    {
        $result = $this->parser->parse('balance < -100');
        $this->assertSame(['balance<' => -100], $result);
    }

    // -----------------------------------------------------------------------
    // Error cases
    // -----------------------------------------------------------------------

    public function testInvalidFieldNameThrows(): void
    {
        $this->expectException(FilterParseException::class);
        $this->parser->parse('DROP TABLE users == true');
    }

    public function testUnterminatedStringThrows(): void
    {
        $this->expectException(FilterParseException::class);
        $this->parser->parse('status == "Planned');
    }

    public function testMissingOperatorThrows(): void
    {
        $this->expectException(FilterParseException::class);
        $this->parser->parse('status "Planned"');
    }

    public function testEmptyExpressionThrows(): void
    {
        $this->expectException(FilterParseException::class);
        $this->parser->parse('');
    }

    public function testInjectionAttemptThrows(): void
    {
        $this->expectException(FilterParseException::class);
        $this->parser->parse('1=1; DROP TABLE contacts --');
    }
}
