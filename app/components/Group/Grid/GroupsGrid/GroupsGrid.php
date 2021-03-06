<?php

namespace App\Components\Group\Grid;

use App\Components\BaseControl;
use App\Extensions\Grido\BaseGrid;
use App\Model\Entity\Group;
use Grido\DataSources\Doctrine;
use Grido\Grid;

class GroupsGrid extends BaseControl
{

	/** @return Grid */
	protected function createComponentGrid()
	{
		$grid = new BaseGrid();
		$grid->setTranslator($this->translator);
		$grid->setTheme(BaseGrid::THEME_METRONIC);

		$repo = $this->em->getRepository(Group::getClassName());
		$qb = $repo->createQueryBuilder('g');
		$grid->model = new Doctrine($qb, []);

		$grid->setDefaultSort([
			'name' => 'ASC',
		]);

		$grid->addColumnNumber('level', 'ID #')
				->setSortable()
				->setFilterNumber();
		$grid->getColumn('level')->headerPrototype->width = '5%';

		$grid->addColumnText('name', 'Group')
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
				->elementPrototype->class[] = 'red';

		$grid->setActionWidth("20%");

		return $grid;
	}

}

interface IGroupsGridFactory
{

	/** @return GroupsGrid */
	function create();
}
