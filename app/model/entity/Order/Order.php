<?php

namespace App\Model\Entity;

use App\Model\Facade\Exception\InsufficientQuantityException;
use App\Model\Facade\Exception\MissingItemException;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use h4kuna\Exchange\Exchange;
use Kdyby\Doctrine\Entities\Attributes\Identifier;
use Kdyby\Doctrine\Entities\BaseEntity;
use Knp\DoctrineBehaviors\Model;

/**
 * @ORM\Entity(repositoryClass="App\Model\Repository\OrderRepository")
 * @ORM\Table(name="`order`")
 *
 * @property ArrayCollection $items
 * @property int $itemsCount
 */
class Order extends BaseEntity
{

	use Identifier;
	use Model\Timestampable\Timestampable;

	/** @ORM\OneToOne(targetEntity="User") */
	protected $user;

	/** @ORM\OneToMany(targetEntity="OrderItem", mappedBy="order", cascade={"persist", "remove"}, orphanRemoval=true) */
	protected $items;
	
	/** @ORM\Column(type="string", length=5, nullable=true) */
	protected $locale;

	public function __construct($locale, User $user = NULL)
	{
		if ($user) {
			$this->setUser($user);
		}
		$this->locale = $locale;
		$this->items = new ArrayCollection();
		parent::__construct();
	}

	public function setUser(User $user)
	{
		$this->user = $user;
		return $this;
	}

	public function setItem(Stock $stock, Price $price, $quantity, $locale)
	{
		if ($quantity > $stock->inStore) {
			throw new InsufficientQuantityException();
		}

		$isInItems = function ($key, OrderItem $item) use ($stock) {
			return $stock->id === $item->stock->id;
		};
		$changeQuantity = function ($key, OrderItem $item) use ($stock, $price, $quantity) {
			if ($stock->id === $item->stock->id) {
				if ($quantity > 0) {
					$item->quantity = $quantity;
					$item->price = $price;
				} else {
					$this->items->removeElement($item);
				}
				return FALSE;
			}
			return TRUE;
		};

		if ($this->items->exists($isInItems)) {
			$this->items->forAll($changeQuantity);
		} else {
			$stock->product->setCurrentLocale($locale);
			$item = new OrderItem();
			$item->order = $this;
			$item->stock = $stock;
			$item->name = $stock->product->name;
			$item->price = $price;
			$item->quantity = $quantity;
			$this->items->add($item);
		}
		return $this;
	}

	/** @return int */
	public function getItemCount(Stock $stock)
	{
		foreach ($this->items as $item) {
			if ($item->stock->id === $stock->id) {
				return $item->quantity;
			}
		}
		throw new MissingItemException();
	}

	/** @return int */
	public function getItemsCount()
	{
		return count($this->items);
	}

	/** @return Price */
	public function getItemPrice(Stock $stock)
	{
		foreach ($this->items as $item) {
			if ($item->stock->id === $stock->id) {
				return $item->price;
			}
		}
		throw new MissingItemException();
	}

	/** @return float */
	public function getItemsTotalPrice(Exchange $exchange, $level = NULL, $withVat = TRUE)
	{
		$totalPrice = 0;
		foreach ($this->items as $item) {
			$totalPrice += $item->getTotalPrice($exchange, $level, $withVat);
		}
		return $totalPrice;
	}

	public function import(Basket $basket, $level = NULL)
	{
		$this->items->clear();
		foreach ($basket->items as $item) {
			/* @var $item BasketItem */
			$price = $item->stock->getPrice($level);
			$this->setItem($item->stock, $price, $item->quantity, $this->locale);
		}
		return $this;
	}

}
