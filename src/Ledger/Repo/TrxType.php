<?php

namespace Ledger\Repo;

use Ledger\Connection;

class TrxType{

	public function getByType($type){

		$db = Connection::getDb();

		$types = $db->read()->in("trx_type")
            ->where("type", "==", $type)
            ->get()
            ->getArrayCopy();

        return $types;
	}

	public function first(string $name = null){

		$db = Connection::getDb();

		$q = $db->read()->in('trx_type');

		if(!is_null($name))
            $q = $q->where("name", "==", $name);
            
        return $q->get()->first();
	}

	public function all(){

		$db = Connection::getDb();

		return $db->read()->in("trx_type")->get()->getArrayCopy();
	}
}