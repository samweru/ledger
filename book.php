<?php

require "bootstrap.php";

use Strukt\Type\Str;
use Strukt\Type\Json;
use Strukt\Type\Number;
use Strukt\Fs;

use Ledger\Cli;

$storage = new Flatbase\Storage\Filesystem('./flatbase');
$flatbase = new Flatbase\Flatbase($storage);

$book = new Ledger\Book($flatbase);

$stdio = new Clue\React\Stdio\Stdio();
$stdio->setPrompt('Book> ');


$stdio->setAutocomplete(function() use($flatbase, $stdio, $book){

    $line = trim($stdio->getInput());

    $stdio->moveCursorBy(0);

    return [];
});

// load history
$all = explode("\n", Strukt\Fs::cat(".history"));
foreach($all as $history)
    $stdio->addHistory($history);

$stdio->on('data', function ($line) use ($flatbase, $stdio, $book){

    $line = rtrim($line);

    $all = $stdio->listHistory();

    // skip empty line and duplicate of previous line
    if ($line !== '' && $line !== end($all)) {

        $stdio->addHistory($line);
        $all[] = $line;
        Fs::overwrite(".history", implode("\n", $all));
    } 

    Cli::cmd("exit", function() use($stdio){

        $stdio->end();
    });

    Cli::cmd("help", function(){

        $help = [];
        $help = array_merge($help, explode("\n", Cli::run("sch ?")));
        $help = array_merge($help, explode("\n", Cli::run("trx ?")));
        $help[] = Cli::run("bal ?");
        $help[] = "trx:type ls";

        $helps = [];
        foreach($help as $line)
            $helps[] = sprintf("  %s", $line);

        return sprintf("\n%s\n\n", implode("\n", $helps));
    });

    Cli::cmd("trx:type ls", function() use($flatbase){

        $rs = $flatbase->read()->in("trx_type")->get()->getArrayCopy();

        foreach($rs as $row)
            $rows[] = $row["name"];

        return implode("\n", $rows);
    });

    Cli::cmd("sch help", function(){

        return Cli::run("sch ?");
    });

    Cli::cmd("sch ?", function(){

        $help = array(

            "sch <trx_type> <tenant_no> <amount>",
            "sch last [<offset>]"
        );
        
        return implode("\n", $help);
    });

    /**
     * sch last [<offset>]
     */
    Cli::cmd("sch last", function(int $offset = null) use($flatbase){

        $rs = $flatbase->read()->in("trx_queue")->get()->getArrayCopy();
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
    Cli::cmd("sch", function(string $trx_type, string $token, $amt) use($book, $stdio){

        $trxType = $book->withTrxType($trx_type);

        if($trxType->exists()){

            if($trxType->isType("schedule")){

                $trx_no = $book->makeSchedule($trx_type, $token, $amt);

                $stdio->setInput(sprintf("trx:descr %s ", $trx_no));

                return 'success:true|on:trx-schedule';
            }
            
            return "success:false|on:trx-schedule|error:expected[type:schedule]";
        }

        return 'success:false|on:trx-schedule';
    });

    Cli::cmd("trx help", function(){

        return Cli::run("trx ?");
    });

    Cli::cmd("trx ?", function(){

        $help = array(

            "trx <trx_type> <trx_no> [<amount>]",
            "trx last [<offset>]"
        );

        return implode("\n", $help);
    });

    /**
     * trx:descr <trx_no> <descr*>
     */
    Cli::cmd("trx:descr", function(string $trx_no, ...$descr) use($stdio, $flatbase){

        $r = $flatbase->read()->in("trx_queue")->where("trx_no","==", $trx_no)->first();

        if(array_key_exists("descr", $r))
            if(!empty($r["descr"]))
                return "success:false|on:schedule|error:[descr:exists]";

        $r["descr"] = implode(" ", $descr);

        $flatbase->update()->in("trx_queue")->set($r)->where("trx_no", "==", $trx_no)->execute();

        return "success:true|on:schedule|update:descr";
    });

    /**
     * trx last [<offset>]
     */
    Cli::cmd("trx last", function(int $offset = null) use($flatbase){

        $rs = $flatbase->read()->in("trx")->get()->getArrayCopy();
        $rs = array_reverse($rs);

        if(is_null($offset) || $offset < 1)
            $offset = 1;

        array_splice($rs, $offset);

        return Json::pp($rs);
    });

    /**
     * trx <trx_type> <trx_no> <amt>
     * 
     * trx_type:string Transaction Type - example Rent:Paid
     * trx_no:string   Transaction Number
     * amt:number      Transaction Amount
     */
    Cli::cmd("trx", function(string $trx_type, string $trx_no, $amt = null) use($book){

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

    Cli::cmd("bal help", function(){

        return Cli::run("bal ?");
    });

    Cli::cmd("bal ?", function(){

        return "bal <trx_no>";
    });

    /**
     * bal <trx_no>
     * 
     * trx_no:string Transaction number
     */
    Cli::cmd("bal", function(string $trx_no) use($book){

        $bal = $book->getBal($trx_no);

        return sprintf("balance:%s", $bal);
    });

    try{
        
        $stdio->write(Cli::run($line));        
    }
    catch(\Exception $e){

        $stdio->write($e->getMessage());
    }
});