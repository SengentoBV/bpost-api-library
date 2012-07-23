<?php

/**
 * bPost class
 *
 * This source file can be used to communicate with the bPost Shipping Manager API
 *
 * The class is documented in the file itself. If you find any bugs help me out and report them. Reporting can be done by sending an email to php-bpost-bugs[at]verkoyen[dot]eu.
 * If you report a bug, make sure you give me enough information (include your code).
 *
 * License
 * Copyright (c), Tijs Verkoyen. All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products derived from this software without specific prior written permission.
 *
 * This software is provided by the author "as is" and any express or implied warranties, including, but not limited to, the implied warranties of merchantability and fitness for a particular purpose are disclaimed. In no event shall the author be liable for any direct, indirect, incidental, special, exemplary, or consequential damages (including, but not limited to, procurement of substitute goods or services; loss of use, data, or profits; or business interruption) however caused and on any theory of liability, whether in contract, strict liability, or tort (including negligence or otherwise) arising in any way out of the use of this software, even if advised of the possibility of such damage.
 *
 * @author Tijs Verkoyen <php-bpost@verkoyen.eu>
 * @version 1.0.0
 * @copyright Copyright (c), Tijs Verkoyen. All rights reserved.
 * @license BSD License
 */
class bPost
{
	// internal constant to enable/disable debugging
	const DEBUG = true;

	// URL for the api
	const API_URL = 'https://api.bpost.be/services/shm';

	// current version
	const VERSION = '1.0.0';

	/**
	 * The account id
	 *
	 * @var string
	 */
	private $accountId;

	/**
	 * A cURL instance
	 *
	 * @var resource
	 */
	private $curl;

	/**
	 * The passphrase
	 *
	 * @var string
	 */
	private $passphrase;

	/**
	 * The port to use.
	 *
	 * @var int
	 */
	private $port;

	/**
	 * The timeout
	 *
	 * @var int
	 */
	private $timeOut = 10;

	/**
	 * The user agent
	 *
	 * @var string
	 */
	private $userAgent;

	// class methods
	/**
	 * Default constructor
	 *
	 * @param string $accountId
	 * @param string $passphrase
	 */
	public function __construct($accountId, $passphrase)
	{
		$this->accountId = (string) $accountId;
		$this->passphrase = (string) $passphrase;
	}

	/**
	 * Destructor
	 */
	public function __destruct()
	{
		// is the connection open?
		if($this->curl !== null)
		{
			// close connection
			curl_close($this->curl);

			// reset
			$this->curl = null;
		}
	}

	/**
	 * Callback-method for elements in the return-array
	 *
	 * @param mixed $input			The value.
	 * @param string $key string	The key.
	 * @param DOMDocument $xml		Some data.
	 */
	private static function arrayToXML(&$input, $key, $xml)
	{
		// wierd stuff
		if($key == 'orderLine')
		{
			foreach($input as $row)
			{
				$element = new DOMElement($key);
				$xml->appendChild($element);

				// loop properties
				foreach($row as $name => $value)
				{
					$node = new DOMElement($name, $value);
					$element->appendChild($node);
				}
			}

			return;
		}

		// skip attributes
		if($key == '@attributes') return;

		if(is_null($input)) return;

		// create element
		$element = new DOMElement($key);

		// append
		$xml->appendChild($element);

		// no value? just stop here
		if($input === null) return;

		// is it an array and are there attributes
		if(is_array($input) && isset($input['@attributes']))
		{
			// loop attributes
			foreach((array) $input['@attributes'] as $name => $value) $element->setAttribute($name, $value);

			// reset value
			if(count($input) == 2 && isset($input['value'])) $input = $input['value'];

			// reset the input if it is a single value
			elseif(count($input) == 1) return;
		}

		// the input isn't an array
		if(!is_array($input))
		{
			// boolean
			if(is_bool($input)) $element->appendChild(new DOMText(($input) ? 'true' : 'false'));

			// integer
			elseif(is_int($input)) $element->appendChild(new DOMText($input));

			// floats
			elseif(is_double($input)) $element->appendChild(new DOMText($input));
			elseif(is_float($input)) $element->appendChild(new DOMText($input));

			// a string?
			elseif(is_string($input))
			{
				// characters that require a cdata wrapper
				$illegalCharacters = array('&', '<', '>', '"', '\'');

				// default we dont wrap with cdata tags
				$wrapCdata = false;

				// find illegal characters in input string
				foreach($illegalCharacters as $character)
				{
					if(stripos($input, $character) !== false)
					{
						// wrap input with cdata
						$wrapCdata = true;

						// no need to search further
						break;
					}
				}

				// check if value contains illegal chars, if so wrap in CDATA
				if($wrapCdata) $element->appendChild(new DOMCdataSection($input));

				// just regular element
				else $element->appendChild(new DOMText($input));
			}

			// fallback
			else
			{
				if(self::DEBUG)
				{
					echo 'Unknown type';
					var_dump($input);
					exit();
				}

				$element->appendChild(new DOMText($input));
			}
		}

		// the value is an array
		else
		{
			// init var
			$isNonNumeric = false;

			// loop all elements
			foreach($input as $index => $value)
			{
				// non numeric string as key?
				if(!is_numeric($index))
				{
					// reset var
					$isNonNumeric = true;

					// stop searching
					break;
				}
			}

			// is there are named keys they should be handles as elements
			if($isNonNumeric) array_walk($input, array('bPost', 'arrayToXML'), $element);

			// numeric elements means this a list of items
			else
			{
				// handle the value as an element
				foreach($input as $value)
				{
					if(is_array($value)) array_walk($value, array('bPost', 'arrayToXML'), $element);
				}
			}
		}
	}

