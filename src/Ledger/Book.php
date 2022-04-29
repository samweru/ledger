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

        $this->withDebit($dr, $amt);
        $this->withCredit($cr, $amt);
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

        return Number::create($sch["amount"])->subtract($trx["amount"])->yield();
    }

    public function getMeta(){

        $rsCoa = $this->db->read()->in("coa")->get()->getArrayCopy();
        $rsTrxType = $this->db->read()->in("trx_type")->get()->getArrayCopy();

        $rows = [];

        foreach($rsCoa as $rCoa)
                $rows["coa"][] = $rCoa["name"]; 

        foreach($rsTrxType as $rTrxType)
            $rows["trx"][$rTrxType["name"]] = $rTrxType["type"];

        return $rows;
    }   

    public function withDebit($coa_name, $amt){

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

        $bal = Number::create($alloc["balance"]);
        if(in_array($type, ["liability", "revenue"]))
            $bal = $bal->subtract($amt);

        if(in_array($type, ["asset", "equity", "expense"]))
            $bal = $bal->add($amt);

        $this->db->update()->in('trx_alloc')
            ->set([

                "balance"=>$bal->yield()
            ])
            ->where("name", "==", $coa_name)
            ->execute();
    }

    public function withCredit($coa_name, $amt){

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

        $bal = Number::create($alloc["balance"]);
        if(in_array($type, ["liability", "revenue"]))
            $bal = $bal->add($amt);

        if(in_array($type, ["asset", "equity", "expense"]))
            $bal = $bal->subtract($amt);

        $this->db->update()->in('trx_alloc')
            ->set([

                "balance"=>$bal->yield()
            ])
            ->where("name", "==", $coa_name)
            ->execute();
    }
}