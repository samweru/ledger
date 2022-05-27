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

    public function __construct(){

        //
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
    public function makeTrx(string $trx_type, string $trx_no, $amt){

        $sch = TrxQ::firstByTrxNo($trx_no);
        if(is_null($sch))
            new Raise("success:false|error:[schedule:unavailable]");

        $tsamt = Number::create($sch["amount"]); //Total Schedule Amount

        $ttamt = Number::create(0); //Total Trx Amount
        $trxs = Trx::allByTrxNo($trx_no);
        foreach($trxs as $trx)
            $ttamt = $ttamt->add($trx["amount"]);

        //Make balance positive
        $bal = $ttamt->subtract($tsamt)->negate();

        $pamt = Number::create(0); //Payment Amount
        if(!is_null($amt))
            $pamt = $pamt->add($amt);

        //When paid amount is empty check balance
        if($pamt->equals(0))
            $pamt = $pamt->add($bal);

        $teamt = $ttamt->add($pamt); //Total Expected Amount

        $status = "Pending";
        if($tsamt->equals($teamt))
            $status = "Final";

        if($teamt->gt($tsamt))
            new Raise("success:false|error:amt[exceeded:sch-amt]");

        Trx::add(array(

            "trx_no"=>$sch["trx_no"],
            'name' => $trx_type,
            'amount'=>$pamt->yield(),
            'token'=>$sch["token"],
            'status'=>$status
        ));

        Trx::updateByTrxNo($trx_no, array(

            "status"=>$status
        ));

        TrxQ::updateByTrxNo($trx_no, [

            "status"=>$status
        ]);     

        $this->makeDblEntry($trx_type, $pamt->yield());
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

    public function getBal(string $trx_no){

        $tsamt = Number::create(0); //Total Schedule Amount
        $sch = TrxQ::firstByTrxNo($trx_no);
        if(!empty($sch))
            $tsamt = $tsamt->add($sch["amount"]);

        $ttamt = Number::create(0); //Total Trx Amount
        $trxs = Trx::allByTrxNo($trx_no);        
        foreach($trxs as $trx)
            $ttamt = $ttamt->add($trx["amount"]);

        if($tsamt->equals(0))
            return 0;

        return $tsamt->subtract($ttamt)->yield();
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