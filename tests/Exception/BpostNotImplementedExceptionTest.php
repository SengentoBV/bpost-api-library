<?php

namespace Bpost\BpostApiClient\test\Exception;

use Bpost\BpostApiClient\Exception\BpostNotImplementedException;

class BpostNotImplementedExceptionTest extends \PHPUnit\Framework\TestCase
{
    public function testGetMessage()
    {
        $ex = new BpostNotImplementedException();
        $this->assertStringContainsString('Not implemented', $ex->getMessage());
    }
}