	/**
	 * Decode the response
	 *
	 * @param SimpleXMLElement $item	The item to decode.
	 * @param array[optional] $return	Just a placeholder.
	 * @param int[optional] $i			A internal counter.
	 * @return mixed
	 */
	private static function decodeResponse($item, $return = null, $i = 0)
	{
		$arrayKeys = array('barcode', 'orderLine');
		$integerKeys = array('totalPrice');

		if($item instanceof SimpleXMLElement)
		{
			foreach($item as $key => $value)
			{
				// empty
				if(isset($value['nil']) && (string) $value['nil'] === 'true') $return[$key] = null;

				// empty
				elseif(isset($value[0]) && (string) $value == '')
				{
					if(in_array($key, $arrayKeys))
					{
						$return[$key][] = self::decodeResponse($value);
					}

					else $return[$key] = self::decodeResponse($value, null, 1);
				}

				else
				{
					// arrays
					if(in_array($key, $arrayKeys))
					{
						$return[$key][] = (string) $value;
					}

					// booleans
					elseif((string) $value == 'true') $return[$key] = true;
					elseif((string) $value == 'false') $return[$key] = false;

					// integers
					elseif(in_array($key, $integerKeys)) $return[$key] = (int) $value;

					// fallback to string
					else $return[$key] = (string) $value;
				}
			}
		}

		else throw new bPostException('Invalid item.');

		return $return;
	}

