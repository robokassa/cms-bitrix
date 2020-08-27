<?php

	$updater->copyDirFiles("install/handler/robokassapayment", "/bitrix/php_interface/include/sale_payment/robokassapayment");
	
	RegisterModuleDependences('sale', 'OnSaleStatusOrder', 'ipol.robokassa', 'RobokassaPaymentService', 'sendSecondCheck');