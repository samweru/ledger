<?php

require "bootstrap.php";

$which = $argv[1];

$which_types = ["merch","rent"];
if(!in_array($which, $which_types))
	exit("expected:either[merch, rent]");

$coas = json_decode(Strukt\Fs::cat(sprintf("db/seed/%s/coa.json", $which)), 1);
$types = json_decode(Strukt\Fs::cat(sprintf("db/seed/%s/trx_type.json", $which)), 1);

foreach($coas as $coa){

	$flatbase->insert()->in('coa')->set($coa)->execute();
	$flatbase->insert()->in('trx_alloc')->set(array(

		"name"=>$coa["name"],
		"balance"=>0,

	))->execute();
}

foreach($types as $type)
	$flatbase->insert()->in('trx_type')->set($type)->execute();