	/**
	 * Make the call
	 *
	 * @param string $url					The URL to call.
	 * @param array[optional] $data			The data to pass.
	 * @param array[optional] $headers		The headers to pass.
	 * @param string[optional] $method		The HTTP-method to use.
	 * @param bool[optional] $expectXML		Do we expect XML?
	 * @return mixed
	 */
	private function doCall($url, $data = null, $headers = array(), $method = 'GET', $expectXML = true)
	{
		// any data?
		if($data !== null)
		{
			// init XML
			$xml = new DOMDocument('1.0', 'utf-8');

			// set some properties
			$xml->preserveWhiteSpace = false;
			$xml->formatOutput = true;

			// build data
			array_walk($data, array(__CLASS__, 'arrayToXML'), $xml);

			// store body
			$body = $xml->saveXML();
		}
		else $body = null;

		// build Authorization header
		$headers[] = 'Authorization: Basic ' . $this->getAuthorizationHeader();

		// set options
		$options[CURLOPT_URL] = self::API_URL . '/' . $this->accountId . $url;
		if($this->getPort() != 0) $options[CURLOPT_PORT] = $this->getPort();
		$options[CURLOPT_USERAGENT] = $this->getUserAgent();
		$options[CURLOPT_RETURNTRANSFER] = true;
		$options[CURLOPT_TIMEOUT] = (int) $this->getTimeOut();
		$options[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_1_1;
		$options[CURLOPT_HTTPHEADER] = $headers;

		// PUT
		if($method == 'PUT')
		{
			$options[CURLOPT_CUSTOMREQUEST] = 'PUT';
			if($body != null) $options[CURLOPT_POSTFIELDS] = $body;
		}
		if($method == 'POST')
		{
			$options[CURLOPT_POST] = true;
			$options[CURLOPT_POSTFIELDS] = $body;
		}

		// init
		$this->curl = curl_init();

		// set options
		curl_setopt_array($this->curl, $options);

		// execute
		$response = curl_exec($this->curl);
		$headers = curl_getinfo($this->curl);

		// fetch errors
		$errorNumber = curl_errno($this->curl);
		$errorMessage = curl_error($this->curl);

		// error?
		if($errorNumber != '')
		{
			// internal debugging enabled
			if(self::DEBUG)
			{
				echo '<pre>';
				var_dump(htmlentities($response));
				var_dump($this);
				echo '</pre>';
			}

			throw new bPostException($errorMessage, $errorNumber);
		}

		// valid HTTP-code
		if(!in_array($headers['http_code'], array(0, 200)))
		{
			// internal debugging enabled
			if(self::DEBUG)
			{
				echo '<pre>';
				var_dump($response);
				var_dump($headers);
				var_dump($this);
				echo '</pre>';
			}

			throw new bPostException('Invalid response.', $headers['http_code']);
		}

		// if we don't expect XML we can return the content here
		if(!$expectXML) return $response;

		// convert into XML
		$xml = simplexml_load_string($response);

		// validate
		if($xml->getName() == 'businessException')
		{
			// internal debugging enabled
			if(self::DEBUG)
			{
				echo '<pre>';
				var_dump($response);
				var_dump($headers);
				var_dump($this);
				echo '</pre>';
			}

			// message
			$message = (string) $response->Message;
			$code = (string) $response->Code;

			// throw exception
			throw new bPostException($message, $code);
		}

		// return the response
		return $xml;
	}

	/**
	 * Generate the secret string for the Authorization header
	 *
	 * @return string
	 */
	private function getAuthorizationHeader()
	{
		return base64_encode($this->accountId . ':' . $this->passphrase);
	}

	/**
	 * Get the port
	 *
	 * @return int
	 */
	public function getPort()
	{
		return (int) $this->port;
	}

	/**
	 * Get the timeout that will be used
	 *
	 * @return int
	 */
	public function getTimeOut()
	{
		return (int) $this->timeOut;
	}

	/**
	 * Get the useragent that will be used.
	 * Our version will be prepended to yours.
	 * It will look like: "PHP bPost/<version> <your-user-agent>"
	 *
	 * @return string
	 */
	public function getUserAgent()
	{
		return (string) 'PHP bPost/' . self::VERSION . ' ' . $this->userAgent;
	}

	/**
	 * Set the timeout
	 * After this time the request will stop. You should handle any errors triggered by this.
	 *
	 * @param int $seconds	The timeout in seconds.
	 */
	public function setTimeOut($seconds)
	{
		$this->timeOut = (int) $seconds;
	}

	/**
	 * Set the user-agent for you application
	 * It will be appended to ours, the result will look like: "PHP bPost/<version> <your-user-agent>"
	 *
	 * @param string $userAgent	Your user-agent, it should look like <app-name>/<app-version>.
	 */
	public function setUserAgent($userAgent)
	{
		$this->userAgent = (string) $userAgent;
	}

	// webservice methods
// orders
	/**
	 * Creates a new order. If an order with the same orderReference already exists
	 *
	 * @param bPostOrder $order
	 * @return bool
	 */
	public function createOrReplaceOrder(bPostOrder $order)
	{
		// build url
		$url = '/orders';

		// build data
		$data['order']['@attributes']['xmlns'] = 'http://schema.post.be/shm/deepintegration/v2/';
		$data['order']['value'] = $order->toXMLArray($this->accountId);

		// build headers
		$headers = array(
			'Content-type: application/vnd.bpost.shm-order-v2+XML'
		);

		// make the call
		return ($this->doCall($url, $data, $headers, 'POST', false) == '');
	}

	/**
	 * Fetch an order
	 *
	 * @param string $reference
	 * @return array
	 */
	public function fetchOrder($reference)
	{
		// build url
		$url = '/orders/' . (string) $reference;

		// make the call
		$return = self::decodeResponse($this->doCall($url));

		// for some reason the order-data is wrapped in an order tag sometimes.
		if(isset($return['order'])) $return = $return['order'];

		$order = new bPostOrder($return['orderReference']);

		if(self::DEBUG)
		{
			foreach($return as $key => $value)
			{
				if(!in_array($key, array('status', 'costCenter', 'orderLine', 'customer', 'deliveryMethod', 'totalPrice')))
				{
					var_dump($return);
					exit;
				}
			}
		}

		if(isset($return['status'])) $order->setStatus($return['status']);
		if(isset($return['costCenter'])) $order->setCostCenter($return['costCenter']);

		// order lines
		if(isset($return['orderLine']) && !empty($return['orderLine']))
		{
			foreach($return['orderLine'] as $row)
			{
				$order->addOrderLine($row['text'], $row['nbOfItems']);
			}
		}

		// customer
		if(isset($return['customer']))
		{
			// create customer
			$customer = new bPostCustomer($return['customer']['firstName'], $return['customer']['lastName']);
			if(isset($return['customer']['deliveryAddress']))
			{
				$address = new bPostAddress(
					$return['customer']['deliveryAddress']['streetName'],
					$return['customer']['deliveryAddress']['number'],
					$return['customer']['deliveryAddress']['postalCode'],
					$return['customer']['deliveryAddress']['locality'],
					$return['customer']['deliveryAddress']['countryCode']
				);
				if(isset($return['customer']['deliveryAddress']['box']))
				{
					$address->setBox($return['customer']['deliveryAddress']['box']);
				}
				$customer->setDeliveryAddress($address);
			}
			if(isset($return['customer']['email'])) $customer->setEmail($return['customer']['email']);
			if(isset($return['customer']['phoneNumber'])) $customer->setPhoneNumber($return['customer']['phoneNumber']);

			$order->setCustomer($customer);
		}

		// delivery method
		if(isset($return['deliveryMethod']))
		{
			// atHome?
			if(isset($return['deliveryMethod']['atHome']))
			{
				$deliveryMethod = new bPostDeliveryMethodAtHome();

				// options
				if(isset($return['deliveryMethod']['atHome']['normal']['options']) && !empty($return['deliveryMethod']['atHome']['normal']['options']))
				{
					$options = array();

					foreach($return['deliveryMethod']['atHome']['normal']['options'] as $key => $row)
					{
						$language = 'NL';	// @todo fix me
						$emailAddress = null;
						$mobilePhone = null;
						$fixedPhone = null;

						if(isset($row['emailAddress'])) $emailAddress = $row['emailAddress'];
						if(isset($row['mobilePhone'])) $mobilePhone = $row['mobilePhone'];
						if(isset($row['fixedPhone'])) $fixedPhone = $row['fixedPhone'];

						if($emailAddress === null && $mobilePhone === null && $fixedPhone === null) continue;

						$options[$key] = new bPostNotification($language, $emailAddress, $mobilePhone, $fixedPhone);
					}

					$deliveryMethod->setNormal($options);
				}

				$order->setDeliveryMethod($deliveryMethod);
			}

			else
			{
				// @todo	implemented other types
				var_dump($return);
				exit;
			}
		}
		if(isset($return['totalPrice'])) $order->setTotal($return['totalPrice']);

		return $order;
	}

	/**
	 * Modify the status for an order.
	 *
	 * @param string $reference		The reference for an order
	 * @param string $status		The new status, allowed values are: OPEN, PENDING, CANCELLED, COMPLETED or ON-HOLD
	 * @return bool
	 */
	public function modifyOrderStatus($reference, $status)
	{
		$allowedStatuses = array('OPEN', 'PENDING', 'CANCELLED', 'COMPLETED', 'ON-HOLD');
		$status = mb_strtoupper((string) $status);

		// validate
		if(!in_array($status, $allowedStatuses))
		{
			throw new bPostException(
				'Invalid status (' . $status . '), allowed values are: ' .
				implode(', ', $allowedStatuses) . '.'
			);
		}

		// build url
		$url = '/orders/status';

		// build data
		$data['orderStatusMap']['@attributes']['xmlns'] = 'http://schema.post.be/shm/deepintegration/v2/';
		$data['orderStatusMap']['entry']['orderReference'] = (string) $reference;
		$data['orderStatusMap']['entry']['status'] = $status;

		// build headers
		$headers = array(
			'X-HTTP-Method-Override: PATCH',
			'Content-type: application/vnd.bpost.shm-order-status-v2+XML'
		);

		// make the call
		return ($this->doCall($url, $data, $headers, 'PUT', false) == '');
	}

// labels
	/**
	 * Create a national label
	 *
	 * @param string $reference					Order reference: unique ID used in your web shop to assign to an order.
	 * @param int $amount						Amount of labels.
	 * @param bool[optional] $withRetour		Should the return labeks be included?
	 * @param bool[optional] $returnLabels		Should the labels be included?
	 * @param string[optional] $labelFormat		Format of the labels, possible values are: A_4, A_5.
	 * @return array
	 */
	public function createNationalLabel($reference, $amount, $withRetour = null, $returnLabels = null, $labelFormat = null)
	{
		$allowedLabelFormats = array('A_4', 'A_5');

		// validate
		if($labelFormat !== null && !in_array($labelFormat, $allowedLabelFormats))
		{
			throw new bPostException(
				'Invalid value for labelFormat (' . $labelFormat . '), allowed values are: ' .
				implode(', ', $allowedLabelFormats) . '.'
			);
		}

		// build url
		$url = '/labels';

		if($labelFormat !== null) $url .= '?labelFormat=' . $labelFormat;

		// build data
		$data['orderRefLabelAmountMap']['@attributes']['xmlns'] = 'http://schema.post.be/shm/deepintegration/v2/';
		$data['orderRefLabelAmountMap']['entry']['orderReference'] = (string) $reference;
		$data['orderRefLabelAmountMap']['entry']['labelAmount'] = (int) $amount;
		if($withRetour !== null) $data['orderRefLabelAmountMap']['entry']['withRetour'] = (bool) $withRetour;
		if($returnLabels !== null) $data['orderRefLabelAmountMap']['entry']['returnLabels'] = ($returnLabels) ? '1' : '0';

		// build headers
		$headers = array(
			'Content-type: application/vnd.bpost.shm-nat-label-v2+XML'
		);

		// make the call
		$return = self::decodeResponse($this->doCall($url, $data, $headers, 'POST'));

		// validate
		if(!isset($return['entry'])) throw new bPostException('Invalid response.');

		// return
		return $return['entry'];
	}

	public function createInternationalLabel($reference, bPostInternationalLabelInfo $labelInfo, $returnLabels = null)
	{

	}

	/**
	 * Create an order and the labels
	 *
	 * @param bPostOrder $order
	 * @param int $amount
	 * @return array
	 */
	public function createOrderAndNationalLabel(bPostOrder $order, $amount)
	{
		// build url
		$url = '/orderAndLabels';

		// build data
		$data['orderWithLabelAmount']['@attributes']['xmlns'] = 'http://schema.post.be/shm/deepintegration/v2/';
		$data['orderWithLabelAmount']['order'] = $order->toXMLArray($this->accountId);
		$data['orderWithLabelAmount']['labelAmount'] = (int) $amount;

		// build headers
		$headers = array(
			'Content-type: application/vnd.bpost.shm-orderAndNatLabels-v2+XML'
		);

		// make the call
		$return = self::decodeResponse($this->doCall($url, $data, $headers, 'POST'));

		// validate
		if(!isset($return['entry'])) throw new bPostException('Invalid response.');

		// return
		return $return['entry'];
	}

	public function createOrderAndInternationalLabel()
	{
		throw new bPostException('Not implemented.');
	}

	/**
	 * Retrieve a PDF-label for a box
	 *
	 * @param string $barcode					The barcode to retrieve
	 * @param string[optional] $labelFormat		Possible values are: A_4, A_5
	 * @return string
	 */
	public function retrievePDFLabelsForBox($barcode, $labelFormat = null)
	{
		$allowedLabelFormats = array('A_4', 'A_5');

		// validate
		if($labelFormat !== null && !in_array($labelFormat, $allowedLabelFormats))
		{
			throw new bPostException(
				'Invalid value for labelFormat (' . $labelFormat . '), allowed values are: ' .
				implode(', ', $allowedLabelFormats) . '.'
			);
		}

		// build url
		$url = '/labels/' . (string) $barcode . '/pdf';

		if($labelFormat !== null) $url .= '?labelFormat=' . $labelFormat;

		// build headers
		$headers = array(
			'Accept: application/vnd.bpost.shm-pdf-v2+XML'
		);

		// make the call
		return (string) $this->doCall($url, null, $headers);
	}

	/**
	 * Retrieve a PDF-label for an order
	 *
	 * @param string $reference
	 * @param string[optional] $labelFormat		Possible values are: A_4, A_5
	 * @return string
	 */
	public function retrievePDFLabelsForOrder($reference, $labelFormat = null)
	{
		$allowedLabelFormats = array('A_4', 'A_5');

		// validate
		if($labelFormat !== null && !in_array($labelFormat, $allowedLabelFormats))
		{
			throw new bPostException(
				'Invalid value for labelFormat (' . $labelFormat . '), allowed values are: ' .
				implode(', ', $allowedLabelFormats) . '.'
			);
		}

		// build url
		$url = '/orders/' . (string) $reference . '/pdf';

		if($labelFormat !== null) $url .= '?labelFormat=' . $labelFormat;

		// build headers
		$headers = array(
			'Accept: application/vnd.bpost.shm-pdf-v2+XML'
		);

		// make the call
		return (string) $this->doCall($url, null, $headers);
	}
}

/**
 * bPost Order class
 *
 * @author Tijs Verkoyen <php-bpost@verkoyen.eu>
 */
class bPostOrder
{
	/**
	 * Generic info
	 *
	 * @var string
	 */
	private $costCenter, $status, $reference;

