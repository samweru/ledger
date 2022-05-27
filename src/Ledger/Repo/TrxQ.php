<?php

namespace Ledger\Repo;

use Ledger\Connection;

/**
* Schedule
*/
class TrxQ{

	public function allByTrxNo(string $trx_no){

		$db = Connection::getDb();

		$schs = $db->read()->in("trx_queue")
            ->where("trx_no", "==", $trx_no)
            ->get()
            ->getArrayCopy();

        return $schs;
	}

	public function allByStatus(string $status){

		$db = Connection::getDb();

		$schs = $db->read()->in("trx_queue")
            ->where("status", "==", $status)
            ->get()
            ->getArrayCopy();

        return $schs;
	}

	public function firstByTrxNo(string $trx_no){

		$db = Connection::getDb();

		$sch = $db->read()->in("trx_queue")
            ->where("trx_no", "==", $trx_no)
            ->get()
            ->first();

        return $sch;
	}

	public function all(){

		$db = Connection::getDb();

		return $db->read()->in("trx_queue")->get()->getArrayCopy();
	}

	public function add(array $rows){

		$db = Connection::getDb();

        $db->insert()->in("trx_queue")->set($rows)->execute();
	}

	public function updateByTrxNo(string $trx_no, array $row){

		$db = Connection::getDb();

		$db->update()->in("trx_queue")->set($row)
        	->where("trx_no", "==", $trx_no)
        	->execute(); 
	}
}