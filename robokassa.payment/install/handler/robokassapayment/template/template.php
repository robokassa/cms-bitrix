<?
	use Bitrix\Main\Localization\Loc;
	Loc::loadMessages(__FILE__);
?>
<form action="<?=$params['URL']?>" method="post">
	<?=Loc::getMessage("ROBOKASSA.TEMPL_ORDER");?> <?=htmlspecialcharsbx($params['PAYMENT_ID'])?><br>
	<?=Loc::getMessage("ROBOKASSA.TEMPL_TO_PAY");?> <b><?=SaleFormatCurrency($params['PAYMENT_SHOULD_PAY'], $params["PAYMENT_CURRENCY"])?></b>
	<p>
	<input type="hidden" name="MrchLogin" value="<?=htmlspecialcharsbx($params['SHOPLOGIN']);?>">
	<input type="hidden" name="OutSum" value="<?=htmlspecialcharsbx($params['PAYMENT_SHOULD_PAY']);?>">
	<input type="hidden" name="InvId" value="<?=htmlspecialcharsbx($params['PAYMENT_ID']);?>">
	<input type="hidden" name="Desc" value="<?=htmlspecialcharsbx(Loc::getMessage("ROBOKASSA.TEMPL_ORDER_DESC").$params['ORDER_NUMBER']);?>">
	<input type="hidden" name="SignatureValue" value="<?=$params['SIGNATURE_VALUE'];?>">
	<input type="hidden" name="Email" value="<?=htmlspecialcharsbx($params['BUYER_PERSON_EMAIL'])?>">
	<input type="hidden" name="Receipt" value="<?=htmlspecialcharsbx($params['RECEIPT'])?>">
	<input type="hidden" name="SHP_HANDLER" value="ROBOKASSA.PAYMENT">
	<input type="hidden" name="SHP_BX_PAYSYSTEM_CODE"
		value="<?=$params['BX_PAYSYSTEM_CODE'];?>">
	<?if ($params['PS_IS_TEST'] == 'Y'):?>
		<input type="hidden" name="IsTest" value="1">
	<?endif;?>
	<?if ($params['PS_MODE'] != "0"):?>
		<input type="hidden" name="IncCurrLabel" value="<?=htmlspecialcharsbx($params['PS_MODE']);?>">
	<?endif;?>

	<input type="submit" class="btn btn-default button robokasskassa_payment_button"
		name="Submit" value="<?=Loc::getMessage("ROBOKASSA.TEMPL_BUTTON")?>">
	</p>
</form>