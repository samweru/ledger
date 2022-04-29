<?php

require "bootstrap.php";

// $flatbase->insert()->in('users')
//     ->set(['name' => 'Adam', 'height' => "6'4"])
//     ->execute();

// $flatbase->insert()->in('users')->set([
//     'name' => 'Adam',
//     'country' => 'UK',
//     'language' => 'English'
// ])->execute();

// $rs = $flatbase->read()->in('users')
//     ->where('name', '=', 'Adam')
//     ->first();

$rs = $flatbase->read()->in('coa')->first();

print_r($rs);

