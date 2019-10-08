<?php

global $MESS;
IncludeModuleLangFile(__FILE__);

if (class_exists("ipol_robokassa")) return;

Class ipol_robokassa extends CModule
{
	var $MODULE_ID = "ipol.robokassa";
	var $MODULE_VERSION;
	var $MODULE_VERSION_DATE;
	var $MODULE_NAME;
	var $MODULE_DESCRIPTION;
	var $MODULE_CSS;
	var $MODULE_GROUP_RIGHTS = "N";
	var $PARTNER_NAME;
	var $PARTNER_URI;

	function ipol_robokassa()
	{
		$arModuleVersion = array();
		$path = str_replace("\\", "/", __FILE__);
		$path = substr($path, 0, strlen($path) - strlen("/index.php"));
		include($path."/version.php");
		if (is_array($arModuleVersion) && array_key_exists("VERSION", $arModuleVersion))
		{
			$this->MODULE_VERSION = $arModuleVersion["VERSION"];
			$this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
		}
		$this->PARTNER_NAME = GetMessage("ROBOKASSA.PARTNER_NAME");
		$this->PARTNER_URI = GetMessage("ROBOKASSA.PARTNER_URI");
		$this->MODULE_NAME = GetMessage("ROBOKASSA.MODULE_NAME");
		$this->MODULE_DESCRIPTION = GetMessage("ROBOKASSA.MODULE_DESCRIPTION");
	}

	function InstallFiles($arParams = array())
	{

		CopyDirFiles(
			$_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/ipol.robokassa/install/handler',
			$_SERVER['DOCUMENT_ROOT'].'/bitrix/php_interface/include/sale_payment/',
			true,
			true
		);

		return true;
	}

	function UnInstallFiles()
	{

		DeleteDirFilesEx(
			$_SERVER['DOCUMENT_ROOT'].'/bitrix/php_interface/include/sale_payment/robokassapayment'
		);
		return true;
	}

	function DoInstall()
	{

		global $APPLICATION;

		$this->InstallDB();
		$this->InstallFiles();

		$APPLICATION->IncludeAdminFile(
			GetMessage("ROBOKASSA.MODULE_INSTALL_TITLE"),
			$_SERVER['DOCUMENT_ROOT']."/bitrix/modules/ipol.robokassa/install/step.php"
		);
	}

	function DoUninstall()
	{

		global $APPLICATION;

		$this->UnInstallDB();
		$this->UnInstallFiles();

		$APPLICATION->IncludeAdminFile(
			GetMessage("ROBOKASSA.MODULE_UNINSTALL_TITLE"),
			$_SERVER['DOCUMENT_ROOT']."/bitrix/modules/ipol.robokassa/install/unstep.php"
		);
	}

	function InstallDB()
	{

		RegisterModule("ipol.robokassa");
		return true;
	}

	function UnInstallDB()
	{

		UnRegisterModule("ipol.robokassa");
		return true;
	}
}