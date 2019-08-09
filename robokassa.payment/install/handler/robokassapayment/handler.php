<?php

namespace Sale\Handlers\PaySystem;

use Bitrix\Main\Error;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Request;
use Bitrix\Main\Type\DateTime;
use Bitrix\Sale\Payment;
use Bitrix\Sale\PaySystem;
use Bitrix\Sale\PriceMaths;

Loc::loadMessages(__FILE__);
\CModule::IncludeModule("robokassa.payment");

class RobokassaPaymentHandler extends PaySystem\ServiceHandler
{

	/**
	 * @param Payment $payment
	 * @param Request|null $request
	 * @return PaySystem\ServiceResult
	 */
	public function initiatePay(Payment $payment, Request $request = null)
	{
		$test = '';
		if ($this->isTestMode($payment)) {
			$test = '_TEST';
		}

		$paymentShouldPay = (float) PriceMaths::roundPrecision($this->getBusinessValue($payment, 'PAYMENT_SHOULD_PAY'));

		$receipt = \Bitrix\Main\Web\Json::encode([
			'sno'   => $this->getBusinessValue($payment, 'SNO'),
			'items' => \RobokassaPaymentService::formReceiptData(
				$payment, $paymentShouldPay, $this
			),
		]);

		$signatureValue = md5(
			$this->getBusinessValue($payment, 'SHOPLOGIN') . ":" .
			$paymentShouldPay . ":" .
			$this->getBusinessValue($payment, 'PAYMENT_ID') . ":" .
			$receipt . ":" .
			$this->getBusinessValue($payment, 'SHOPPASSWORD' . $test) . ':' .
			'SHP_BX_PAYSYSTEM_CODE=' . $payment->getPaymentSystemId() . ':' .
			'SHP_HANDLER=ROBOKASSA.PAYMENT'
		);

		$params = array(
			'URL'                => $this->getUrl($payment, 'pay'),
			'SIGNATURE_VALUE'    => $signatureValue,
			'RECEIPT'            => $receipt,
			'BX_PAYSYSTEM_CODE'  => $payment->getPaymentSystemId(),
			'PAYMENT_SHOULD_PAY' => $paymentShouldPay,
		);

		if ($this->getBusinessValue($payment, 'LOG_REQUESTS') == 'Y') {
			$this->logToFile('GENERATE FORM', $params);
		}

		$this->setExtraParams($params);

		return $this->showTemplate($payment, "template");
	}

	/**
	 * @return array
	 */
	public function getCurrencyList()
	{
		return array('RUB');
	}

	/**
	 * @return array
	 */
	public static function getIndicativeFields()
	{
		return array('SHP_HANDLER' => 'ROBOKASSA.PAYMENT');
	}

	/**
	 * @param Request $request
	 * @param $paySystemId
	 * @return bool
	 */
	protected static function isMyResponseExtended(Request $request, $paySystemId)
	{
		$id = $request->get('SHP_BX_PAYSYSTEM_CODE');
		return $id == $paySystemId;
	}

	/**
	 * @param Payment $payment
	 * @param $request
	 * @return bool
	 */
	private function checkHash(Payment $payment, Request $request)
	{
		$test = '';
		if ($this->isTestMode($payment)) {
			$test = '_TEST';
		}

		$hash = md5($request->get('OutSum') . ":" . $request->get('InvId') . ":" . $this->getBusinessValue($payment, 'SHOPPASSWORD2' . $test) . ':SHP_BX_PAYSYSTEM_CODE=' . $payment->getPaymentSystemId() . ':SHP_HANDLER=ROBOKASSA.PAYMENT');

		return ToUpper($hash) == ToUpper($request->get('SignatureValue'));
	}

	/**
	 * @param Payment $payment
	 * @param Request $request
	 * @return bool
	 */
	private function checkSum(Payment $payment, Request $request)
	{
		$sum        = PriceMaths::roundPrecision($request->get('OutSum'));
		$paymentSum = PriceMaths::roundPrecision($this->getBusinessValue($payment, 'PAYMENT_SHOULD_PAY'));

		return $paymentSum == $sum;
	}

	/**
	 * @param Request $request
	 * @return mixed
	 */
	public function getPaymentIdFromRequest(Request $request)
	{
		return $request->get('InvId');
	}


	public function getPaymentMethod(Payment $payment)
	{
		$paymentMethod = $this->getBusinessValue($payment, 'PAYMENT_METHOD');
        if (!$paymentMethod) {
            $paymentMethod = 'full_prepayment';
        }
        return $paymentMethod;
	}

