<?php
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
   
    static $moduleId = 'robokassa.payment';
    
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
                        ',', 
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
                        ',', 
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
}