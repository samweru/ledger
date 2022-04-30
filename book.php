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

    $rows = $book->getMeta();

    $line = trim($stdio->getInput());

    $arg1 = Str::create($line);

    if($arg1->startsWith("sch") && !$arg1->startsWith("sch last"))
        $line = "trx";

    if(!empty(@$rows[$line])){

        $ls = array_merge([""], array_keys($rows[$line]));
        echo(sprintf("\n%s\n", implode("\n", $ls)));
    }

    $stdio->moveCursorBy(0);

    return [];
});

$stdio->on('data', function ($line) use ($flatbase, $stdio, $book){

    $line = rtrim($line);

    $all = $stdio->listHistory();

    $rows = $book->getMeta();

    // skip empty line and duplicate of previous line
    if ($line !== '' && $line !== end($all)) {

        $stdio->addHistory($line);
    }

    Cli::cmd("exit", function() use($stdio){

        $stdio->end();
    });

    Cli::cmd("sch ?", function(){

        $help = array(

            "sch <trx_type> <tenant_no> <amount>",
            "sch last [<offset>]"
        );
        
        return sprintf("\n%s", implode("\n", $help));
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
    Cli::cmd("sch", function(string $trx_type, $tenant_no, $amt) use($book, $rows){

        if(array_key_exists($trx_type, $rows["trx"])){

            if($rows["trx"][$trx_type] == "schedule"){

                $token = sprintf("type:tenant|id:%s", $tenant_no);

                $book->makeSchedule($trx_type, $amt, $token);

                return 'Schedule successfully completed.';
            }
            
            return "Transaction must be Type:Schedule!";
        }

        return 'Failed to execute schedule!';
    });

    Cli::cmd("trx ?", function(){

        $help = array(

            "trx <trx_type> <trx_no> [<amount>]",
            "trx last [<offset>]"
        );

        return sprintf("%s", implode("\n", $help));
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
    Cli::cmd("trx", function($trx_type, $trx_no, $amt = null) use($book, $rows){

        if(array_key_exists($trx_type, $rows["trx"])){

            if($rows["trx"][$trx_type] == "payment"){

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

    $stdio->write(Cli::run($line));        
});
