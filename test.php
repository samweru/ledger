<?php

use Ledger\Cli;

require "bootstrap.php";

/**
 * trx last [<offset>]
 */
Cli::cmd("trx last", function($offset = null){

	echo sprintf(sprintf("Last:Trx[%s]", $offset));
});

/**
 * trx <trx_type> <tenant_no> <amount>
 */
Cli::cmd("trx", function($trx_type, $tenant_no, $amount){

	echo sprintf("trx_type:%s|tenant_no:%s|amount:%s", $trx_type, $tenant_no, $amount);
});

$argv = $_SERVER["argv"];
array_shift($argv);
Cli::run(implode(" ", $argv));