	/**
	 * The order lines
	 * @var array
	 */
	private $lines;

	/**
	 * The customer
	 *
	 * @var bPostCustomer
	 */
	private $customer;

	/**
	 * The delivery method
	 *
	 * @var bPostDeliveryMethod
	 */
	private $deliveryMethod;

	/**
	 * The order total
	 *
	 * @var int
	 */
	private $total;

	/**
	 * Create an order
	 *
	 * @param string $reference
	 */
	public function __construct($reference)
	{
		$this->setReference($reference);
	}

	/**
	 * Add an order line
	 *
	 * @param string $text			Text describing the ordered item.
	 * @param int $numberOfItems	Number of items.
	 */
	public function addOrderLine($text, $numberOfItems)
	{
		$this->lines[] = array(
			'text' => (string) $text,
			'nbOfItems' => (int) $numberOfItems
		);
	}

	/**
	 * Get the cost center
	 * @return string
	 */
	public function getCostCenter()
	{
		return $this->costCenter;
	}

	/**
	 * Get the customer
	 *
	 * @return bPostCustomer
	 */
	public function getCustomer()
	{
		return $this->customer;
	}

	/**
	 * Get the delivery method
	 *
	 * @return bPostDeliveryMethod
	 */
	public function getDeliveryMethod()
	{
		return $this->deliveryMethod;
	}

