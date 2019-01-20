<?php

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\DependencyInjection\AddConsoleCommandPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use TeamdriveManager\Service\GoogleIamService;
use TeamdriveManager\Struct\User;

require_once 'vendor/autoload.php';

$containerBuilder = new ContainerBuilder();

$config = include 'config.php';
putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $config['serviceAccountFile']);
$containerBuilder->setParameter('config', $config);

$users = User::fromConfig($config);
$containerBuilder->setParameter('users', $users);
unset($users);

$containerBuilder->register(Google_Client::class)->setSynthetic(true);

$containerBuilder
    ->register(Google_Service_Drive::class, Google_Service_Drive::class)
    ->addArgument(new Reference(Google_Client::class));

$containerBuilder
    ->register(Google_Service_Directory::class, Google_Service_Directory::class)
    ->addArgument(new Reference(Google_Client::class));


$containerBuilder
    ->register(Google_Service_Iam::class, Google_Service_Iam::class)
    ->addArgument(new Reference(Google_Client::class));


$loader = new YamlFileLoader($containerBuilder, new FileLocator(__DIR__ . DIRECTORY_SEPARATOR . 'config'));
$loader->load('services.yaml');

$containerBuilder->addCompilerPass(new AddConsoleCommandPass());

$containerBuilder->compile();

$client = new Google_Client();
$client->useApplicationDefaultCredentials();
$client->setSubject($config['subject']);
$client->setScopes([Google_Service_Drive::DRIVE, Google_Service_Directory::ADMIN_DIRECTORY_GROUP, Google_Service_Iam::CLOUD_PLATFORM]);
$client->setDefer(true);
$containerBuilder->set(Google_Client::class, $client);

$container = $containerBuilder;
unset($containerBuilder);

$loop = React\EventLoop\Factory::create();

$application = new Application();

/** @var \Symfony\Component\Console\CommandLoader\CommandLoaderInterface $commandLoader */
$commandLoader = $container->get('console.command_loader');
$application->setCommandLoader($commandLoader);

$loop->run();

$application->run();