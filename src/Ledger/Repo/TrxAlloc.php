<?php

namespace Ledger\Repo;

use Ledger\Connection;

class TrxAlloc{

	public function firstByCoaName(string $name){

		$db = Connection::getDb();

		$alloc = $db->read()->in("trx_alloc")
            ->where("name", "==", $name)
            ->get()
            ->first();

        return $alloc;
	}

	public function updateByCoaName(string $name, array $row){

		$db = Connection::getDb();

		$db->update()->in('trx_alloc')
            ->set($row)
            ->where("name", "==", $name)
            ->execute();
	}
}