Q4M Bundle
==========

This is a client of Q4M to use in Symfony2 as a Bundle

[CI(Continuous Integration)](http://jenkins.siny.jp:8080/job/SinyQ4MBundle/)

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

Set up in order to unit test
----------------------------

### 1) Create new user, database, and table for testing Q4MBundle

```shell
$ mysql -uroot mysql
```

```mysql
GRANT ALL ON q4mtest.* TO q4mtestuser@localhost IDENTIFIED BY 'q4mtestpassword';
```

```shell
$ mysqladmin -uroot flush-privileges
$ mysql -uq4mtestuser -pq4mtestpassword
```

```mysql
CREATE DATABASE IF NOT EXISTS q4mtest DEFAULT CHARSET UTF8;
use q4mtest;
CREATE TABLE `q4mtest` (
  `id` int(11) NOT NULL,
  `message` varchar(255) DEFAULT NULL,
  `priority` tinyint(3) unsigned NOT NULL DEFAULT '10'
) ENGINE=QUEUE DEFAULT CHARSET=utf8;
CREATE TABLE `q4mtest_row_priority` (
  `id` int(11) NOT NULL,
  `message` varchar(255) DEFAULT NULL,
  `priority` tinyint(3) unsigned NOT NULL DEFAULT '10'
) ENGINE=QUEUE DEFAULT CHARSET=utf8;
```
