<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ExampleTest extends TestCase
{
    public function testCanAddTwoNumbers(): void
    {
        $this->assertEquals(4, 2 + 2);
    }
}