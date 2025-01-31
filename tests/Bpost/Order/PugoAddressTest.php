<?php
namespace Bpost;

use Bpost\BpostApiClient\Bpost\Order\PugoAddress;
use Bpost\BpostApiClient\Exception\BpostLogicException\BpostInvalidLengthException;

class PugoAddressTest extends \PHPUnit\Framework\TestCase
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
     * Tests PugoAddress->toXML
     */
    public function testToXML()
    {
        $data = array(
            'streetName' => 'Afrikalaan',
            'number' => '2890',
            'box' => '3',
            'postalCode' => '9000',
            'locality' => 'Gent',
            'countryCode' => 'BE',
        );

        $expectedDocument = self::createDomDocument();
        $address = $expectedDocument->createElement('pugoAddress');
        foreach ($data as $key => $value) {
            $address->appendChild(
                $expectedDocument->createElement($key, $value)
            );
        }
        $expectedDocument->appendChild($address);

        $actualDocument = self::createDomDocument();
        $address = new PugoAddress(
            $data['streetName'],
            $data['number'],
            $data['box'],
            $data['postalCode'],
            $data['locality'],
            $data['countryCode']
        );
        $actualDocument->appendChild(
            $address->toXML($actualDocument, null)
        );

        $this->assertEquals($expectedDocument, $actualDocument);
    }

    public function testFaultyBoxProperties()
    {
        $this->expectException(\Bpost\BpostApiClient\Exception\BpostLogicException\BpostInvalidLengthException::class);

        $address = new PugoAddress();
        $address->setBox(str_repeat('a', 9));
    }

    public function testFaultyCountryCodeProperties()
    {
        $this->expectException(\Bpost\BpostApiClient\Exception\BpostLogicException\BpostInvalidLengthException::class);

        $address = new PugoAddress();
        $address->setCountryCode(str_repeat('a', 3));
    }

    public function testFaultyLocalityProperties()
    {
        $this->expectException(\Bpost\BpostApiClient\Exception\BpostLogicException\BpostInvalidLengthException::class);

        $address = new PugoAddress();
        $address->setLocality(str_repeat('a', 41));
    }

    public function testFaultyNumberProperties()
    {
        $this->expectException(\Bpost\BpostApiClient\Exception\BpostLogicException\BpostInvalidLengthException::class);

        $address = new PugoAddress();
        $address->setNumber(str_repeat('a', 9));
    }

    public function testFaultyPostalCodeProperties()
    {
        $this->expectException(\Bpost\BpostApiClient\Exception\BpostLogicException\BpostInvalidLengthException::class);

        $address = new PugoAddress();
        $address->setPostalCode(str_repeat('a', 41));
    }

    public function testFaultyStreetNameProperties()
    {
        $this->expectException(\Bpost\BpostApiClient\Exception\BpostLogicException\BpostInvalidLengthException::class);

        $address = new PugoAddress();
        $address->setStreetName(str_repeat('a', 41));
    }
}
