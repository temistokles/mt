<?php

namespace App\Model\Entity\Newsletter;

use Doctrine\ORM\Mapping as ORM;
use Kdyby\Doctrine\Entities\BaseEntity;

/**
 * @ORM\Entity
 * @ORM\Table(name="newsletter_message")
 */
class Message extends BaseEntity
{

	const TYPE_USER = 0;
	const TYPE_DEALER = 1;
	const TYPE_GROUP = 2;

	use \Kdyby\Doctrine\Entities\Attributes\Identifier;

	/** @ORM\Column(type="string", length=255) */
	protected $subject;

	/** @ORM\Column(type="text") */
	protected $content;

	/** @ORM\Column(type="string", length=2) */
	protected $locale;

	/** @ORM\Column(type="smallint", length=32, options={"unsigned"=true}) */
	protected $type;

	/**
	 * @ORM\ManyToOne(targetEntity="App\Model\Entity\Group")
	 * @ORM\JoinColumn(nullable=true)
	 */
	protected $group;

}