	/**
	 * Get the order lines
	 *
	 * @return array
	 */
	public function getOrderLines()
	{
		return $this->lines;
	}

	/**
	 * Get the reference
	 *
	 * @return string
	 */
	public function getReference()
	{
		return $this->reference;
	}

	/**
	 * Get the total price of the order.
	 *
	 * @return int
	 */
	public function getTotal()
	{
		return $this->total;
	}

	/**
	 * Get the status
	 *
	 * @return string
	 */
	public function getStatus()
	{
		return $this->status;
	}

	/**
	 * Set teh cost center, will be used on your invoice and allows you to attribute different cost centers
	 *
	 * @param string $value
	 */
	public function setCostCenter($costCenter)
	{
		$this->costCenter = (string) $costCenter;
	}

	/**
	 * Set the customer
	 *
	 * @param bPostCustomer $value
	 */
	public function setCustomer(bPostCustomer $customer)
	{
		$this->customer = $customer;
	}

	/**
	 * Set the delivery method
	 *
	 * @param bPostDeliveryMethod $value
	 */
	public function setDeliveryMethod(bPostDeliveryMethod $deliveryMethod)
	{
		$this->deliveryMethod = $deliveryMethod;
	}

	/**
	 * Set the order reference, a unique id used in your web-shop.
	 * If the value already exists it will overwrite the current info.
	 *
	 * @param string $value
	 */
	public function setReference($reference)
	{
		$this->reference = (string) $reference;
	}

	/**
	 * The total price of the order in euro-cents (excluding shipping)
	 *
	 * @param int $value
	 */
	public function setTotal($total)
	{
		$this->total = (int) $total;
	}

