<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Kdyby\Doctrine\Entities\BaseEntity;
use Knp\DoctrineBehaviors\Model;

/**
 * @ORM\Entity
 *
 * @property string $html
 */
class PaymentTranslation extends BaseEntity
{

	use Model\Translatable\Translation;

	/** @ORM\Column(type="text", nullable=true) */
	protected $html;

}