	public function getPaymentObject(Payment $payment)
	{
		$paymentObject = $this->getBusinessValue($payment, 'PAYMENT_OBJECT');
        if (!$paymentObject) {
            $paymentObject = 'commodity';
        }
        return $paymentObject;
	}

	public function getPaymentObjectDelivery(Payment $payment)
	{
		$paymentObjectDelivery = $this->getBusinessValue($payment, 
            'PAYMENT_OBJECT_DELIVERY'
        );
        if (!$paymentObjectDelivery) {
            $paymentObjectDelivery = 'commodity';
        }
        return $paymentObjectDelivery;
	}

	/**
	 * @return mixed
	 */
	protected function getUrlList()
	{
		return array(
			'pay' => array(
				self::ACTIVE_URL => 'https://auth.robokassa.ru/Merchant/Index.aspx',
			),
		);
	}

	/**
	 * @param Payment $payment
	 * @param Request $request
	 * @return PaySystem\ServiceResult
	 */
	public function processRequest(Payment $payment, Request $request)
	{

		if ($this->getBusinessValue($payment, 'LOG_REQUESTS') == 'Y') {
			$this->logToFile('CALLBACK', $_POST);
		}

		$result = new PaySystem\ServiceResult();

		if ($this->checkHash($payment, $request)) {
			return $this->processNoticeAction($payment, $request);
		} else {
			PaySystem\ErrorLog::add(array(
				'ACTION'  => 'processRequest',
				'MESSAGE' => 'Incorrect hash',
			));
			$result->addError(new Error('Incorrect hash'));
		}

		return $result;
	}

	/**
	 * @param Payment $payment
	 * @param Request $request
	 * @return PaySystem\ServiceResult
	 */
	private function processNoticeAction(Payment $payment, Request $request)
	{
		$result = new PaySystem\ServiceResult();

		$psStatusDescription = Loc::getMessage('SALE_HPS_ROBOXCHANGE_RES_NUMBER') . ": " . $request->get('InvId');
		$psStatusDescription .= "; " . Loc::getMessage('SALE_HPS_ROBOXCHANGE_RES_DATEPAY') . ": " . date("d.m.Y H:i:s");

		if ($request->get("IncCurrLabel") !== null) {
			$psStatusDescription .= "; " . Loc::getMessage('SALE_HPS_ROBOXCHANGE_RES_PAY_TYPE') . ": " . $request->get("IncCurrLabel");
		}

		$fields = array(
			"PS_STATUS"             => "Y",
			"PS_STATUS_CODE"        => "-",
			"PS_STATUS_DESCRIPTION" => $psStatusDescription,
			"PS_STATUS_MESSAGE"     => Loc::getMessage('SALE_HPS_ROBOXCHANGE_RES_PAYED'),
			"PS_SUM"                => $request->get('OutSum'),
			"PS_CURRENCY"           => $this->getBusinessValue($payment, "PAYMENT_CURRENCY"),
			"PS_RESPONSE_DATE"      => new DateTime(),
		);

		$result->setPsData($fields);

		if ($this->checkSum($payment, $request)) {
			$result->setOperationType(PaySystem\ServiceResult::MONEY_COMING);
		} else {
			PaySystem\ErrorLog::add(array(
				'ACTION'  => 'processNoticeAction',
				'MESSAGE' => 'Incorrect sum',
			));
			$result->addError(new Error('Incorrect sum'));
		}

		return $result;
	}

	/**
	 * @param Payment $payment
	 * @return bool
	 */
	protected function isTestMode(Payment $payment = null)
	{
		return ($this->getBusinessValue($payment, 'PS_IS_TEST') == 'Y');
	}

	/**
	 * @param PaySystem\ServiceResult $result
	 * @param Request $request
	 * @return mixed
	 */
	public function sendResponse(PaySystem\ServiceResult $result, Request $request)
	{
		global $APPLICATION;
		if ($result->isResultApplied()) {
			$APPLICATION->RestartBuffer();
			echo 'OK' . $request->get('InvId');
		}
	}

	/**
	 * @param  string $message
	 * @param  array  $data
	 * @return void
	 */
	private function logToFile($message, $data = []) {
		$message = '['.date('Y-m-d H:i:s').'] ACTION '.$message;
		if ($data) {
			$message .= ". DATA:\n".print_r($data, true);
		}
		$message .= "\n";
		file_put_contents(
			$_SERVER['DOCUMENT_ROOT'].'/robokassa.log',
			$message,
			FILE_APPEND
		);
	}
}
