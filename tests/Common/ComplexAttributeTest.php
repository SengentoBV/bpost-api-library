<?php

namespace Bpost\BpostApiClient;

use Bpost\BpostApiClient\Common\ComplexAttribute;
use Bpost\BpostApiClient\Exception\BpostLogicException;

class ComplexAttributeTest extends \PHPUnit_Framework_TestCase
{

    public function testGetPrefixedTagName()
    {
        $fake = $this->getComplexAttributeMock();

        $this->assertSame('fake:name', $fake->getPrefixedTagName('name', 'fake'));
        $this->assertSame('name', $fake->getPrefixedTagName('name', ''));
        $this->assertSame('name', $fake->getPrefixedTagName('name'));
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|ComplexAttribute
     */
    private function getComplexAttributeMock()
    {
        return self::getMockForAbstractClass('\Bpost\BpostApiClient\Common\ComplexAttribute');
    }
}
