#!/usr/bin/env php
<?php

require __DIR__.'/vendor/autoload.php';

use Symfony\Component\Console\Application;
use Symfony\Component\Dotenv\Dotenv;
use XSDGenerator\Commands\Generator;

$dotenv = new Dotenv();
$dotenv->load(__DIR__.'/.env');

$application = new Application();

$application->add(new Generator());

$application->run();