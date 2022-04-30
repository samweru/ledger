<?php

require "bootstrap.php";

use Strukt\Type\Str;
use Strukt\Type\Json;
use Strukt\Type\Number;
use Ledger\Cli;

$storage = new Flatbase\Storage\Filesystem('./flatbase');
$flatbase = new Flatbase\Flatbase($storage);

$book = new Ledger\Book($flatbase);

$stdio = new Clue\React\Stdio\Stdio();
$stdio->setPrompt('Input > ');

$stdio->setAutocomplete(function() use($flatbase, $stdio, $book){

    $line = trim($stdio->getInput());

    $stdio->moveCursorBy(0);

    return [];
});

$stdio->on('data', function ($line) use ($flatbase, $stdio, $book){

    $line = rtrim($line);

    $all = $stdio->listHistory();

    // skip empty line and duplicate of previous line
    if ($line !== '' && $line !== end($all)) {

        $stdio->addHistory($line);
    }

    Cli::cmd("exit", function() use($stdio){

        $stdio->end();
    });

    Cli::cmd("help", function(){

        $help = array(

            Cli::run("sch ?"),
            Cli::run("trx ?"),
            Cli::run("bal ?")
        );

        return implode("\n", $help);
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
     *  sch <trx_type> <tenant_no> <amt>
     */
    Cli::cmd("sch", function(string $trx_type, $tenant_no, $amt) use($book){

        $trxType = $book->withTrxType($trx_type);

        if($trxType->exists()){

            if($trxType->isType("schedule")){

                $token = sprintf("type:tenant|id:%s", $tenant_no);

                $book->makeSchedule($trx_type, $amt, $token);

                return 'Schedule successfully completed.';
            }
            
            return "Transaction must be Type:Schedule!";
        }

        return 'Failed to execute schedule!';
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
     */
    Cli::cmd("trx", function($trx_type, $trx_no, $amt = null) use($book){

        $trxType = $book->withTrxType($trx_type);

        if($trxType->exists()){

            if($trxType->isType("payment")){

                try{

                    $book->makeTrx($trx_type, $trx_no, $amt);

                    return 'Transaction successfully completed.';
                }
                catch(\Exception $e){

                    return $e->getMessage();
                }
            }
            
            return "Transaction must be Type:Payment!";
        }
        
        return 'Failed to execute transaction!';
    });

    Cli::cmd("bal help", function(){

        return Cli::run("bal ?");
    });

    Cli::cmd("bal ?", function(){

        return "bal <trx_no>";
    });

    /**
     * bal <trx_no>
     */
    Cli::cmd("bal", function(string $trx_no) use($book){

        $bal = $book->getBal($trx_no);

        return sprintf("Balance: %s", $bal);
    });

    try{
        
        $stdio->write(Cli::run($line));        
    }
    catch(\Exception $e){

        $stdio->write($e->getMessage());
    }
});