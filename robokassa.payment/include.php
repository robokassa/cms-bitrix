<?php

use Bitrix\Main\Config\Option;

/**
 * Класс-помощник для работы с формированием запроса к робокассе
 * с поддержкой ФЗ-54
 */
class RobokassaPaymentService { 
    
    const NO_VAT= "none";
    const VAT_0= "vat0";
    const VAT_10= "vat10";
    const VAT_18= "vat18";
    const VAT_20= "vat20";

    const SECOND_CHECK_URL = 'https://ws.roboxchange.com/RoboFiscal/Receipt/Attach';
   
    static $moduleId = 'ipol.robokassa';
    
    /**
     * Возвращает ID модуля
     * @return string
     */
    public static function getModuleId()
    { 
        return self::$moduleId;
    }

    /**
     * Генерация блока Receipt
     * @param  Payment $payment
     * @param  integer $paymentShouldPay
     * @param  RobokassaHandler $handler
     * @return array
     */
    public static function formReceiptData($payment, $paymentShouldPay, $handler)
    {
        $items = array();
        $shipmentCollection = 
            $payment->getCollection()->getOrder()->getShipmentCollection();
        
        //получаем настройки платежки
        $paymentMethod = $handler->getPaymentMethod($payment);
        $paymentObject = $handler->getPaymentObject($payment);
        $paymentObjectDelivery = $handler->getPaymentObjectDelivery($payment);

        foreach ($shipmentCollection as $shipmentItem) {
            
            $shipmentItemColletion = $shipmentItem->getShipmentItemCollection();
            
            foreach ($shipmentItemColletion as $elem) {

                $basketItem = $elem->getBasketItem(); 

                if ($basketItem->isBundleChild()) {
                    continue;
                }

                if (!$basketItem->getFinalPrice()) {
                    continue;
                }

                $items[] = [
                    'name' => substr($basketItem->getField('NAME'), 0, 64),
                    'quantity' => $elem->getQuantity(),
                    'sum' => \Bitrix\Sale\PriceMaths::roundPrecision($basketItem->getFinalPrice()
                    ),
                    'tax' => self::getProductTax($basketItem),
                    'payment_method' => $paymentMethod,
                    'payment_object' => $paymentObject
                ];
            } 

            if (!$shipmentItem->isSystem() && $shipmentItem->getPrice()) {
                $items[]=[ 
                    'name' => substr($shipmentItem->getDeliveryName(), 0, 64),
                    'quantity' => 1,
                    'sum' => \Bitrix\Sale\PriceMaths::roundPrecision(
                        $shipmentItem->getPrice()
                    ),
                    'tax' => self::getShipmentTax($shipmentItem),
                    'payment_method' => $paymentMethod,
                    'payment_object' => $paymentObjectDelivery
                ];
            }
        }

        //сверяем суммы
        $items = self::sumCorrection($items, $paymentShouldPay);
        return $items;
    }

    /**
     * Конвертация налоговой ставки товара в понятный робокассе
     * @param  Sale\BasketItem $basketItem
     * @return string
     */
    public static function getProductTax($basketItem)
    {
        if (\Bitrix\Main\Loader::includeModule('catalog')) {
            $result = \CCatalogProduct::GetVATInfo($basketItem->getProductId());
            $bitrixVat = $result->Fetch();
            $covertedVat = self::toRobokassaTax($bitrixVat);
            return $covertedVat;
        } 
        return self::NO_VAT;
    }

    /**
     * Конвертация налоговой ставки доставки в понятный робокассе
     * @param  \Bitrix\Sale\Shipment $shipmentItem
     * @return string
     */
    public static function getShipmentTax($shipmentItem)
    {
        
        $delivery = \Bitrix\Sale\Delivery\Services\Manager::getById(
            $shipmentItem->getDeliveryId()
        );
        
        if(is_null($delivery['VAT_ID'])){
            return self::NO_VAT;
        }

        $bitrixVat = CCatalogVat::GetByID($delivery['VAT_ID'])->Fetch();
        return self::toRobokassaTax($bitrixVat);
    }

    /**
     * Конвертация налоговой ставки в понятный робокассе строковой
     * @param CCatalogVat $bitrixVat
     * @return string
     */
    public static function toRobokassaTax($bitrixVat)
    {
        if ($bitrixVat['NAME'] == GetMessage('ROBOKASSA.NO_NDS')){
            $convertedVat = self::NO_VAT;
        } else { 
            $rate = intval($bitrixVat['RATE']);
            switch ($rate) {

                case 0:
                    $convertedVat= self::VAT_0;
                    break;

                case 10:
                    $convertedVat = self::VAT_10;
                    break;

                case 18:
                    $convertedVat = self::VAT_18;
                    break;
            
                case 20:
                    $convertedVat = self::VAT_20;
                    break;

                default:
                    $convertedVat = self::NO_VAT;
                    break;
            }
        }
        return $convertedVat;
    }

    /**
     * Сверка сумм и пересчет если нужно
     * @param  array $items
     * @param  iteger $paymentShouldPay
     * @return array
     */
    public static function sumCorrection($items, $paymentShouldPay) 
    {
        $result = array();
        $totalSum = 0;
        foreach($items as $item){
            $totalSum += $item['sum'];
        }

        if (abs($totalSum - $paymentShouldPay) > 0.0001) {
            $roundedSum = 0;
            $rate = $paymentShouldPay / $totalSum;
            $lastIndex = count($items) - 1;

            foreach ($items as $key => $item) {
                if ($key == $lastIndex) {
                    $roundedValue = round(
                        $paymentShouldPay - $roundedSum, 
                        2
                    );
                    $item['sum'] = number_format(
                        $roundedValue, 
                        '2', 
                        '.', 
                        ''
                    );
                    $result[]= $item;
                } else { 
                    $roundedValue = round(
                        $item['sum']* $rate, 2
                    );
                    $item['sum'] = number_format(
                        $roundedValue,
                        '2', 
                        '.', 
                        ''
                    );
                    $roundedSum += $roundedValue; 
                    $result[] = $item;
                }
            }
        } else { 
            $result = $items;
        }
        return $result;
    }

