<?php

use Strukt\Loop;
use Strukt\Cmd;
use Strukt\Type\Str;
use Strukt\Type\Json;
use Strukt\Type\Number;
use Strukt\Type\Arr;
use Strukt\Fs;

use Ledger\Cli\Cli;
use Ledger\Cli\Buffer;
use Ledger\Repo\Trx;
use Ledger\Repo\TrxQ;
use Ledger\Repo\TrxType;
use Ledger\Repo\TrxAlloc;

require "bootstrap.php";

$book = new Ledger\Book();

readline_read_history(".history");

readline_completion_function(function($line, $idx){

    $rli = readline_info();
    $ln = Str::create(trim(substr($rli['line_buffer'], 0, $rli['end'])));

    $cmds = [];
    $cmdls = Cmd::ls();
    foreach($cmdls as $cmd)
        if(!Str::create($cmd)->startsWith("init"))
            if($ln->notEquals($cmd))
                $cmds[$cmd] = $cmd;

    $tschs = TrxType::getByType("schedule");
    foreach($tschs as $tsch){

        $cmdsch = sprintf("sch %s", $tsch["name"]);
        if($ln->notEquals($cmdsch) && !$ln->empty())
            $cmds[$cmdsch] = $tsch["name"];
    }

    $ttrxs = TrxType::getByType("payment");
    foreach($ttrxs as $ttrx){

        $cmdtrx = sprintf("trx %s", $ttrx["name"]);
        if($ln->notEquals($cmdtrx) && !$ln->empty())
            $cmds[$cmdtrx] = $ttrx["name"];
    }

    $line = $ln->yield();

    $matches = [];
    $cmdkeys = preg_grep("/^$line/", array_keys($cmds));
    foreach($cmdkeys as $key){

        $sKey = Str::create($key);
        if($sKey->startsWith($line) && !$ln->empty())
            $match = Arr::create($sKey->split(" "))->last()->yield();

        if($sKey->endsWith("ls"))
            $match = $key;
        
        if($ln->empty())
            $match = $cmds[$key];

        $matches[] = $match;
    }

    return array_flip(array_flip($matches));
});

