<?php

namespace App\Model\Facade\Traits;

use App\Model\Entity\Address;
use App\Model\Entity\Role;
use App\Model\Entity\User;
use InvalidArgumentException;

trait UserFacadeSetters
{

	/**
	 * Add role as Role entity, string or array of entites to user.
	 * @param User $user
	 * @param Role|string|array $role
	 * @return User
	 * @throws InvalidArgumentException
	 */
	public function addRole(User $user, $role)
	{
		if (is_string($role) || $role instanceof Role) {
			return $user->addRole($this->roleDao->findOneBy(['name' => (string) $role]));
		} elseif (is_array($role)) {
			return $user->addRoles($this->roleDao->findBy(['name' => $role]));
		} else {
			throw new InvalidArgumentException;
		}
	}
	
	public function setAddress(User $user, Address $billing = NULL, Address $shipping = NULL, $removeNull = TRUE)
	{
		if ($billing) {
			if (!$user->billingAddress) {
				$user->billingAddress = new Address();
			}
			$user->billingAddress->import($billing, TRUE);
			$this->addressRepo->save($user->billingAddress);
		} else if ($removeNull && $user->billingAddress) {
			$toDeleteBilling = $user->billingAddress;
			$user->billingAddress = NULL;
		}
		
		if ($shipping) {
			if (!$user->shippingAddress) {
				$user->shippingAddress = new Address();
			}
			$user->shippingAddress->import($shipping, TRUE);
			$this->addressRepo->save($user->shippingAddress);
		} else if ($removeNull && $user->shippingAddress) {
			$toDeleteShipping = $user->shippingAddress;
			$user->shippingAddress = NULL;
		}
		
		$this->userRepo->save($user);
		if ($removeNull) {
			if (isset($toDeleteBilling)) {
				$this->addressRepo->delete($toDeleteBilling);
			}
			if (isset($toDeleteShipping)) {
				$this->addressRepo->delete($toDeleteShipping);
			}
		}
		
		return $this;
	}

}
