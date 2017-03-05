<?php

require __DIR__ . '/../vendor/autoload.php';

use OmekaTestHelper\Bootstrap;

Bootstrap::bootstrap(__DIR__);
Bootstrap::loginAsAdmin();
Bootstrap::enableModule('IiifServer');