	/**
	 * Подготовка строки перед кодированием в base64
	 * @param $string
	 * @return string
	 */
    protected static function formatSignReplace($string)
    {
    	return \strtr(
    		$string,
		    [
			    '+' => '-',
		        '/' => '_',
		    ]
	    );
    }

	/**
	 * Подготовка строки после кодирования в base64
	 * @param $string
	 * @return string
	 */
    protected static function formatSignFinish($string)
    {
    	return \preg_replace('/^(.*?)(=*)$/', '$1', $string);
    }

	/**
	 * Отправка 2го чека
	 * @param $orderId
	 * @param $newStatus
	 * @throws \Bitrix\Main\ArgumentNullException
	 * @throws \Bitrix\Main\ArgumentOutOfRangeException
	 * @throws \Bitrix\Main\LoaderException
	 */
    public static function sendSecondCheck($orderId, $newStatus)
    {

	    if(Option::get(self::getModuleId(), 'SECOND_CHECK_STATUS_ID', '') === $newStatus)
	    {

	    	$productProperty = Option::get(self::getModuleId(), 'SECOND_CHECK_PROPERTY_CODE', '');

	    	\Bitrix\Main\Loader::includeModule('sale');
	    	\Bitrix\Main\Loader::includeModule('iblock');

	    	/** @var \Bitrix\Sale\Order $order */
	    	$order = \Bitrix\Sale\Order::load($orderId);

	    	/** @var \Bitrix\Sale\Payment $payment */
	        foreach ($order->getPaymentCollection() as $payment)
		    {

		    	if(
		    		$payment->getPaySystem()->getField('ACTION_FILE') === 'robokassapayment'
				    && $payment->isPaid()
			    )
			    {

			    	/** @var array $params */
				    $params = $payment->getPaySystem()->getParamsBusValue($payment);

				    /** @var array $fields */
				    $fields = [
				    	'merchantId' => $params['SHOPLOGIN'],
					    'id' => $payment->getId() + 1,
					    'originId' => $payment->getId(),
					    'operation' => 'sell',
					    'sno' => $params['SNO'],
					    'url' => \urlencode('http://' . $_SERVER['HTTP_HOST']),
					    'total' => $payment->getSum(),
					    'items' => [],
					    'client' => [
					    	'email' => $order->getPropertyCollection()->getUserEmail()->getValue(),
					    	'phone' => $order->getPropertyCollection()->getPhone()->getValue(),
					    ],
					    'payments' => [
						    [
						    	'type' => 2,
							    'sum' => $payment->getSum()
						    ]
					    ],
					    'vats' => []
				    ];

				    /** @var \Bitrix\Sale\BasketItem $basketItem */
				    foreach ($order->getBasket()->getBasketItems() as $basketItem)
				    {

				    	$productTax = self::getProductTax($basketItem);

					    $product = [
						    'name' => substr(mb_convert_encoding($basketItem->getField('NAME'), 'UTF-8'), 0, 64),
						    'quantity' => $basketItem->getQuantity(),
						    'sum' => \Bitrix\Sale\PriceMaths::roundPrecision($basketItem->getFinalPrice()),
						    'tax' => $productTax,
						    'payment_method' => 'full_prepayment',
						    'payment_object' => $params['PAYMENT_OBJECT'],
					    ];

					    if(strlen($productProperty) > 0)
					    {

					    	$element = \CIBlockElement::GetByID($basketItem->getProductId())->GetNextElement();
					    	$property = $element->GetProperties([], ['CODE' => $productProperty]);

					    	if(!empty($property['ARTNUMBER']) && \is_array($property['ARTNUMBER']) && strlen($property['ARTNUMBER']['VALUE']) > 0)
						    	$product['nomenclature_code'] = mb_convert_encoding($property['ARTNUMBER']['VALUE'], 'UTF-8');
					    }

					    $fields['items'][] = $product;

					    switch ($productTax)
					    {

						    case self::VAT_0:
						    case self::NO_VAT:
							    $fields['vats'][] = ['type' => $productTax, 'sum' => 0];
						    break;

						    default:
							    $fields['vats'][] = ['type' => self::NO_VAT, 'sum' => 0];
						    break;

						    case self::VAT_10:
						    case self::VAT_18:
						    case self::VAT_20:
							    $fields['vats'][] = ['type' => $productTax, 'sum' => $basketItem->getVat()];
						    break;
					    }
				    }

				    /** @var string $startupHash */
				    $startupHash = self::formatSignFinish(
				    	\base64_encode(
				    		self::formatSignReplace(
							    \Bitrix\Main\Web\Json::encode($fields)
						    )
					    )
				    );

				    /** @var string $sign */
				    $sign = self::formatSignFinish(
				    	\base64_encode(
				    	    \md5(
				    	    	$startupHash .
						        ($params['PS_IS_TEST'] === 'Y' ? $params['SHOPPASSWORD_TEST'] : $params['SHOPPASSWORD'])
					        )
					    )
				    );

				    $client = new \Bitrix\Main\Web\HttpClient();
				    $client->post(self::SECOND_CHECK_URL, $startupHash . '.' . $sign);
			    }
		    }
	    }
    }
}