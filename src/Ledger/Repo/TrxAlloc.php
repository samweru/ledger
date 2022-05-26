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

	public function all(){

		$db = Connection::getDb();

		$coas = $db->read()->in("coa")->get()->getArrayCopy();
		foreach($coas as $coa)
			$all[$coa["name"]] = $coa["rules"];

		$allocs = $db->read()->in("trx_alloc")->get()->getArrayCopy();
		foreach($allocs as $alloc)
			$rs[] = array(

				"balance"=>$alloc["balance"],
				"name"=>$alloc["name"],
				"rules"=>$all[$alloc["name"]]
			);

		return $rs; 
	}
}