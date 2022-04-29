<?php 

$loader = require "vendor/autoload.php";
$loader->add('Ledger', __DIR__.'/src/');

$storage = new Flatbase\Storage\Filesystem('./flatbase');
$flatbase = new Flatbase\Flatbase($storage);