	/**
	 * Set the order status
	 *
	 * @param string $value		Possible values are OPEN, PENDING, CANCELLED, COMPLETED, ON-HOLD.
	 */
	public function setStatus($status)
	{
		$allowedStatuses = array('OPEN', 'PENDING', 'CANCELLED', 'COMPLETED', 'ON-HOLD');

		// validate
		if(!in_array($status, $allowedStatuses))
		{
			throw new bPostException(
				'Invalid status (' . $status . '), possible values are: ' . implode(', ', $allowedStatuses) . '.'
			);
		}

		$this->status = $status;
	}

	/**
	 * Return the object as an array for usage in the XML
	 *
	 * @param string accountId
	 * @return array
	 */
	public function toXMLArray($accountId)
	{
		$data = array();
		$data['@attributes']['xmlns'] = 'http://schema.post.be/shm/deepintegration/v2/';
		$data['accountId'] = (string) $accountId;
		if($this->reference !== null) $data['orderReference'] = $this->reference;
		if($this->status !== null) $data['status'] = $this->status;
		if($this->costCenter !== null) $data['costCenter'] = $this->costCenter;

		if(!empty($this->lines))
		{
			foreach($this->lines as $line)
			{
				$data['orderLine'][] = $line;
			}
		}

		if($this->customer !== null) $data['customer'] = $this->customer->toXMLArray();
		if($this->deliveryMethod !== null) $data['deliveryMethod'] = $this->deliveryMethod->toXMLArray();
		if($this->total !== null) $data['totalPrice'] = $this->total;

		return $data;
	}
}

/**
 * bPost Customer class
 *
 * @author Tijs Verkoyen <php-bpost@verkoyen.eu>
 */
class bPostCustomer
{
	/**
	 * Generic info
	 *
	 * @var string
	 */
	private $firstName, $lastName, $email, $phoneNumber;

	/**
	 * The address
	 *
	 * @var bPostAddress
	 */
	private $deliveryAddress;

	/**
	 * Create a customer
	 *
	 * @param string $firstName
	 * @param string $lastName
	 */
	public function __construct($firstName, $lastName)
	{
		$this->setFirstName($firstName);
		$this->setLastName($lastName);
	}

	/**
	 * Get the delivery address
	 *
	 * @return bPostAddress
	 */
	public function getDeliveryAddress()
	{
		return $this->deliveryAddress;
	}

	/**
	 * Get the email
	 *
	 * @return string
	 */
	public function getEmail()
	{
		return $this->email;
	}

	/**
	 * Get the first name
	 *
	 * @return string
	 */
	public function getFirstName()
	{
		return $this->firstName;
	}

	/**
	 * Get the last name
	 *
	 * @return string
	 */
	public function getLastName()
	{
		return $this->lastName;
	}

	/**
	 * Get the phone number
	 *
	 * @return string
	 */
	public function getPhoneNumber()
	{
		return $this->phoneNumber;
	}

	/**
	 * Set the delivery address
	 *
	 * @param bPostAddress $deliveryAddress
	 */
	public function setDeliveryAddress($deliveryAddress)
	{
		$this->deliveryAddress = $deliveryAddress;
	}

	/**
	 * Set the email
	 *
	 * @param string $email
	 */
	public function setEmail($email)
	{
		if(mb_strlen($email) > 50) throw new bPostException('Invalid length for email, maximum is 50.');
		$this->email = $email;
	}

	/**
	 * Set the first name
	 *
	 * @param string $firstName
	 */
	public function setFirstName($firstName)
	{
		if(mb_strlen($firstName) > 40) throw new bPostException('Invalid length for firstName, maximum is 40.');
		$this->firstName = $firstName;
	}

	/**
	 * Set the last name
	 *
	 * @param string $lastName
	 */
	public function setLastName($lastName)
	{
		if(mb_strlen($lastName) > 40) throw new bPostException('Invalid length for lastName, maximum is 40.');
		$this->lastName = $lastName;
	}

	/**
	 * Set the phone number
	 *
	 * @param string $phoneNumber
	 */
	public function setPhoneNumber($phoneNumber)
	{
		if(mb_strlen($phoneNumber) > 20) throw new bPostException('Invalid length for phone number, maximum is 20.');
		$this->phoneNumber = $phoneNumber;
	}

	/**
	 * Return the object as an array for usage in the XML
	 *
	 * @return array
	 */
	public function toXMLArray()
	{
		$data = array();
		if($this->firstName !== null) $data['firstName'] = $this->firstName;
		if($this->lastName !== null) $data['lastName'] = $this->lastName;
		if($this->deliveryAddress !== null) $data['deliveryAddress'] = $this->deliveryAddress->toXMLArray();
		if($this->email !== null) $data['email'] = $this->email;
		if($this->phoneNumber !== null) $data['phoneNumber'] = $this->phoneNumber;

		return $data;
	}
}

/**
 * bPost Address class
 *
 * @author Tijs Verkoyen <php-bpost@verkoyen.eu>
 */
class bPostAddress
{
	/**
	 * Generic info
	 *
	 * @var string
	 */
	private $streetName, $number, $box, $postcalCode, $locality, $countryCode;

