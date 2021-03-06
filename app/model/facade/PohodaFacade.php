<?php

namespace App\Model\Facade;

use App\Extensions\Settings\SettingsStorage;
use App\Helpers;
use App\Model\Entity\PohodaItem;
use App\Model\Entity\PohodaStorage;
use App\Model\Entity\Special\XmlItem;
use App\Model\Entity\Stock;
use App\Model\Repository\PohodaItemRepository;
use Exception;
use Kdyby\Doctrine\EntityManager;
use MissingSettingsException;
use Nette\Database\UniqueConstraintViolationException;
use Nette\DI\Container;
use Nette\Object;
use Nette\Utils\DateTime;
use Nette\Utils\FileSystem;
use Nette\Utils\Finder;
use Nette\Utils\Random;
use Nette\Utils\Strings;
use XMLReader;

class PohodaFacade extends Object
{

	const STORE = 'store'; // skladová karta
	const SHORT_STOCK = 'short-stock'; // skladová zásoba
	const ANY_IMPORT = 'any-import'; // jakýkoli import skladů
	const ORDERS = 'orders'; // objednávky
	const DIR_FOR_IMPORT = 'files/pohoda-xml-import';
	const FOLDER_UPLOADED = 'uploaded';
	const FOLDER_PARSED = 'parsed';
	const LAST_DOWNLOAD = 'last-download'; // poslední stažení XML do PohodaConnectoru
	const LAST_UPDATE = 'last-update'; // poslední nahrání XML do přechodné Pohoda DB
	const LAST_CONVERT = 'last-convert'; // poslední převod produktů z Pohoda DB do vnitřní DB

	/** @var array */
	public $onDoneRecieveXml = [];

	/** @var array */
	public $onStartParseXml = [];

	/** @var array */
	public $onDoneParseXml = [];

	/** @var EntityManager @inject */
	public $em;

	/** @var Container @inject */
	public $container;

	/** @var SettingsStorage @inject */
	public $settings;

	public function updateFullProducts($lastChange = NULL)
	{
		/* @var $pohodaRepo PohodaItemRepository */
		$pohodaRepo = $this->em->getRepository(PohodaItem::getClassName());
		$stockRepo = $this->em->getRepository(Stock::getClassName());
		$language = $this->settings->modules->pohoda->language;
		if (!$language) {
			throw new MissingSettingsException('Pohoda language must be set.');
		}
		$conditions = [
			'isInternet' => 'true',
		];
		if ($lastChange) {
			$conditions['updatedAt >='] = $lastChange;
		}

		$pohodaItems = $pohodaRepo->findArrGroupedBy($conditions);
		$i = 0;
		foreach ($pohodaItems as $group) {
			list($pohodaProductArr, $totalCount) = $group;

			/* @var $stock Stock */
			if (array_key_exists('code', $pohodaProductArr)) {
				$stock = $stockRepo->findOneByPohodaCode($pohodaProductArr['code']);
				if ($stock) {
					$stock->product->translateAdd($language)->name = $pohodaProductArr['name'];
					$stock->product->mergeNewTranslations();
					$stock->quantity = $totalCount;
					$stock->purchasePrice = $pohodaProductArr['purchasingPrice'];
					$stock->price = $pohodaProductArr['recountedSellingWithoutVat'];
					$stock->barcode = $pohodaProductArr['ean'];
					$this->em->persist($stock);
					$i++;
				}
			}

			if ($i % 500 === 0) {
				$this->em->flush();
			}
		}
		$this->em->flush();
	}

	public function getNewCode()
	{
		$pohodaRepo = $this->em->getRepository(PohodaItem::getClassName());
		$tries = 5;
		for ($i = 0; $i < $tries; $i++) {
			$newCodeLenght = $this->settings->modules->pohoda->newCodeLenght;
			$newCodeCharlist = $this->settings->modules->pohoda->newCodeCharlist;
			$newCode = Random::generate($newCodeLenght, $newCodeCharlist);
			$found = $pohodaRepo->findBy(['code' => $newCode]);
			if (!$found) {
				return $newCode;
			}
		}
		throw new UniqueConstraintViolationException('Generating unique code was failed.');
	}

	public function recieveStore($xml)
	{
		$type = self::STORE;
		$filename = $this->recieveXml($xml, $type);
		return $this->parseXml($filename, $type);
	}

	public function recieveShortStock($xml)
	{
		$type = self::SHORT_STOCK;
		$filename = $this->recieveXml($xml, $type);
		return $this->parseXml($filename, $type);
	}

