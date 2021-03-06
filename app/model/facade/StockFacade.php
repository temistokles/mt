<?php

namespace App\Model\Facade;

use App\Extensions\Settings\SettingsStorage;
use App\Model\Entity\Category;
use App\Model\Entity\Producer;
use App\Model\Entity\ProducerLine;
use App\Model\Entity\ProducerModel;
use App\Model\Entity\Product;
use App\Model\Entity\Sign;
use App\Model\Entity\Stock;
use App\Model\Repository\CategoryRepository;
use App\Model\Repository\ProductRepository;
use App\Model\Repository\SignRepository;
use App\Model\Repository\StockRepository;
use App\Router\RouterFactory;
use Kdyby\Doctrine\EntityManager;
use Kdyby\Translation\Translator;
use Nette\Application\Request;
use Nette\Caching\Cache;
use Nette\Caching\IStorage;
use Nette\Object;
use Nette\Utils\DateTime;

class StockFacade extends Object
{

	const KEY_ALL_PRODUCTS_URLS = 'product-urls';
	const TAG_ALL_PRODUCTS = 'all-products';

	/** @var EntityManager @inject */
	public $em;

	/** @var Translator @inject */
	public $translator;

	/** @var SettingsStorage @inject */
	public $settings;

	/** @var IStorage @inject */
	public $cacheStorage;

	/** @var StockRepository */
	private $stockRepo;

	/** @var ProductRepository */
	private $productRepo;

	/** @var CategoryRepository */
	private $categoryRepo;

	/** @var SignRepository */
	private $signRepo;

	public function __construct(EntityManager $em)
	{
		$this->em = $em;
		$this->stockRepo = $this->em->getRepository(Stock::getClassName());
		$this->productRepo = $this->em->getRepository(Product::getClassName());
		$this->categoryRepo = $this->em->getRepository(Category::getClassName());
		$this->signRepo = $this->em->getRepository(Sign::getClassName());
	}

	public function getLimitPrices($priceLevelName, Category $category = NULL, $producer = NULL)
	{
		$qb = $this->stockRepo->createQueryBuilder('s')
				->select("MIN(s.{$priceLevelName}) AS minimum, MAX(s.{$priceLevelName}) AS maximum")
				->innerJoin('s.product', 'p');

		if ($category) {
			$qb->innerJoin('p.categories', 'categories')
					->andWhere('categories IN (:categories)')
					->setParameter('categories', array_keys($category->childrenArray));
		}
		if ($producer instanceof Producer) {
			
		} else if ($producer instanceof ProducerLine) {
			
		} else if ($producer instanceof ProducerModel) {
			
		}
		$result = $qb->getQuery()->getOneOrNullResult();

		$limitPrices = [$result['minimum'], $result['maximum']];
		return $limitPrices;
	}

	private function getSignedProducts($signId)
	{
		if (!$signId) {
			return [];
		}
		$sorting = [
			0 => 'ASC',
			1 => 'DESC',
		];
		$newSign = $this->signRepo->find($signId);
		if (!$newSign) {
			return [];
		}
		$qb = $this->stockRepo
				->createQueryBuilder('s')
				->innerJoin('s.product', 'p')
				->innerJoin('p.signs', 'signs')
				->where('signs = :sign')
				->andWhere('s.active = :active AND p.active = :active')
				->andWhere('s.deletedAt IS NULL OR s.deletedAt > :now')
				->setParameter('active', TRUE)
				->setParameter('sign', $newSign)
				->setParameter('now', new DateTime());
		return $qb->orderBy('s.id', $sorting[rand(0, 1)])
						->setMaxResults(10)
						->getQuery()
						->getResult();
	}

	public function getSales()
	{
		$signs = $this->settings->modules->signs;
		$id = $signs->enabled ? $signs->values->sale : NULL;
		return $this->getSignedProducts($id);
	}

	public function getNews()
	{
		$signs = $this->settings->modules->signs;
		$id = $signs->enabled ? $signs->values->new : NULL;
		return $this->getSignedProducts($id);
	}

	public function getTops()
	{
		$signs = $this->settings->modules->signs;
		$id = $signs->enabled ? $signs->values->top : NULL;
		return $this->getSignedProducts($id);
	}

	private function getDemoProducts()
	{
		$sorting = [
			0 => 'ASC',
			1 => 'DESC',
		];
		return $this->stockRepo
						->createQueryBuilder('s')
						->innerJoin('s.product', 'p')
						->where('s.active = :active')
						->andWhere('p.active = :active')
						->andWhere('s.deletedAt IS NULL OR s.deletedAt > :now')
						->setParameter('active', TRUE)
						->setParameter('now', new DateTime())
						->orderBy('s.id', $sorting[rand(0, 1)])
						->setMaxResults(10)
						->getQuery()
						->getResult();
	}

	public function getBestSellers()
	{
		return $this->getDemoProducts();
	}

	public function urlToId($uri, Request $request)
	{
		$locale = $request->getParameter(RouterFactory::LOCALE_PARAM_NAME);
		$slugs = $this->getUrls($locale);
		$slug = array_search($uri, $slugs);
		if ($slug) {
			return $slug;
		}
		return NULL;
	}

	public function idToUrl($id, Request $request)
	{
		$locale = $request->getParameter(RouterFactory::LOCALE_PARAM_NAME);
		$slugs = $this->getUrls($locale);
		if (array_key_exists($id, $slugs)) {
			return $slugs[$id];
		}
		return NULL;
	}

	/** @return array */
	private function getUrls($locale = NULL)
	{
		if ($locale === NULL) {
			$locale = $this->translator->getDefaultLocale();
		}

		$cache = $this->getCache();
		$cacheKey = self::KEY_ALL_PRODUCTS_URLS . '_' . $locale;

		$urls[$locale] = $cache->load($cacheKey);
		if (!$urls[$locale]) {
			$urls[$locale] = $this->getLocaleUrlsArray($locale);
			$cache->save($cacheKey, $urls[$locale], [Cache::TAGS => [self::TAG_ALL_PRODUCTS]]);
		}

		return $urls[$locale];
	}

	/** @return array */
	private function getLocaleUrlsArray($locale)
	{
		$localeUrls = [];
		$this->categoryRepo->findAll(); // only for optimalization - doctrine use intern cache for objects
		$products = $this->productRepo->findAllWithTranslation(['active' => TRUE]);
		foreach ($products as $product) {
			$product->setCurrentLocale($locale);
			$localeUrls[$product->id] = $product->url;
		}
		return $localeUrls;
	}

	/** @return Cache */
	public function getCache()
	{
		$cache = new Cache($this->cacheStorage, get_class($this));
		return $cache;
	}

}
