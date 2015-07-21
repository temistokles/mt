<?php

require __DIR__ . '/../vendor/autoload.php';

$configurator = new Nette\Configurator;

// Enable Nette Debugger for error visualisation & logging
$configurator->setDebugMode([
    '94.113.177.5', // Petr - Brno
    '37.221.251.251', // Petr - Svetla n.S.
	'147.229.204.31', // Kapco
	'188.121.172.183', // Samo
	'89.111.69.34', // Martin - Rudoltice
	'193.86.138.194', // Martin - Brno
]);

$configurator->enableDebugger(__DIR__ . '/../log');

$configurator->setTempDirectory(__DIR__ . '/../temp');

$configurator->createRobotLoader()
		->addDirectory(__DIR__)
		->addDirectory(__DIR__ . '/../vendor/others')
		->register();

$configurator->addConfig(__DIR__ . '/config/config.neon');
$configurator->addConfig(__DIR__ . '/config/config.local.neon');

Drahak\Restful\DI\RestfulExtension::install($configurator);

$container = $configurator->createContainer();

return $container;
