<?php

namespace App\Components\Product;

use App\Forms\Form;
use App\Forms\Renderers\MetronicFormRenderer;
use App\Model\Entity\Stock;
use App\Model\Entity\Vat;
use App\Model\Facade\VatFacade;
use Nette\Utils\ArrayHash;

class StockBasicControl extends StockBaseControl
{
	// <editor-fold desc="variables">

	/** @var VatFacade @inject */
	public $vatFacade;

	// </editor-fold>

	/** @return Form */
	protected function createComponentForm()
	{
		$this->checkEntityExistsBeforeRender();

		$form = new Form;
		$form->setTranslator($this->translator);
		$form->setRenderer(new MetronicFormRenderer);
		
		$product = $this->stock->product;
		$product->setCurrentLocale($this->lang);

		$form->addText('name', 'Name')
				->setAttribute('placeholder', $product->name);
		$form->addText('barcode', 'Barcode');
		$form->addCheckSwitch('active', 'Active')
				->setDefaultValue(TRUE);
		$form->addTextArea('perex', 'Perex')
				->setAttribute('placeholder', $product->perex);
		$form->addWysiHtml('description', 'Description')
				->setAttribute('placeholder', $product->description);

		$form->addSubmit('save', 'Save');

		$form->setDefaults($this->getDefaults());
		$form->onSuccess[] = $this->formSucceeded;
		return $form;
	}

	public function formSucceeded(Form $form, $values)
	{
		$this->load($values);
		$this->save();
		$this->onAfterSave($this->stock);
	}

	private function load(ArrayHash $values)
	{
		$this->stock->barcode = $values->barcode;
		$this->stock->active = $values->active;

		$this->stock->product->setCurrentLocale($this->lang);
		$this->stock->product->name = $values->name;
		$this->stock->product->perex = $values->perex;
		$this->stock->product->description = $values->description;
		$this->stock->product->mergeNewTranslations();
		$this->stock->product->active = $values->active;

		return $this;
	}

	private function save()
	{
		$stockRepo = $this->em->getRepository(Stock::getClassName());
		$stockRepo->save($this->stock);
		return $this;
	}

	/** @return array */
	protected function getDefaults()
	{
		$values = [
			'barcode' => $this->stock->barcode,
			'active' => $this->stock->active,
		];
		if ($this->stock->product) {
			$this->stock->product->setCurrentLocale($this->lang);
			$values = [
				'name' => $this->stock->product->name,
				'perex' => $this->stock->product->perex,
				'description' => $this->stock->product->description,
			];
		}
		return $values;
	}
}

interface IStockBasicControlFactory
{

	/** @return StockBasicControl */
	function create();
}