	protected function recieveXml($xml, $type)
	{
		if ($xml) {
			$savedFilename = $this->createXml($xml, $type);
			$this->onDoneRecieveXml();
			return $savedFilename;
		} else {
			throw new Exception('XML file is empty');
		}
	}

	public function setLastSync($type, $sync = self::LAST_UPDATE)
	{
		$filename = $this->getFilenameLastSync($type, $sync);
		file_put_contents($filename, time());
	}

	public function getLastSync($type, $sync = self::LAST_UPDATE)
	{
		$time = NULL;
		switch ($type) {
			case self::STORE:
			case self::SHORT_STOCK:
			case self::ANY_IMPORT:
				$time = $this->getLastSyncDate(Strings::webalize($type), $sync);
				break;
		}
		return $time;
	}

	public function clearLastSync($type, $sync)
	{
		$filename = $this->getFilenameLastSync($type, $sync);
		FileSystem::delete($filename);
	}

	protected function parseXml($filename, $type, $startLine = NULL, $finishLine = NULL)
	{
		ini_set('max_execution_time', 60);
		ini_set('memory_limit', '300M');

		$productRepo = $this->em->getRepository(PohodaItem::getClassName());
		$storageRepo = $this->em->getRepository(PohodaStorage::getClassName());

		$this->onStartParseXml($type);

		$reader = new XMLReader();
		$reader->open($filename);

		$line = 0;
		$counter = 0;
		while ($reader->read()) {
			if ($finishLine && $line >= $finishLine) {
				break;
			}

			if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'lStk:stock') { // products start
				$line++;
				if ($startLine && $line < $startLine) {
					continue;
				}
				$item = new XmlItem();
				while ($reader->read()) { // product start
					if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->name === 'lStk:stock') {
						break;
					}

					if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'stk:stockHeader') {
						while ($reader->read()) {
							if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->name === 'stk:stockHeader') {
								break;
							}
							if ($reader->nodeType === XMLReader::ELEMENT && (
									$reader->name === 'stk:id' ||
									$reader->name === 'stk:code' ||
									$reader->name === 'stk:name' ||
									$reader->name === 'stk:EAN' ||
									$reader->name === 'stk:count' ||
									$reader->name === 'stk:countReceivedOrders' ||
									$reader->name === 'stk:purchasingPrice' ||
									$reader->name === 'stk:sellingPrice' ||
									$reader->name === 'stk:sellingPriceWithVAT' ||
									$reader->name === 'stk:purchasingRateVAT' ||
									$reader->name === 'stk:sellingRateVAT' ||
									$reader->name === 'stk:isSales' ||
									$reader->name === 'stk:isInternet'
									)) {
								$item->{$reader->name} = $reader->readString();
							}
							if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'stk:storage') {
								$item->storage = new XmlItem();
								while ($reader->read()) {
									if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->name === 'stk:storage') {
										break;
									}
									if ($reader->nodeType === XMLReader::ELEMENT && (
											$reader->name === 'typ:id' ||
											$reader->name === 'typ:ids'
											)) {
										$item->storage->{$reader->name} = $reader->readString();
									}
								}
							}
						}
					} // stk:stockHeader

					if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'stk:stockPriceItem') {
						$item->stockPrice = [];
						while ($reader->read()) {
							if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->name === 'stk:stockPriceItem') {
								break;
							}
							// stk:stockPrice
							if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'stk:stockPrice') {
								$item->stockPrice[] = $stockPriceItem = new XmlItem();
								while ($reader->read()) {
									if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->name === 'stk:stockPrice') {
										break;
									}
									if ($reader->nodeType === XMLReader::ELEMENT && (
											$reader->name === 'typ:id' ||
											$reader->name === 'typ:ids' ||
											$reader->name === 'typ:price'
											)) {
										$stockPriceItem->{$reader->name} = $reader->readString();
									}
								}
							}
						}
					} // stk:stockPriceItem
				} // product end

				if (isset($item->storage) && isset($item->storage->typ_id) && isset($item->storage->typ_ids)) { // update/add storage
					$storage = $storageRepo->find($item->storage->typ_id);
					if (!$storage) {
						$storage = new PohodaStorage($item->storage->typ_id);
					}
					$storage->name = $item->storage->typ_ids;
					$this->em->persist($storage);
				}

				// update/add product
				if (isset($item->stk_id) && isset($item->stk_code) && isset($storage)) {
					$product = $productRepo->find($item->stk_id);
					if (!$product) {
						$product = new PohodaItem($item->stk_id);
					}
					$product->code = $item->stk_code;
					$product->storage = $storage;

					$optional = [
						'stk_name' => 'name',
						'stk_isSales' => 'isSales',
						'stk_isInternet' => 'isInternet',
						'stk_purchasingRateVAT' => 'purchasingRateVAT',
						'stk_sellingRateVAT' => 'sellingRateVAT',
						'stk_purchasingPrice' => 'purchasingPrice',
						'stk_sellingPrice' => 'sellingPrice',
						'stk_sellingPriceWithVAT' => 'sellingPriceWithVAT',
						'stk_count' => 'count',
						'stk_countReceivedOrders' => 'countReceivedOrders',
					];
					foreach ($optional as $itemKey => $productKey) {
						if (isset($item->$itemKey) && isset($product->$productKey)) {
							$product->$productKey = $item->$itemKey;
						}
					}
					if (isset($item->stockPrice) && is_array($item->stockPrice)) {
						foreach ($item->stockPrice as $priceItem) {
							if (isset($priceItem->typ_id) && $priceItem->typ_id === '1') {
								$item->stockPrice1 = $priceItem->typ_price;
								$product->priceItem1 = $priceItem->typ_price;
							}
						}
					}

					// recount selling price
					$vatRates = $this->settings->modules->pohoda->vatRates;
					$sellingVatRate = $vatRates[$product->sellingRateVAT];
					if (isset($item->sellingPrice) && isset($item->sellingPriceWithVAT)) {
						$product->setRecountedSellingPrice($item->sellingPrice, $item->sellingPriceWithVAT, $sellingVatRate);
					} else if (isset($item->stockPrice1)) {
						$product->setRecountedSellingPrice(NULL, $item->stockPrice1, $sellingVatRate);
					}

					$this->em->persist($product);
					$counter++;
				}

				if ($counter % 1000 === 0) {
					$this->em->flush();
					$this->em->clear();
				}
			} // products end
		} // READER END

		$this->em->flush();
		$reader->close();

		$this->onDoneParseXml($type);

		$minusTime = '-' . $this->settings->modules->pohoda->removeParsedXmlOlderThan;
		$this->removeOlderParsedXml(DateTime::from($minusTime), $type);
		$this->moveXml($filename, self::FOLDER_PARSED, $type);
	}

	protected function createXml($xml, $type)
	{
		$filename = $this->getFilenameForXml(self::FOLDER_UPLOADED, Strings::webalize($type));
		file_put_contents($filename, $xml);
		return $filename;
	}

	protected function moveXml($file, $folder, $type)
	{
		$dest = $this->getFilenameForXml($folder, Strings::webalize($type));
		FileSystem::copy($file, $dest);
		FileSystem::delete($file);
		return $dest;
	}

	protected function removeOlderParsedXml(DateTime $date, $type = NULL)
	{
		$folders = [];
		if ($type) {
			$folders[] = $type;
		} else {
			$folders[] = self::STORE;
			$folders[] = self::SHORT_STOCK;
		}
		$paths = [];
		foreach ($folders as $folder) {
			$paths[] = realpath($this->getDirForXml(self::FOLDER_PARSED, $folder));
		}
		foreach (Finder::findFiles('*.xml')->in($paths) as $filename => $file) {
			if (preg_match('/(\d+).xml$/', $filename, $matches)) {
				$xmlTime = DateTime::from($matches[1]);
				if ($xmlTime < $date) {
					FileSystem::delete($filename);
				}
			}
		}
	}

	protected function getRootDir()
	{
		$root = Helpers::getPath($this->container->parameters['appDir'], '..');
		$rootPohoda = Helpers::getPath($root, self::DIR_FOR_IMPORT);
		FileSystem::createDir($rootPohoda);

		return $rootPohoda;
	}

	protected function getDirForXml($dir, $folder)
	{
		$root = $this->getRootDir();
		$path = Helpers::getPath($root, $dir, $folder);
		FileSystem::createDir($path);

		return $path;
	}

	protected function getFilenameForXml($dir, $folder)
	{
		$filename = time() . '.xml';
		return $this->getDirForXml($dir, $folder) . '/' . $filename;
	}

	protected function getFilenameLastSync($type, $sync)
	{
		$filename = Strings::webalize($type) . '.time';
		$dir = Helpers::getPath($this->getRootDir(), $sync);
		FileSystem::createDir($dir);
		return $dir . '/' . $filename;
	}

	protected function getLastSyncDate($type, $sync)
	{
		$filename = $this->getFilenameLastSync($type, $sync);
		if (is_file($filename)) {
			$content = file_get_contents($filename);
			if ($content) {
				return DateTime::from($content);
			}
		}
		return NULL;
	}

}
