<?php

namespace App\Components\Page\Grid;

use App\Components\BaseControl;
use App\Extensions\Grido\BaseGrid;
use App\Model\Entity\Page;
use Grido\DataSources\Doctrine;
use Grido\Grid;

class PagesGrid extends BaseControl
{

	/** @return Grid */
	protected function createComponentGrid()
	{
		$grid = new BaseGrid();
		$grid->setTranslator($this->translator);
		$grid->setTheme(BaseGrid::THEME_METRONIC);

		$repo = $this->em->getRepository(Page::getClassName());
		$qb = $repo->createQueryBuilder('p')
				->leftJoin('p.translations', 't')
				->where('t.locale = :lang OR t.locale = :defaultLang')
				->setParameter('lang', $this->translator->getLocale())
				->setParameter('defaultLang', $this->translator->getDefaultLocale());
		$grid->model = new Doctrine($qb, [
			'name' => 't.name',
		]);

		$grid->setDefaultSort([
			'name' => 'ASC',
		]);

		$grid->addColumnText('name', 'Page')
				->setCustomRender(function ($row) {
					return $row->translate($this->translator->getLocale())->name;
				})
				->setSortable()
				->setFilterText()
				->setSuggestion();

		$grid->addColumnText('comment', 'Comment')
				->setSortable()
				->setFilterText()
				->setSuggestion();

		$grid->addColumnText('link', 'Link')
				->setSortable()
				->setFilterText()
				->setSuggestion();

		$grid->addActionHref('edit', 'Edit')
				->setIcon('fa fa-edit');

		$grid->addActionHref('delete', 'Delete')
						->setIcon('fa fa-trash-o')
						->setConfirm(function($item) {
							$message = $this->translator->translate('Are you sure you want to delete \'%name%\'?', NULL, ['name' => (string) $item]);
							return $message;
						})
						->setDisable(function($item) {
							return !$this->presenter->canDelete($item);
						})
				->elementPrototype->class[] = 'red';

		$grid->setActionWidth("20%");

		return $grid;
	}

}

interface IPagesGridFactory
{

	/** @return PagesGrid */
	function create();
}
