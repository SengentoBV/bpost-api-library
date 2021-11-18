<?php

namespace Bpost\BpostApiClient\test\Exception\LogicException;

use Bpost\BpostApiClient\Exception\BpostLogicException\BpostInvalidWeightException;

class BpostInvalidWeightExceptionTest extends \PHPUnit\Framework\TestCase
{
    public function testGetMessage()
    {
        $ex = new BpostInvalidWeightException(32, 30);
        $this->assertSame('Invalid weight (32 kg), maximum is 30.', $ex->getMessage());
    }
}
