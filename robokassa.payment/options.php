<?php

	use Bitrix\Main\Application;
	use Bitrix\Main\Config\Option;
	use Bitrix\Main\Localization\Loc;

	defined('ADMIN_MODULE_NAME') or define('ADMIN_MODULE_NAME', 'ipol.robokassa');

	global $USER, $APPLICATION;

	if (!$USER->isAdmin())
		$APPLICATION->authForm('Nope');
?>
<?php

	/** @var \Bitrix\Main\HttpRequest $request */
	$request = Application::getInstance()->getContext()->getRequest();

	\Bitrix\Main\Loader::IncludeModule("sale");

	#Loc::loadMessages(Application::getInstance()->getContext()->getServer()->getDocumentRoot() . "/bitrix/modules/main/options.php");
	Loc::loadMessages(__FILE__);


	$tabControl = new CAdminTabControl(
		"tabControl",
		[
			[
				"DIV" => "edit1",
				"TAB" => Loc::getMessage("IPOL_ROBOKASSA_OPTIONS_TAB_SECOND_CHECK_TITLE"),
				"TITLE" => Loc::getMessage("IPOL_ROBOKASSA_OPTIONS_TAB_SECOND_CHECK_TITLE"),
			],
		]
	);

	/** @var array $statuses */
	$statuses = \Bitrix\Sale\Internals\StatusLangTable::getList(
		[
			'order' => ['STATUS.SORT' => 'ASC'],
			'filter' => ['STATUS.TYPE' => 'O', 'LID' => LANGUAGE_ID],
			'select' => ['STATUS_ID', 'NAME', 'DESCRIPTION']
		]
	)->fetchAll();

	if (
		(!empty($save) || !empty($restore))
		&& $request->isPost()
		&& check_bitrix_sessid())
	{

		Option::set(ADMIN_MODULE_NAME, 'SECOND_CHECK_STATUS_ID', $request->getPost('SECOND_CHECK_STATUS_ID'));
		Option::set(ADMIN_MODULE_NAME, 'SECOND_CHECK_PROPERTY_CODE', $request->getPost('SECOND_CHECK_PROPERTY_CODE'));
	}

	$secondCheckStatus = Option::get(ADMIN_MODULE_NAME, 'SECOND_CHECK_STATUS_ID', '');
	$secondCheckProperty = Option::get(ADMIN_MODULE_NAME, 'SECOND_CHECK_PROPERTY_CODE', '');

$tabControl->begin();
?>

<form method="post" action="<?= sprintf('%s?mid=%s&lang=%s', $request->getRequestedPage(), urlencode($mid), LANGUAGE_ID) ?>">
	<?php echo bitrix_sessid_post();?>


	<?php $tabControl->beginNextTab(); ?>

	<tr class="heading">
		<td colspan="2" valign="top" align="center"><?=Loc::getMessage('IPOL_ROBOKASSA_OPTIONS_TAB_SECOND_CHECK_ABOUT');?></td>
	</tr>

	<tr>
		<td>
			<?=Loc::getMessage('IPOL_ROBOKASSA_OPTIONS_TAB_SECOND_CHECK_STATUS_ID');?>
		</td>
		<td>
			<select name="SECOND_CHECK_STATUS_ID">
				<option value=""><?=Loc::getMessage('IPOL_ROBOKASSA_OPTIONS_TAB_SECOND_CHECK_STATUS_ID_NONE');?></option>
				<?php foreach($statuses as $status):?>
					<option
						value="<?=$status['STATUS_ID'];?>"
						<?php if($secondCheckStatus === $status['STATUS_ID']):?> selected="selected"<?php endif;?>
					>
						<?=$status['NAME'];?>
					</option>
				<?php endforeach;?>
			</select>
		</td>
	</tr>

	<tr>
		<td>
			<?=Loc::getMessage('IPOL_ROBOKASSA_OPTIONS_TAB_SECOND_CHECK_PROPERTY_CODE');?>
		</td>
		<td>
			<input type="text" name="SECOND_CHECK_PROPERTY_CODE" value="<?=$secondCheckProperty;?>"><br />
			<small>
				<?=Loc::getMessage('IPOL_ROBOKASSA_OPTIONS_TAB_SECOND_CHECK_PROPERTY_ABOUT');?>
			</small>
		</td>
	</tr>

	<?php $tabControl->buttons(); ?>
	<input type="submit" name="save" value="<?= Loc::getMessage("MAIN_SAVE") ?>" title="<?= Loc::getMessage("MAIN_OPT_SAVE_TITLE") ?>" class="adm-btn-save" />
	<?php $tabControl->end(); ?>
</form>