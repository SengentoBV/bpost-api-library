<?php

use Bpost\BpostApiClient\Bpost\Order\Box\National\ParcelLockerReducedMobilityZone;
use Bpost\BpostApiClient\Bpost\Order\Box\National\Unregistered;

class UnregisteredTest extends \PHPUnit\Framework\TestCase
{

    /**
     * Create a generic DOM Document
     *
     * @return \DOMDocument
     */
    private static function createDomDocument()
    {
        $document = new \DOMDocument('1.0', 'utf-8');
        $document->preserveWhiteSpace = false;
        $document->formatOutput = true;

        return $document;
    }

    /**
     * Tests CashOnDelivery->toXML
     */
    public function testToXML()
    {
        $self = new Unregistered();
        $self->setLanguage('EN');
        $self->setEmailAddress('pomme@antidot.com');
        $self->setMobilePhone('0123456789');
        $self->setParcelLockerReducedMobilityZone(new ParcelLockerReducedMobilityZone());

        // Without prefix
        $rootDom = $this->createDomDocument();
        $rootDom->appendChild($self->toXml($rootDom));

        $this->assertEquals($this->getXmlWithoutPrefix(), $rootDom->saveXML());

        // With prefix
        $rootDom = $this->createDomDocument();
        $rootDom->appendChild($self->toXml($rootDom, 'test'));

        $this->assertEquals($this->getXmlWithPrefix(), $rootDom->saveXML());

        return;
    }

    public function testCreateFromXml()
    {
        $self = Unregistered::createFromXml(new \SimpleXMLElement($this->getXmlWithoutPrefix()));

        $this->assertTrue($self->hasLanguage());
        $this->assertSame('EN', $self->getLanguage());

        $this->assertTrue($self->hasEmailAddress());
        $this->assertSame('pomme@antidot.com', $self->getEmailAddress());

        $this->assertTrue($self->hasMobilePhone());
        $this->assertSame('0123456789', $self->getMobilePhone());

        $this->assertTrue($self->hasParcelLockerReducedMobilityZone());
    }

    private function getXmlWithoutPrefix()
    {
        return <<<EOF
<?xml version="1.0" encoding="utf-8"?>
<unregistered>
  <language>EN</language>
  <mobilePhone>0123456789</mobilePhone>
  <emailAddress>pomme@antidot.com</emailAddress>
  <parcelLockerReducedMobilityZone/>
</unregistered>

EOF;
    }

    private function getXmlWithPrefix()
    {
        return <<<EOF
<?xml version="1.0" encoding="utf-8"?>
<test:unregistered>
  <test:language>EN</test:language>
  <test:mobilePhone>0123456789</test:mobilePhone>
  <test:emailAddress>pomme@antidot.com</test:emailAddress>
  <test:parcelLockerReducedMobilityZone/>
</test:unregistered>

EOF;
    }
}
