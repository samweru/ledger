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

    Cli::cmd("sch ?", function() use($stdio){

        $stdio->write("sch <trx_type> <tenant_no> <amount>".PHP_EOL);
        $stdio->write("sch last [<offset>]".PHP_EOL);
    });

    /**
     * sch last [<offset>]
     */
    Cli::cmd("sch last", function(int $offset = null) use($flatbase, $stdio){

        $rs = $flatbase->read()->in("trx_queue")->get()->getArrayCopy();
        $rs = array_reverse($rs);

        if(is_null($offset) || $offset < 1)
            $offset = 1;

        array_splice($rs, $offset);

        $stdio->write(Json::pp($rs) . PHP_EOL);
    });

    /**
     *  sch <trx_type> <tenant_no> <amt>
     */
    Cli::cmd("sch", function(string $trx_type, $tenant_no, $amt) use($book, $stdio, $rows){

        if(array_key_exists($trx_type, $rows["trx"])){

            if($rows["trx"][$trx_type] == "schedule"){

                $token = sprintf("type:tenant|id:%s", $tenant_no);

                $book->makeSchedule($trx_type, $amt, $token);

                $stdio->write('Schedule successfully completed.' . PHP_EOL);
            }
            else $stdio->write("Transaction must be Type:Schedule!");
        }
        else $stdio->write('Failed to execute schedule!' . PHP_EOL); 
    });

    Cli::cmd("trx ?", function() use($stdio){

        $stdio->write("trx <trx_type> <trx_no> [<amount>]".PHP_EOL);
        $stdio->write("trx last [<offset>]".PHP_EOL);
    });

    /**
     * trx last [<offset>]
     */
    Cli::cmd("trx last", function(int $offset = null) use($flatbase, $stdio){

        $rs = $flatbase->read()->in("trx")->get()->getArrayCopy();
        $rs = array_reverse($rs);

        if(is_null($offset) || $offset < 1)
            $offset = 1;

        array_splice($rs, $offset);

        $stdio->write(Json::pp($rs) . PHP_EOL);
    });

    /**
     * trx <trx_type> <trx_no> <amt>
     */
    Cli::cmd("trx", function($trx_type, $trx_no, $amt = null) use($book, $stdio, $rows){

        if(array_key_exists($trx_type, $rows["trx"])){

            if($rows["trx"][$trx_type] == "payment"){

                try{

                    $book->makeTrx($trx_type, $trx_no, $amt);

                    $stdio->write('Transaction successfully completed.' . PHP_EOL);
                }
                catch(\Exception $e){

                    $stdio->write($e->getMessage());
                }
            }
            else $stdio->write("Transaction must be Type:Payment!");
        }
        else $stdio->write('Failed to execute transaction!' . PHP_EOL);
    });

    Cli::cmd("bal ?", function() use($stdio){

        $stdio->write("bal <trx_no>".PHP_EOL);
    });

    /**
     * bal <trx_no>
     */
    Cli::cmd("bal", function(string $trx_no) use($book, $stdio){

        $bal = $book->getBal($trx_no);

        $stdio->write(sprintf("Balance: %s", $bal));
    });

    Cli::run($line);        
});
