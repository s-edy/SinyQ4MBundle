<?php
/**
* This file is a part of Siny\Amazon\ProductAdvertisingAPIBundle package.
*
* (c) Shinichiro Yuki <edy@siny.jp>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

$bundleDirectory = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..');
$vendorDirectory = realpath($bundleDirectory . DIRECTORY_SEPARATOR . 'vendor');

require_once $vendorDirectory . '/symfony/src/Symfony/Component/ClassLoader/UniversalClassLoader.php';

use Symfony\Component\ClassLoader\UniversalClassLoader;

$loader = new UniversalClassLoader();
$loader->registerNamespace('Siny', $bundleDirectory);
$loader->register();

spl_autoload_register(function($class)
{
    if (strpos($class, 'Siny\\Q4MBundle\\') === 0) {
        $file = __DIR__ . '/../' . implode('/', array_slice(explode('\\', $class), 2)) . '.php';
        if (file_exists($file) === false) {
            return false;
        }
        require_once $file;
    }
});
