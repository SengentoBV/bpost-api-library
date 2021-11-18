<?php

namespace Bpost\BpostApiClient\test\Exception\BpostApiResponseException;

use Bpost\BpostApiClient\Exception\BpostApiResponseException\BpostInvalidResponseException;

class BpostInvalidResponseExceptionTest extends \PHPUnit\Framework\TestCase
{
    public function testGetMessage()
    {
        $ex = new BpostInvalidResponseException('Oops');
        $this->assertSame('Invalid response: Oops', $ex->getMessage());

        $ex = new BpostInvalidResponseException();
        $this->assertSame('Invalid response', $ex->getMessage());
    }
}
