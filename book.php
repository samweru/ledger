<?php

require "bootstrap.php";

use Strukt\Type\Str;
use Strukt\Type\Json;
use Strukt\Type\Number;

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

    // skip empty line and duplicate of previous line
    if ($line !== '' && $line !== end($all)) {

        $stdio->addHistory($line);
    }

    if ($line === "exit"){

        $stdio->end();
    }

    if(in_array($line, ["sch help", "sch ?"])){

        $stdio->write("sch <trx_type> <tenant_no> <amount>".PHP_EOL);
        $stdio->write("sch last [<offset>]".PHP_EOL);
    }
    elseif(Str::create($line)->startsWith("sch")){

        $rows = $book->getMeta();

        $args = explode(" ", $line);

        $trx_type = $args[1];

        if(Str::create($line)->startsWith("sch last")){

            $rs = $flatbase->read()->in("trx_queue")->get()->getArrayCopy();
            $rs = array_reverse($rs);

            $offset = (int) @$args[2];
            if(is_null($offset) || $offset < 1)
                $offset = 1;

            array_splice($rs, $offset);

            $stdio->write(Json::pp($rs) . PHP_EOL);
        }
        elseif(array_key_exists($trx_type, $rows["trx"])){

            $tno = $args[2];
            $amt = $args[3];

            if($rows["trx"][$trx_type] == "schedule"){

                $token = sprintf("type:tenant|id:%s", $tno);

                $book->makeSchedule($trx_type, $amt, $token);

                $stdio->write('Schedule successfully completed.' . PHP_EOL);
            }
            else $stdio->write("Transaction must be Type:Schedule!");
        }
        else $stdio->write('Failed to execute schedule!' . PHP_EOL);        
    }

    if(in_array($line, ["trx help", "trx ?"])){

        $stdio->write("trx <trx_type> <trx_no> [<amount>]".PHP_EOL);
        $stdio->write("trx last [<offset>]".PHP_EOL);
    }
    elseif(Str::create($line)->startsWith("trx")){

        $rows = $book->getMeta();

        $args = explode(" ", $line);

        $trx_type = $args[1];

        if(Str::create($line)->startsWith("trx last")){

            $rs = $flatbase->read()->in("trx")->get()->getArrayCopy();
            $rs = array_reverse($rs);

            $offset = (int) @$args[2];
            if(is_null($offset) || $offset < 1)
                $offset = 1;

            array_splice($rs, $offset);

            $stdio->write(Json::pp($rs) . PHP_EOL);
        }
        elseif(array_key_exists($trx_type, $rows["trx"])){

            $trx_no = $args[2];
            $amt = @$args[3];

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
        
    }

    if(in_array($line, ["bal help", "bal ?"])){

        $stdio->write("bal <trx_no>".PHP_EOL);
    }
    elseif(Str::create($line)->startsWith("bal")){

        $args = explode(" ", $line);

        $trx_type = $args[1];

        $bal = $book->getBal($trx_type);

        $stdio->write(sprintf("Balance: %s", $bal));
    }
});
