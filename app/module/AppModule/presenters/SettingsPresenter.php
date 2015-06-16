<?php

namespace App\AppModule\Presenters;

use App\Components\Unit\IUnitsControlFactory;
use App\Components\Unit\UnitsControl;
use App\Model\Entity\Unit;
use Kdyby\Doctrine\EntityRepository;

class SettingsPresenter extends BasePresenter
{

	/** @var EntityRepository */
	private $unitRepo;

	// <editor-fold desc="injects">

	/** @var IUnitsControlFactory @inject */
	public $iUnitControlFactory;

	// </editor-fold>

	protected function startup()
	{
		parent::startup();
		$this->unitRepo = $this->em->getRepository(Unit::getClassName());
	}

	/**
	 * @secured
	 * @resource('settings')
	 * @privilege('default')
	 */
	public function actionDefault()
	{
		
	}

	/**
	 * @secured
	 * @resource('settings')
	 * @privilege('units')
	 */
	public function actionUnits()
	{
		
	}

	// <editor-fold desc="forms">

	/** @return UnitsControl */
	public function createComponentUnitsForm()
	{
		$control = $this->iUnitControlFactory->create();
		$control->setUnits($this->unitRepo->findAll());
		$control->setLang($this->lang);
		$control->onAfterSave = function () {
			$this->flashMessage('Units was successfully saved.', 'success');
			$this->redirect('units');
		};
		return $control;
	}

	// </editor-fold>
}
