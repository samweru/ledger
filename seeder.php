<?php

require "bootstrap.php";

$coas = array(

	array(
	  "name" => "Payables",
	  "code" => "2010",
	  "rules"=> "type:liability|term:long",
	  "token"=> "payables"
	),
	array(
	  "name" => "Receivables",
	  "code" => "1050",
	  "rules"=> "type:asset|term:short",
	  "token"=> "receivables"
	),
	array(
	  "name" => "Rent:Receivables",
	  "code" => "1051",
	  "rules"=> "type:asset|term:short",
	  "token"=> "receivables"
	),
	array(
	  "name" => "Rent:Income",
	  "code" => "3051",
	  "rules"=> "type:revenue|term:short",
	  "token"=> "revenue"
	),
	array(
	  "name" => "Cash",
	  "code" => "1030",
	  "rules"=> "type:asset|term:short"
	)
);

foreach($coas as $coa){

	$flatbase->insert()->in('coa')->set($coa)->execute();
	$flatbase->insert()->in('trx_alloc')->set(array(

		"name"=>$coa["name"],
		"balance"=>0,

	))->execute();
}

$types = array(

	array(

		"name"=>"Rent:Due",
		"token"=>"Rent:Receivables|Rent:Income",
		"type"=>"schedule"
	),
	array(

		"name"=>"Rent:Paid",
		"token"=>"Cash|Rent:Receivables",
		"type"=>"payment"
	)
);

foreach($types as $type)
	$flatbase->insert()->in('trx_type')->set($type)->execute();