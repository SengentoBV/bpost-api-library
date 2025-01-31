<?php
namespace Bpost;

use Bpost\BpostApiClient\Bpost\Order\ParcelsDepotAddress;
use Bpost\BpostApiClient\Exception\BpostLogicException\BpostInvalidLengthException;

class ParcelsDepotAddressTest extends \PHPUnit\Framework\TestCase
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
     * Tests Address->toXML
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
        $address = $expectedDocument->createElement('parcelsDepotAddress');
        foreach ($data as $key => $value) {
            $address->appendChild(
                $expectedDocument->createElement($key, $value)
            );
        }
        $expectedDocument->appendChild($address);

        $actualDocument = self::createDomDocument();
        $address = new ParcelsDepotAddress(
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

    /**
     * @throws BpostInvalidLengthException
     */
    public function testFaultyProperties()
    {
        $this->expectException(\Bpost\BpostApiClient\Exception\BpostLogicException\BpostInvalidLengthException::class);

        $address = new ParcelsDepotAddress();
        $address->setBox(str_repeat('a', 9));
    }

    /**
     * @throws BpostInvalidLengthException
     */
    public function testFaultyCountryCodeProperties()
    {
        $this->expectException(\Bpost\BpostApiClient\Exception\BpostLogicException\BpostInvalidLengthException::class);

        $address = new ParcelsDepotAddress();
        $address->setCountryCode(str_repeat('a', 3));
    }

    /**
     * @throws BpostInvalidLengthException
     */
    public function testFaultyLocalityProperties()
    {
        $this->expectException(\Bpost\BpostApiClient\Exception\BpostLogicException\BpostInvalidLengthException::class);

        $address = new ParcelsDepotAddress();
        $address->setLocality(str_repeat('a', 41));
    }

    /**
     * @throws BpostInvalidLengthException
     */
    public function testFaultyNumberProperties()
    {
        $this->expectException(\Bpost\BpostApiClient\Exception\BpostLogicException\BpostInvalidLengthException::class);

        $address = new ParcelsDepotAddress();
        $address->setNumber(str_repeat('a', 9));
    }

    /**
     * @throws BpostInvalidLengthException
     */
    public function testFaultyPostalCodeProperties()
    {
        $this->expectException(\Bpost\BpostApiClient\Exception\BpostLogicException\BpostInvalidLengthException::class);

        $address = new ParcelsDepotAddress();
        $address->setPostalCode(str_repeat('a', 41));
    }

    /**
     * @throws BpostInvalidLengthException
     */
    public function testFaultyStreetNameProperties()
    {
        $this->expectException(\Bpost\BpostApiClient\Exception\BpostLogicException\BpostInvalidLengthException::class);

        $address = new ParcelsDepotAddress();
        $address->setStreetName(str_repeat('a', 41));
    }
}
