<?php

namespace Ledger;

use Strukt\Type\Str;
use Strukt\Type\Json;
use Strukt\Type\Number;
use Strukt\Raise;

class Book{

    public function __construct($db){

        $this->db = $db;
    }

    public function makeTrxNo(){

        return strtoupper(substr(sha1(rand()), 0, 10));
    }

    public function makeTrx($trx_type, $trx_no, $amt=null){

        $sch = $this->db->read()->in("trx_queue")
            ->where("trx_no", "==", $trx_no)
            ->get()
            ->first();

        if(is_null($sch))
        	new Raise("Schedule could not be found!");

        $status = "Pending";

        if(is_null($amt) || empty($amt) || $amt == $sch["amount"]){

            $amt = $sch["amount"];
            $status = "Final";
        }

        $trx = $this->db->read()->in("trx")
            ->where("trx_no", "==", $trx_no)
            ->get()
            ->first();

        if(is_null($trx)){

            $this->db->insert()->in("trx")->set(array(

                    "trx_no"=>$sch["trx_no"],
                    'name' => $trx_type,
                    'amount'=>$amt,
                    'token'=>$sch["token"],
                    'status'=>$status
                ))
                ->execute();  
        }
        else{
        	
            $trxamt = Number::create($trx["amount"])->add($amt);

            if($trxamt->gt($sch["amount"]))
            	new Raise("Inconsistent amount!");

            if($trxamt->lt($sch["amount"]) || $trxamt->equals($sch["amount"])){

            	if($trxamt->equals($sch["amount"]))
            		$status = "Final";

            	$this->db->update()->in("trx")
	                ->set(array(

	                    "amount"=>$trxamt->yield(),
	                    "status"=>$status
	                ))
	                ->where("trx_no", "==", $trx_no)            
	                ->execute();
            }
        }

        $this->db->update()->in("trx_queue")->set([

            "status"=>$status
        ])
        ->where("trx_no","==",$trx_no)
        ->execute();      

        $this->makeDblEntry($trx_type, $amt);
    }

    public function makeSchedule($trx_type, $amt, $token, $status="Pending"){

        $this->db->insert()->in("trx_queue")->set(array(

                "trx_no"=>$this->makeTrxNo(),
                'name' => $trx_type,
                'amount'=>$amt,
                'token'=>$token,
                'status'=>$status
            ))
            ->execute();

       $this->makeDblEntry($trx_type, $amt);
    }

    public function makeDblEntry($trx_type, $amt){

    	 $rTrxType = $this->db->read()->in('trx_type')
            ->where("name", "==", $trx_type)
            ->get()
            ->first();

        list($dr, $cr) = explode("|", $rTrxType["token"]);

        $this->withAmount($amt)->doDebit($dr)->doCredit($cr)->transfer();
    }

    public function getBal($trx_no){

    	$sch = $this->db->read()->in("trx_queue")
            ->where("trx_no", "==", $trx_no)
            ->get()
            ->first();

        $trx = $this->db->read()->in("trx")
            ->where("trx_no", "==", $trx_no)
            ->get()
            ->first();

        if(is_null($trx))
            $trx["amount"] = 0;

        return Number::create($sch["amount"])->subtract($trx["amount"])->yield();
    }

    public function withTrxType($trx_type){

        $trxs = null;
        $rsTrxType = $this->db->read()->in("trx_type")->get()->getArrayCopy();
        foreach($rsTrxType as $rTrxType)
            $trxs[$rTrxType["name"]] = $rTrxType["type"];

        return new class($trxs, $trx_type){

            public function __construct(array $trxs, string $trx_type){

                $this->trxs = $trxs;
                $this->trx_type = $trx_type;
            }

            public function exists(){

                return array_key_exists($this->trx_type, $this->trxs);
            }

            public function isType($type){

                return $this->trxs[$this->trx_type] == $type;
            }
        };
    }

    public function getAlloc($coa_name){

        $coa = $this->db->read()->in("coa")
            ->where("name", "==", $coa_name)
            ->get()
            ->first();

        list($coa_type, $term) = explode("|", $coa["rules"]);
        list($key, $type) = explode(":", $coa_type);

        $alloc = $this->db->read()->in('trx_alloc')
            ->where("name", "==", $coa_name)
            ->get()
            ->first();

        $alloc["type"] = $type;

        return $alloc;
    }

    public function withAmount($amt){

        return new class($this, $this->db, $amt){

            private $book;
            private $db;
            private $amt;
            private $trx;

            public function __construct($book, $db, $amt){

                $this->book = $book;
                $this->db = $db;
                $this->amt = $amt;
                $this->trx = [];
            }

            public function doDebit(string $coa_name){

                $alloc = $this->book->getAlloc($coa_name);

                $bal = Number::create($alloc["balance"]);
                if(in_array($alloc["type"], ["liability", "revenue"]))
                    $bal = $bal->subtract($this->amt);

                if(in_array($alloc["type"], ["asset", "equity", "expense"]))
                    $bal = $bal->add($this->amt);    

                $this->trx["dr"] = array(

                    "coa"=> $coa_name,
                    "bal"=> $bal->yield()
                );

                return $this;
            }

            public function doCredit(string $coa_name){

                $alloc = $this->book->getAlloc($coa_name);

                $bal = Number::create($alloc["balance"]);
                if(in_array($alloc["type"], ["liability", "revenue"]))
                    $bal = $bal->add($this->amt);

                if(in_array($alloc["type"], ["asset", "equity", "expense"]))
                    $bal = $bal->subtract($this->amt);

                $this->trx["cr"] = array(

                    "coa" => $coa_name,
                    "bal" => $bal->yield()
                );

                return $this;
            }

            private function doAlloc(array $trx){

                $this->db->update()->in('trx_alloc')
                    ->set([

                        "balance"=>$trx["bal"]
                    ])
                    ->where("name", "==", $trx["coa"])
                    ->execute();
            }

            public function transfer(){

                $this->doAlloc($this->trx["cr"]);
                $this->doAlloc($this->trx["dr"]);
            }
        };
    }
}