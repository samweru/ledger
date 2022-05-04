<?php

namespace Ledger\Repo;

use Ledger\Connection;

class Trx{

	public function firstByTrxNo(string $trx_no){

		$db = Connection::getDb();

		$trx = $db->read()->in("trx")
            ->where("trx_no", "==", $trx_no)
            ->get()
            ->first();

        return $trx;
	}

	public function all(){

		$db = Connection::getDb();

		return $db->read()->in("trx")->get()->getArrayCopy();
	}

	public function add(array $row){

		$db = Connection::getDb();

		$db->insert()->in("trx")->set($row)->execute(); 
	}

	public function updateByTrxNo(string $trx_no, array $row){

		$db = Connection::getDb();

		$db->update()->in("trx")
            ->set($row)
            ->where("trx_no", "==", $trx_no)            
            ->execute();
	}
}