<?php

namespace App\FrontModule\Presenters;

class HomepagePresenter extends BasePresenter
{

	public function actionDefault()
	{
		$this->showSlider = TRUE;
		$this->showBrands = TRUE;
//		$this->showSteps = FALSE;
	}

	public function renderDefault()
	{
		
	}

}
