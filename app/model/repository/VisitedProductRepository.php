<?php

namespace App\Model\Repository;

use App\Model\Entity\Stock;
use App\Model\Entity\User;
use Doctrine\Common\Collections\Criteria;

class VisitedProductRepository extends BaseRepository
{

	public function findOneByUserAndStock(User $user, Stock $stock)
	{
		return $this->findOneBy(['user' => $user, 'stock' => $stock]);
	}

	public function findLatest(User $user)
	{
		return $this->findOneBy(['user' => $user], ['visited' => Criteria::DESC]);
	}

}