Loop::halt(function() use($book){

    Cmd::add("help", function(){

        $help = [];
        $help = array_merge($help, explode("\n", Cmd::exec("sch ?")));
        $help = array_merge($help, explode("\n", Cmd::exec("trx ?")));
        $help[] = Cmd::exec("bal ?");

        $helps = [];
        foreach($help as $line)
            $helps[] = sprintf("  %s", $line);

        return sprintf("\n%s\n", implode("\n", $helps));
    });

    Cmd::add("trx:alloc ls", function(){

        $rs = TrxAlloc::all();

        foreach($rs as $row)
            $rows[] = sprintf("  %s %s %s", str_pad($row["balance"], 10), 
                                            str_pad($row["name"], 17),
                                            $row["rules"]);

        return sprintf("\n%s\n", implode("\n", $rows));
    });

    Cmd::add("trx:type ls", function(){

        $rs = TrxType::all();

        foreach($rs as $row)
            $rows[] = sprintf("  %s - %s", str_pad($row["name"], 10), $row["type"]);

        return sprintf("\n%s\n", implode("\n", $rows));
    });

    Cmd::add("sch help", function(){

        return Cmd::exec("sch ?");
    });

    Cmd::add("sch ?", function(){

        $help = array(

            "sch <trx_type> <token> <amount>",
            "sch last [<offset>]"
        );
        
        return implode("\n", $help);
    });

    /**
     * sch last [<offset>]
     */
    Cmd::add("sch last", function(int $offset = null){

        $rs = TrxQ::all();
        $rs = array_reverse($rs);

        if(is_null($offset) || $offset < 1)
            $offset = 1;

        array_splice($rs, $offset);

        return Json::pp($rs);
    });

    /**
     *  sch <trx_type> <token> <amt>
     *  
     *  trx_type:string   Transaction Type - example Rent:Due
     *  token:string      Other Identifiers - example type:tenant|id:001
     *  amount:number     Transaction Amount
     */
    Cmd::add("sch", function(string $trx_type, string $token, $amt) use($book){

        $trxType = $book->withTrxType($trx_type);

        if($trxType->exists()){

            if($trxType->isType("schedule")){

                $trx_no = $book->makeSchedule($trx_type, $token, $amt);

                Buffer::add("history", sprintf("trx:descr %s ", $trx_no));

                return 'success:true|on:trx-schedule';
            }
            
            return "success:false|on:trx-schedule|error:expected[type:schedule]";
        }

        return 'success:false|on:trx-schedule';
    });

    Cmd::add("trx help", function(){

        return Cmd::exec("trx ?");
    });

    Cmd::add("trx ?", function(){

        $help = array(

            "trx <trx_type> <trx_no> [<amount>]",
            "trx last [<offset>]",
            "trx:id <trx_no>",
            "trx:pay <trx_type> <amount>",
            "trx:type ls",
            "trx:alloc ls",
            "trx:descr <trx_no> <descr*>",
            "trx:status <status>"
        );

        return implode("\n", $help);
    });

    /**
     * trx:descr <trx_no> <descr*>
     */
    Cmd::add("trx:descr", function(string $trx_no, ...$descr){

        $r = TrxQ::firstByTrxNo($trx_no);

        if(array_key_exists("descr", $r))
            if(!empty($r["descr"]))
                return "success:false|on:schedule|error:[descr:exists]";

        $r["descr"] = implode(" ", $descr);

        TrxQ::updateByTrxNo($trx_no, $r);

        return "success:true|on:schedule|update:descr";
    });

    /**
     * trx last [<offset>]
     */
    Cmd::add("trx last", function(int $offset = null){

        $rs = Trx::all();
        $rs = array_reverse($rs);

        if(is_null($offset) || $offset < 1)
            $offset = 1;

        array_splice($rs, $offset);

        return Json::pp($rs);
    });

    /**
     * trx:pay <trx_type> <amt>
     * 
     * trx_type:string Transaction Type - example Rent:Paid
     * amt:number      Transaction Amount
     */
    Cmd::add("trx:pay", function(string $trx_type, string $amt) use($book){

         $trxType = $book->withTrxType($trx_type);

        if($trxType->exists()){

            if($trxType->isType("payment")){

                try{

                    $book->makePay($trx_type, $amt);

                    return "success:true|on:trx-pay";
                }
                catch(\Exception $e){

                    return $e->getMessage();
                }
            }
            
            return "success:false|on:trx-pay|error:expected[type:payment]";
        }
        
        return "success:false|on:trx-pay";        
    });

    /**
     * trx <trx_type> <trx_no> [<amt>]
     * 
     * trx_type:string Transaction Type - example Rent:Paid
     * trx_no:string   Transaction Number
     * amt:number      Transaction Amount
     */
    Cmd::add("trx", function(string $trx_type, string $trx_no, $amt = null) use($book){

        $trxType = $book->withTrxType($trx_type);

        if($trxType->exists()){

            if($trxType->isType("payment")){

                try{

                    $book->makeTrx($trx_type, $trx_no, $amt);

                    return "success:true|on:trx";
                }
                catch(\Exception $e){

                    return $e->getMessage();
                }
            }
            
            return "success:false|on:trx|error:expected[type:payment]";
        }
        
        return "success:false|on:trx";
    });

    /**
    * trx:id <trx_no>
    * 
    * trx_no:string Transaction number
    */
    Cmd::add("trx:id", function(string $trx_no){

        $schs = TrxQ::allByTrxNo($trx_no);
        if(empty($schs))
            return "error:trx|either:[trx:non-existsent|trx-type:direct-payment]";

        $trxs = Trx::allByTrxNo($trx_no);

        $rs = [];
        foreach(array_merge($schs, $trxs) as $trx)
            $rs["transfers"][] = array(

                "name"=>$trx["name"],
                "amount"=>$trx["amount"],
                "token"=>$trx["token"]
            );

        $bal = Number::create(0);
        $sBal = Str::create(Cmd::exec("bal", [$trx_no]))->replace("balance:", "")->yield();
        $bal = $bal->add($sBal);

        $status = "Final";
        if(!$bal->equals(0))
            $status = "Pending";

        $rs = array_merge($rs, array(

            "balance"=>$bal->yield(),
            "status"=>$status
        ));

        return Json::pp($rs);
    });

    /**
    * trx:status <status>
    * 
    * status:string Either pending|final
    */
    Cmd::add("trx:status", function(string $status){

        $status = ucfirst($status);
        if(!in_array($status, ["Pending", "Final"]))
            return "error:either[status:pending|status:final]";

        $schs = TrxQ::allByStatus(ucfirst($status));
        if(empty($schs))
            return "success:true|message:not-found";

        $rs = [];
        foreach($schs as $sch){

            $row = sprintf("  %s %s", str_pad($sch["trx_no"], 10), 
                                        str_pad($sch["name"], 10));

            if($status == "Pending")
                $row = sprintf("%s %s", $row, Cmd::exec("bal", [$sch["trx_no"]]));

            $rs[] = $row;
        }

        return sprintf("\n%s\n", implode("\n", $rs));
    });

    Cmd::add("bal help", function(){

        return Cmd::exec("bal ?");
    });

    Cmd::add("bal ?", function(){

        return "bal <trx_no>";
    });

    /**
     * bal <trx_no>
     * 
     * trx_no:string Transaction number
     */
    Cmd::add("bal", function(string $trx_no) use($book){

        $bal = $book->getBal($trx_no);

        return sprintf("balance:%s", $bal);
    });


    Cmd::add("exit", function(){

        Loop::pause(false);
        readline_write_history(".history");

        exit("Bye bye!\n");
    });


    $line = trim(readline("Book>> "));

    try{

        echo(sprintf("%s\n", Cli::run($line)));

        Buffer::attach("history", $line);
        $history = Buffer::purge("history");
        foreach($history as $line)
            readline_add_history($line); 
    }
    catch(\ArgumentCountError $e){

        echo "error:either[args:inconsistent|args:invalid]\n";        
    }
    catch(\Exception $e){

        echo sprintf("%s\n", $e->getMessage());
    }
});

Loop::run();