<?php

namespace Ledger;

use Strukt\Type\Str;
use Strukt\Type\Json;
use Strukt\Type\Number;
use Strukt\Raise;

use Ledger\Repo\Trx;
use Ledger\Repo\TrxQ;
use Ledger\Repo\TrxType;
use Ledger\Repo\TrxAlloc;
use Ledger\Repo\Coa;

use Ledger\Connection;

class Book{

    // private $trx_nos;

    public function __construct(){

        // $this->trx_nos = [];
    }

    public function makeTrxNo(){

        return strtoupper(substr(sha1(rand()), 0, 10));
    }

    /**
    * Direct payment no schedule [status:Final]
    */
    public function makePay(string $trx_type, $amt, string $token = "type:pay|is:direct"){

        $trx_no = $this->makeTrxNo();

        Trx::add(array(

            "trx_no"=>$trx_no,
            'name' => $trx_type,
            'amount'=>$amt,
            'token'=>$token,
            'status'=>"Final"
        ));

        $this->makeDblEntry($trx_type, $amt);
    }

    /**
    * Make payment that corresponds to schedule
    */
    public function makeTrx($trx_type, $trx_no, $amt=null){

        $sch = TrxQ::firstByTrxNo($trx_no);

        if(is_null($sch))
        	new Raise("success:false|error:[schedule:unavailable]");

        $status = "Pending";

        if(is_null($amt) || empty($amt) || $amt == $sch["amount"]){

            $amt = $sch["amount"];
            $status = "Final";
        }

        $trx = Trx::firstByTrxNo($trx_no);

        if(is_null($trx)){

            Trx::add(array(

                "trx_no"=>$sch["trx_no"],
                'name' => $trx_type,
                'amount'=>$amt,
                'token'=>$sch["token"],
                'status'=>$status
            ));
        }
        else{
        	
            $trxamt = Number::create($trx["amount"])->add($amt);

            if($trxamt->gt($sch["amount"]))
            	new Raise("success:false|error:[amount:inconsistent]");

            if($trxamt->lt($sch["amount"]) || $trxamt->equals($sch["amount"])){

            	if($trxamt->equals($sch["amount"]))
            		$status = "Final";

                Trx::updateByTrxNo($trx_no, array(

                    "amount"=>$trxamt->yield(),
                    "status"=>$status
                ));
            }
        }

        TrxQ::updateByTrxNo($trx_no, [

            "status"=>$status
        ]);     

        $this->makeDblEntry($trx_type, $amt);
    }

    /**
    * Make schedule for payment - preparation for transaction
    */
    public function makeSchedule(string $trx_type, 
                                    string $token,
                                    $amt,  
                                    string $status = "Pending"){

        $trx_no = $this->makeTrxNo();

        TrxQ::add(array(

            "trx_no"=>$trx_no,
            'name' => $trx_type,
            'amount'=>$amt,
            'token'=>$token,
            'status'=>$status
        ));

       $this->makeDblEntry($trx_type, $amt);

       return $trx_no;
    }

    public function makeDblEntry($trx_type, $amt){

        $rTrxType = TrxType::first($trx_type);

        list($dr, $cr) = explode("|", $rTrxType["token"]);

        $this->withAmount($amt)->doDebit($dr)->doCredit($cr)->transfer();
    }

    public function getBal($trx_no){

        $sch = TrxQ::firstByTrxNo($trx_no);

        $trx = Trx::firstByTrxNo($trx_no);

        if(is_null($trx))
            $trx["amount"] = 0;

        return Number::create($sch["amount"])->subtract($trx["amount"])->yield();
    }

    public function withTrxType($trx_type){

        $trxs = null;
        $rsTrxType = TrxType::all();
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

        $coa = Coa::firstByName($coa_name);

        list($coa_type, $term) = explode("|", $coa["rules"]);
        list($key, $type) = explode(":", $coa_type);

        $alloc = TrxAlloc::firstByCoaName($coa_name);

        $alloc["type"] = $type;

        return $alloc;
    }

    public function withAmount($amt){

        return new class($this, $amt){

            private $book;
            private $amt;
            private $trx;

            public function __construct($book, $amt){

                $this->book = $book;
                $this->amt = $amt;
                $this->trx = [];
            }

            public function doDebit(string $coa_name){

                $alloc = $this->book->getAlloc($coa_name);

                $bal = Number::create($alloc["balance"]);
                if(in_array($alloc["type"], ["liability", 
                                                "revenue",
                                                "contra-revenue"]))
                    $bal = $bal->subtract($this->amt);

                if(in_array($alloc["type"], ["contra-equity", 
                                                "contra-expense",
                                                "asset", 
                                                "equity", 
                                                "expense"]))
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
                if(in_array($alloc["type"], ["liability", 
                                                "revenue",
                                                "contra-revenue"]))
                    $bal = $bal->add($this->amt);

                if(in_array($alloc["type"], ["contra-equity", 
                                                "contra-expense",
                                                "asset", 
                                                "equity", 
                                                "expense"]))
                    $bal = $bal->subtract($this->amt);

                $this->trx["cr"] = array(

                    "coa" => $coa_name,
                    "bal" => $bal->yield()
                );

                return $this;
            }

            private function doAlloc(array $trx){

                TrxAlloc::updateByCoaName($trx["coa"], [

                    "balance"=>$trx["bal"]
                ]);
            }

            public function transfer(){

                $this->doAlloc($this->trx["cr"]);
                $this->doAlloc($this->trx["dr"]);
            }
        };
    }
}