	/**
	 * Create a Address object
	 *
	 * @param string $streetName
	 * @param string $number
	 * @param string $postalCode
	 * @param string $locality
	 * @param string[optional] $countryCode
	 */
	public function __construct($streetName, $number, $postalCode, $locality, $countryCode = 'BE')
	{
		$this->setStreetName($streetName);
		$this->setNumber($number);
		$this->setPostcalCode($postalCode);
		$this->setLocality($locality);
		$this->setCountryCode($countryCode);
	}

	/**
	 * Get the box
	 *
	 * @return string
	 */
	public function getBox()
	{
		return $this->box;
	}

	/**
	 * Get the country code
	 *
	 * @return string
	 */
	public function getCountryCode()
	{
		return $this->countryCode;
	}

	/**
	 * Get the locality
	 *
	 * @return string
	 */
	public function getLocality()
	{
		return $this->locality;
	}

	/**
	 * Get the number
	 *
	 * @return string
	 */
	public function getNumber()
	{
		return $this->number;
	}

	/**
	 * Get the postal code
	 *
	 * @return string
	 */
	public function getPostcalCode()
	{
		return $this->postcalCode;
	}

	/**
	 * Get the street name
	 *
	 * @return string
	 */
	public function getStreetName()
	{
		return $this->streetName;
	}

	/**
	 * Set the box
	 *
	 * @param string $box
	 */
	public function setBox($box)
	{
		if(mb_strlen($box) > 8) throw new bPostException('Invalid length for box, maximum is 8.');
		$this->box = $box;
	}

	/**
	 * Set the country code
	 *
	 * @param string $countryCode
	 */
	public function setCountryCode($countryCode)
	{
		$this->countryCode = $countryCode;
	}

	/**
	 * Set the locality
	 *
	 * @param string $locality
	 */
	public function setLocality($locality)
	{
		if(mb_strlen($locality) > 40) throw new bPostException('Invalid length for locality, maximum is 40.');
		$this->locality = $locality;
	}

	/**
	 * Set the number
	 *
	 * @param string $number
	 */
	public function setNumber($number)
	{
		if(mb_strlen($number) > 8) throw new bPostException('Invalid length for number, maximum is 8.');
		$this->number = $number;
	}

	/**
	 * Set the postal code
	 *
	 * @param string $postcalCode
	 */
	public function setPostcalCode($postcalCode)
	{
		if(mb_strlen($postcalCode) > 8) throw new bPostException('Invalid length for postalCode, maximum is 8.');
		$this->postcalCode = $postcalCode;
	}

	/**
	 * Set the street name
	 * @param string $streetName
	 */
	public function setStreetName($streetName)
	{
		if(mb_strlen($streetName) > 40) throw new bPostException('Invalid length for streetName, maximum is 40.');
		$this->streetName = $streetName;
	}

	/**
	 * Return the object as an array for usage in the XML
	 *
	 * @return array
	 */
	public function toXMLArray()
	{
		$data = array();
		if($this->streetName !== null) $data['streetName'] = $this->streetName;
		if($this->number !== null) $data['number'] = $this->number;
		if($this->box !== null) $data['box'] = $this->box;
		if($this->postcalCode !== null) $data['postalCode'] = $this->postcalCode;
		if($this->locality !== null) $data['locality'] = $this->locality;
		if($this->countryCode !== null) $data['countryCode'] = $this->countryCode;

		return $data;
	}
}

/**
 * bPost Delivery Method class
 *
 * @author Tijs Verkoyen <php-bpost@verkoyen.eu>
 */
class bPostDeliveryMethod
{
	private $insurance;

	/**
	 * Set the insurance level
	 *
	 * @param int $level	Level from 0 to 11.
	 */
	public function setInsurance($level = 0)
	{
		if((int) $level > 11) throw new bPostException('Invalid value () for level.');
		$this->insurance = $level;
	}

	/**
	 * Return the object as an array for usage in the XML
	 *
	 * @return array
	 */
	public function toXMLArray()
	{
		// build data
		$data = array();
		if($this->insurance !== null)
		{
			if($this->insurance == 0) $data['insurance']['basicInsurance'] = '';
			else $data['insurance']['additionalInsurance']['@attributes']['value'] = $this->insurance;
		}

		return $data;
	}
}

/**
 * bPost Delivery At Home Method class
 *
 * @author Tijs Verkoyen <php-bpost@verkoyen.eu>
 */
class bPostDeliveryMethodAtHome extends bPostDeliveryMethod
{
	// @todo	implement me correctly

	private $normal, $signed, $insured, $dropAtTheDoor;

	/**
	 * Default constructor
	 */
	public function __construct()
	{
		$this->setNormal();
	}

	/**
	 * Set normal
	 *
	 * @param array $options
	 */
	public function setNormal(array $options = null)
	{
		if($options !== null)
		{
			foreach($options as $key => $value) $this->normal[$key] = $value;
		}
	}

