<?
	use Bitrix\Main\Localization\Loc;
	Loc::loadMessages(__FILE__);
?>
<?php if ($params['IFRAME_STATUS'] == 'Y'){ ?>
	<script type="text/javascript" src="https://auth.robokassa.ru/Merchant/bundle/robokassa_iframe.js"></script>
	<?=Loc::getMessage("ROBOKASSA.TEMPL_ORDER");?> <?=htmlspecialcharsbx($params['PAYMENT_ID'])?><br>
	<?=Loc::getMessage("ROBOKASSA.TEMPL_TO_PAY");?> <b><?=SaleFormatCurrency($params['PAYMENT_SHOULD_PAY'], $params["PAYMENT_CURRENCY"])?></b>
	<p>
	<input type="submit" class="btn btn-default button robokasskassa_payment_button"
		name="Submit" value="<?=Loc::getMessage("ROBOKASSA.TEMPL_BUTTON")?>"
		onclick="Robokassa.StartPayment({MerchantLogin: '<?=htmlspecialcharsbx($params['SHOPLOGIN']);?>', OutSum: '<?=htmlspecialcharsbx($params['PAYMENT_SHOULD_PAY']);?>', InvId: '<?=htmlspecialcharsbx($params['PAYMENT_ID']);?>', Description: '<?=htmlspecialcharsbx(Loc::getMessage("ROBOKASSA.TEMPL_ORDER_DESC").$params['ORDER_NUMBER']);?>', SignatureValue: '<?=$params['SIGNATURE_VALUE'];?>', Email: '<?=htmlspecialcharsbx($params['BUYER_PERSON_EMAIL'])?>', Receipt: '<?=$params['RECEIPT']?>', <?php if(!empty($params['OUT_CURRENCY']) && strlen($params['OUT_CURRENCY']) > 0):?> OutSumCurrency: '<?=htmlspecialcharsbx($params['OUT_CURRENCY'])?>', <?php endif;?> SHP_BX_PAYSYSTEM_CODE: '<?=$params['BX_PAYSYSTEM_CODE'];?>',  SHP_HANDLER: 'ROBOKASSA.PAYMENT',  Shp_label: 'official_bitrix'})">
	</p>
<?php }else{ ?>
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
	<input type="hidden" name="Receipt" value="<?=$params['RECEIPT']?>">
	<?php if(!empty($params['OUT_CURRENCY']) && strlen($params['OUT_CURRENCY']) > 0):?>
        <input type="hidden" name="OutSumCurrency" value="<?=htmlspecialcharsbx($params['OUT_CURRENCY'])?>">
    <?php endif;?>
	<input type="hidden" name="SHP_HANDLER" value="ROBOKASSA.PAYMENT">
	<input type="hidden" name="SHP_BX_PAYSYSTEM_CODE"
		value="<?=$params['BX_PAYSYSTEM_CODE'];?>">
	<input type="hidden" name="Shp_label" value="official_bitrix">
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
<?php } ?>