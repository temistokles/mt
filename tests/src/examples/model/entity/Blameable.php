<?php

namespace Test\Examples\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Kdyby\Doctrine\Entities\Attributes\Identifier;
use Kdyby\Doctrine\Entities\BaseEntity;

/**
 * @ORM\Entity
 * https://github.com/Zenify/DoctrineBehaviors
 * https://github.com/KnpLabs/DoctrineBehaviors/blob/master/tests/fixtures/BehaviorFixtures/ORM/BlameableEntity.php
 *
 * @property string $name
 * @property mixed $createdBy
 * @property mixed $updatedBy
 * @property mixed $deletedBy
 */
class Blameable extends BaseEntity
{

	use Identifier;
	use \Knp\DoctrineBehaviors\Model\Blameable\Blameable;

	/** @ORM\Column(type="string", length=255, nullable=true) */
	protected $name;

	public function __toString()
	{
		return (string) $this->name;
	}

}
