<?php

namespace App\Model\Entity;

use App\Model\Entity\Traits;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Kdyby\Doctrine\Entities\Attributes\Identifier;
use Kdyby\Doctrine\Entities\BaseEntity;

/**
 * @ORM\Entity(repositoryClass="App\Model\Repository\StockRepository")
 *
 * @property Product $product
 * @property Variant $variant1
 * @property Variant $variant2
 * @property Variant $variant3
 * @property Price $price
 * @property array $groupDiscounts
 * @property Price $purchasePrice
 * @property Price $oldPrice
 * @property boolean $active
 * @property int $quantity
 * @property int $lock
 * @property int $inStore
 * @property string $code
 * @property string $barcode
 */
class Stock extends BaseEntity
{

	use Identifier;
	use Traits\StockPrices;
	use Traits\StockQuantities;
	
    /** @ORM\ManyToOne(targetEntity="Product", inversedBy="stocks", cascade={"persist"}) */
    protected $product;
	
    /** @ORM\ManyToOne(targetEntity="Variant") */
    protected $variant1;
	
    /** @ORM\ManyToOne(targetEntity="Variant") */
    protected $variant2;
	
    /** @ORM\ManyToOne(targetEntity="Variant") */
    protected $variant3;
	
	/** @ORM\Column(type="boolean") */
	protected $active = TRUE;
	
	/** @ORM\Column(type="string", length=100, nullable=true) */
	protected $code;
	
	/** @ORM\Column(type="string", length=100, nullable=true) */
	protected $barcode;

	public function __construct()
	{
		$this->groupDiscounts = new ArrayCollection();
		parent::__construct();
	}

	public function __toString()
	{
		return (string) $this->product;
	}

	public function isNew()
	{
		return $this->id === NULL;
	}

	public function &__get($name)
	{
		if (preg_match('/^price(\d+)$/', $name, $matches)) {
			$value = $this->getPrice($matches[1]);
			return $value;
		} else {
			return parent::__get($name);
		}
	}

}
