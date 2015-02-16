<?php

// Load the composer autoload
require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/src/SplClassLoader.php');

$classLoaderBlogwerk = new SplClassLoader('Blogwerk', __DIR__ . '/src');
$classLoaderBlogwerk->register();

$classLoaderComotive = new SplClassLoader('Comotive', __DIR__ . '/src');
$classLoaderComotive->register();

$classLoaderComotive = new SplClassLoader('zepi', __DIR__ . '/src');
$classLoaderComotive->register();

echo 
'                                                                             ' . PHP_EOL .
'  _____ _____ _____    _____ __       _____                     _            ' . PHP_EOL .
' |   __|     |  |  |  |     |  |     |     |___ ___ _ _ ___ ___| |_ ___ ___  ' . PHP_EOL .
' |__   | | | |    -|  | | | |  |__   |   --| . |   | | | -_|  _|  _| -_|  _| ' . PHP_EOL .
' |_____|_|_|_|__|__|  |_|_|_|_____|  |_____|___|_|_|\_/|___|_| |_| |___|_|   ' . PHP_EOL .
'                                                                             ' . PHP_EOL . PHP_EOL;

// Load the core
$core = new Blogwerk\CliCore();
$core->declareArguments();

// Load the configuration
$configuration = new Blogwerk\Configuration($core);
$configuration->readFromFile();

// Create the converter process controller
$converter = new Blogwerk\Converter($core, $configuration);

// Convert the data
$converter->convert();

