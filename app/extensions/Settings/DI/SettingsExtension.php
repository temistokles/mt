<?php

namespace App\Extensions\Settings\DI;

use App\Model\Entity\OrderStateType;
use App\Model\Entity\Vat;
use Nette\DI\CompilerExtension;

class SettingsExtension extends CompilerExtension
{

	/** @var array */
	public $defaults = [
		'modules' => [
			'cron' => [ // access to cron scripts
				'enabled' => FALSE,
				'allowedIps' => ['127.0.0.1'],
			],
			'vats' => [ // vat levels
				Vat::HIGH => 20,
				Vat::LOW => 15,
				Vat::NONE => 0,
			],
			'order' => [
				'enabled' => TRUE,
				'states' => [
					'order in system' => 'ordered',
					'in proceedings' => 'ordered',
					'sent shippers' => 'expeded',
					'ready to take' => 'expeded',
					'ok - recieved' => 'done',
					'ok - taken' => 'done',
					'canceled' => 'storno',
				],
				'types' => [
					'ordered' => OrderStateType::LOCK_ORDER,
					'expeded' => OrderStateType::LOCK_DONE,
					'done' => OrderStateType::LOCK_DONE,
					'storno' => OrderStateType::LOCK_STORNO,
				],
			],
			'categories' => [ // product categories
				'enabled' => FALSE,
				'expandOnlyActiveCategories' => TRUE, // TRUE -> expand only active category | FALSE -> expand all categories
				'maxDeep' => 3, // count of levels to show subcategories
				'showOnlyNonEmpty' => TRUE, // TRUE -> fetch only categories with some products // not implemented yet
				'showProductsCount' => FALSE, // TRUE -> show count of product after category name
			],
			'signs' => [ // fixed IDs for signs (příznaky)
				'enabled' => FALSE,
				'values' => [
					'new' => 1,
					'sale' => 2,
					'top' => 3,
				],
			],
			'pohoda' => [
				'enabled' => FALSE,
				'ico' => '',
				'language' => '',
				'defaultStorage' => '',
				'typePrice' => '',
				'allowedReadStorageCart' => FALSE,
				'allowedReadOrders' => FALSE,
				'allowedCreateStore' => FALSE,
				'allowedCreateShortStock' => FALSE,
				'removeParsedXmlOlderThan' => '1 month',
				'newCodeLenght' => 8,
				'newCodeCharlist' => 'a-Z0-9',
				'vatRates' => [
					'high' => 20,
					'low' => 15,
					'none' => 0,
				],
			],
			'service' => [
				'enabled' => FALSE,
				'pageId' => 1, // ID of page in pages to show as basic info
			],
			'heureka' => [
				'enabled' => FALSE,
				'key' => NULL, // mereni konverzí
			],
			'buyout' => [
				'enabled' => FALSE,
				'pageId' => 1, // ID of page in pages to show as basic info
				'email' => 'buyout@example.sk',
			],
			'newsletter' => [
				'enabled' => FALSE,
				'email' => 'newsletter@example.sk',
				'template' => [
					'footer' => [
						'address' => [
							'company' => 'Grifin s.r.o.',
							'street' => 'Hviezdoslavova 10',
							'zip' => '01001',
							'city' => 'Žilina',
						],
						'contact' => [
							'phone' => '+421 908 848 484',
							'email' => 'obchod@mobilnetelefony.sk',
						],
						'bank' => [
							'cz' => 'CZ3108000000003627894339',
							'sk' => 'SK0475000000004020234814',
						],
					],
				],
			],
		],
		'pageInfo' => [
			'projectName' => 'projectName',
			'author' => 'author',
			'description' => 'description',
			'termPageId' => 1,
		],
		'pageConfig' => [
			'itemsPerRow' => 3,
			'rowsPerPage' => 4,
		],
		'expiration' => [
			'recovery' => '30 minutes',
			'verification' => '1 hour',
			'registration' => '1 hour',
			'remember' => '14 days',
			'notRemember' => '30 minutes',
		],
		'passwords' => [
			'minLength' => 8,
		],
		'design' => [
			'color' => 'default',
			'layoutBoxed' => FALSE,
			'containerBgSolid' => FALSE,
			'headerFixed' => FALSE,
			'footerFixed' => FALSE,
			'sidebarClosed' => FALSE,
			'sidebarFixed' => FALSE,
			'sidebarReversed' => FALSE,
			'sidebarMenuHover' => FALSE,
			'sidebarMenuLight' => FALSE,
		],
		'mails' => [ // default value is NULL - doesnt send mail
			'automatFrom' => 'info@example.sk',
			'createOrder' => NULL,
		],
	];

	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();
		$config = $this->getConfig($this->defaults);

		$builder->addDefinition($this->prefix('settings'))
				->setClass('App\Extensions\Settings\SettingsStorage')
				->addSetup('setPageInfo', [$config['pageInfo']])
				->addSetup('setPageConfig', [$config['pageConfig']])
				->addSetup('setMails', [$config['mails']])
				->addSetup('setExpiration', [$config['expiration']])
				->addSetup('setPasswords', [$config['passwords']])
				->addSetup('setDesign', [$config['design']])
				->addSetup('setModules', [$config['modules']])
				->setInject(TRUE);
	}

}
