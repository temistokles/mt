<?php

namespace App\Components\Auth;

use App\Components\BaseControl;
use App\Forms\Form;
use App\Forms\Renderers\MetronicFormRenderer;
use App\Model\Entity\Role;
use App\Model\Entity\User;
use App\Model\Facade\RoleFacade;
use App\Model\Facade\UserFacade;
use App\Model\Storage\SignUpStorage;
use Nette\Forms\IControl;
use Nette\Utils\ArrayHash;

class SignUp extends BaseControl
{
	// <editor-fold desc="events">

	/** @var array */
	public $onSuccess = [];

	// </editor-fold>
	// <editor-fold desc="injects">

	/** @var IFacebookConnectFactory @inject */
	public $iFacebookConnectFactory;

	/** @var ITwitterConnectFactory @inject */
	public $iTwitterConnectFactory;

	/** @var UserFacade @inject */
	public $userFacade;

	/** @var SignUpStorage @inject */
	public $session;

	// </editor-fold>

	/** @return Form */
	protected function createComponentForm()
	{
		$form = new Form();
		$form->setRenderer(new MetronicFormRenderer());
		$form->setTranslator($this->translator);

		$form->addServerValidatedText('mail', 'E-mail')
				->setRequired('Please enter your e-mail.')
				->setAttribute('placeholder', 'E-mail')
				->addRule(Form::EMAIL, 'E-mail has not valid format.')
				->addServerRule([$this, 'validateMail'], $this->translator->translate('%value% is already registered.'))
				->setOption('description', 'for example: example@domain.com');

		$helpText = $this->translator->translate('At least %count% characters long.', NULL, ['count' => $this->settings->passwords->minLength]);
		$form->addPassword('password', 'Password')
				->setAttribute('placeholder', 'Password')
				->setRequired('Please enter your password')
				->addRule(Form::MIN_LENGTH, 'Password must be at least %count% characters long.', $this->settings->passwords->minLength)
				->setOption('description', $helpText);

		$form->addPassword('passwordVerify', 'Re-type Your Password')
				->setAttribute('placeholder', 'Re-type Your Password')
				->setRequired('Please re-enter your password')
				->addRule(Form::EQUAL, 'Passwords must be equal.', $form['password']);

		$form->addSubmit('continue', 'Continue');

		$form->onSuccess[] = $this->formSucceeded;
		return $form;
	}

	public function validateMail(IControl $control, $arg = NULL)
	{
		return $this->userFacade->isUnique($control->getValue());
	}

	/**
	 * @param Form $form
	 * @param ArrayHash $values
	 */
	public function formSucceeded(Form $form, ArrayHash $values)
	{
		$entity = new User();
		$entity->setMail($values->mail)
				->setLocale($this->translator->getLocale())
				->setCurrency($this->exchange->getDefault()->getCode())
				->setPassword($values->password);
		$roleRepo = $this->em->getRepository(Role::getClassName());
		$entity->requiredRole = $roleRepo->findOneByName(Role::USER);

		$this->session->verification = FALSE;

		$this->onSuccess($this, $entity);
	}

	public function renderLogin()
	{
		$this->setTemplateFile('login');
		parent::render();
	}

	public function renderSocial()
	{
		$this->setTemplateFile('social');
		parent::render();
	}

	// <editor-fold desc="controls">

	/** @return FacebookConnect */
	protected function createComponentFacebook()
	{
		return $this->iFacebookConnectFactory->create();
	}

	/** @return TwitterConnect */
	protected function createComponentTwitter()
	{
		return $this->iTwitterConnectFactory->create();
	}

	// </editor-fold>
}

interface ISignUpFactory
{

	/** @return SignUp */
	function create();
}
