<?php

namespace Ledger\Repo;

use Ledger\Connection;

class Coa{

	public function firstByName(string $name){

		$db = Connection::getDb();

		$coa = $db->read()->in("coa")
            ->where("name", "==", $name)
            ->get()
            ->first();

        return $coa;
	}
}