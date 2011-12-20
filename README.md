Q4M Bundle
==========

This is a client of Q4M to use in Symfony2 as a Bundle

Installation
-------------

### 1) Add the following lines in your deps file

```
[SinyQ4MBundle]
    git=git://github.com/s-edy/SinyQ4MBundle.git
    target=bundles/Siny/Q4MBundle
```

### 2) Run venders scpript

```
$ php bin/venders install
```

### 3) Add the Siny namespace to your autoloader

```php
<?php
// app/autoload.php

$loader->registerNamespaces(array(
	// ...
	'Siny'             => __DIR__.'/../vendor/bundles',
));
```

### 4) Add this bundle to your application's kernel

```php
<?php
// app/AppKernel.php

class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = array(
        	// ...
            new Siny\Q4MBundle\SinyQ4MBundle(),
        );
```