	/**
	 * Return the object as an array for usage in the XML
	 *
	 * @return array
	 */
	public function toXMLArray()
	{
		$data = array();
		$data['atHome'] = parent::toXMLArray();
		if($this->normal === null) $data['atHome']['normal'] = '';
		else
		{
			foreach($this->normal as $key => $value)
			{
				if($key == 'automaticSecondPresentation') $data['atHome']['normal']['options']['automaticSecondPresentation'] = $value;
				else $data['atHome']['normal']['options'][$key] = $value->toXMLArray();
			}
		}

		return $data;
	}
}

/**
 * bPost Delivery At Shop Method class
 *
 * @author Tijs Verkoyen <php-bpost@verkoyen.eu>
 */
class bPostDeliveryMethodAtShop extends bPostDeliveryMethod
{
	private $infoPugo, $insurance, $infoDistributed;
}

/**
 * bPost Delivery At 24/7 Method class
 *
 * @author Tijs Verkoyen <php-bpost@verkoyen.eu>
 */
class bPostDeliveryMethodAt247 extends bPostDeliveryMethod
{
	private $infoParcelsDepot, $signature, $insurance, $memberId;
}

/**
 * bPost Delivery International Express Method class
 *
 * @author Tijs Verkoyen <php-bpost@verkoyen.eu>
 */
class bPostDeliveryMethodIntExpress extends bPostDeliveryMethod
{
	private $insured;
}

/**
 * bPost Delivery International Business Method class
 *
 * @author Tijs Verkoyen <php-bpost@verkoyen.eu>
 */
class bPostDeliveryMethodIntBusiness extends bPostDeliveryMethod
{
	private $insured;
}

/**
 * bPost Notification class
 *
 * @author Tijs Verkoyen <php-bpost@verkoyen.eu>
 */
class bPostNotification
{
	/**
	 * Generic info
	 *
	 * @var strings
	 */
	private $emailAddress, $mobilePhone, $fixedPhone, $language;

	/**
	 * Create a notification
	 *
	 * @param string $language
	 * @param string[otpional] $emailAddress
	 * @param string[otpional] $mobilePhone
	 * @param string[otpional] $fixedPhone
	 */
	public function __construct($language, $emailAddress = null, $mobilePhone = null, $fixedPhone = null)
	{
		if(
			$emailAddress !== null && $mobilePhone !== null ||
			$emailAddress !== null && $fixedPhone !== null ||
			$mobilePhone !== null && $fixedPhone !== null ||
			$fixedPhone !== null && $mobilePhone !== null
		)
		{
			throw new bPostException('You can\'t specify multiple notifications.');
		}

		$this->setLanguage($language);
		if($emailAddress !== null) $this->setEmailAddress($emailAddress);
		if($mobilePhone !== null) $this->setMobilePhone($mobilePhone);
		if($fixedPhone !== null) $this->setFixedPhone($fixedPhone);
	}

	/**
	 * Get the email address
	 *
	 * @return strings
	 */
	public function getEmailAddress()
	{
		return $this->emailAddress;
	}

	/**
	 * Get the fixed phone
	 *
	 * @return strings
	 */
	public function getFixedPhone()
	{
		return $this->fixedPhone;
	}

	/**
	 * Get the language
	 *
	 * @return strings
	 */
	public function getLanguage()
	{
		return $this->language;
	}

	/**
	 * Get the mobile phone
	 *
	 * @return strings
	 */
	public function getMobilePhone()
	{
		return $this->mobilePhone;
	}

	/**
	 * Set the email address
	 *
	 * @param strings $emailAddress
	 */
	public function setEmailAddress($emailAddress)
	{
		if(mb_strlen($emailAddress) > 50) throw new bPostException('Invalid length for emailAddress, maximum is 50.');
		$this->emailAddress = $emailAddress;
	}

	/**
	 * Set the fixed phone
	 *
	 * @param strings $fixedPhone
	 */
	public function setFixedPhone($fixedPhone)
	{
		if(mb_strlen($fixedPhone) > 20) throw new bPostException('Invalid length for fixedPhone, maximum is 20.');
		$this->fixedPhone = $fixedPhone;
	}

	/**
	 * Set the language
	 *
	 * @param strings $language		Allowed values are EN, NL, FR, DE.
	 */
	public function setLanguage($language)
	{
		$allowedLanguages = array('EN', 'NL', 'FR', 'DE');

		// validate
		if(!in_array($language, $allowedLanguages))
		{
			throw new bPostException(
				'Invalid value for language (' . $language . '), allowed values are: ' .
				implode(',  ', $allowedLanguages) . '.'
			);
		}
		$this->language = $language;
	}

	/**
	 * Set the mobile phone
	 *
	 * @param strings $mobilePhone
	 */
	public function setMobilePhone($mobilePhone)
	{
		if(mb_strlen($mobilePhone) > 20) throw new bPostException('Invalid length for mobilePhone, maximum is 20.');
		$this->mobilePhone = $mobilePhone;
	}

	/**
	 * Return the object as an array for usage in the XML
	 *
	 * @return array
	 */
	public function toXMLArray()
	{
		$data = array();
		$data['@attributes']['language'] = $this->language;

		if(isset($this->emailAddress)) $data['emailAddress'] = $this->emailAddress;
		if(isset($this->mobilePhone)) $data['mobilePhone'] = $this->mobilePhone;
		if(isset($this->fixedPhone)) $data['fixedPhone'] = $this->fixedPhone;

		return $data;
	}
}

/**
 * bPost Exception class
 *
 * @author Tijs Verkoyen <php-bpost@verkoyen.eu>
 */
class bPostException extends Exception
{}