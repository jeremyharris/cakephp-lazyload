<?php
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\Fixture\SchemaLoader;

require_once 'vendor/autoload.php';


// Path constants to a few helpful things.
define('ROOT', dirname(__DIR__) . DS);
define('APP', ROOT . 'tests' . DS . 'test_app' . DS);
define('TMP', sys_get_temp_dir() . DS);

define('CAKE_CORE_INCLUDE_PATH', ROOT . 'vendor' . DS . 'cakephp' . DS . 'cakephp' . DS);
define('CAKE', CAKE_CORE_INCLUDE_PATH . 'src' . DS);

require_once CAKE_CORE_INCLUDE_PATH . 'config' . DS . 'bootstrap.php';

/**
 * Load global functions.
 */
require CAKE . 'functions.php';

date_default_timezone_set('UTC');
mb_internal_encoding('UTF-8');

Configure::write('debug', true);
Configure::write('App', [
    'namespace' => 'JeremyHarris\\LazyLoad\\TestApp',
    'encoding' => 'UTF-8',
    'base' => false,
    'baseUrl' => false,
    'dir' => 'src',
    'webroot' => 'webroot',
    'www_root' => APP . 'webroot',
    'fullBaseUrl' => 'http://localhost',
    'imageBaseUrl' => 'img/',
    'jsBaseUrl' => 'js/',
    'cssBaseUrl' => 'css/',
    'paths' => [
        'plugins' => [APP . 'Plugin' . DS],
        'templates' => [APP . 'Template' . DS]
    ]
]);

// Ensure default test connection is defined
if (!getenv('db_dsn')) {
    putenv('db_dsn=sqlite://./asd.sqlite');
}

ConnectionManager::setConfig('test', [
    'url' => getenv('db_dsn'),
    'timezone' => 'UTC',
]);




// Load one or more SQL files.
(new SchemaLoader())->loadSqlFiles(
    ROOT . 'tests' . DS. 'schema.sql', 
    'test'
);