<?php

use jc21\CliTable;

require "bootstrap.php";

/**
 * Say hello to someone
 */
task("hello", function(string $name){

    writeln(sprintf("Hello %s!", $name));
});

task('db:clean', function(){
    
    run("rm -rf flatbase/*");
});

task('db:seed', function($which){
    
    run(sprintf("php seeder.php %s", $which), function($output) use($which){

        if(empty($output))
            $output = sprintf("[%s] was seeded.", $which);

        echo sprintf("%s\n", $output);
    });
});

task('db:ls', function(){
    
    list($output, $error) = run("ls flatbase", function($output){

        echo $output;
    });
});

task("db:show", function($table) use($flatbase){

    $rs = $flatbase->read()->in($table)->get()->getArrayCopy();

    $table = new CliTable;
    $table->setTableColor('blue');
    $table->setHeaderColor('cyan');

    foreach(array_keys($rs[0]) as $field)
        $table->addField($field, $field);

    $table->injectData($rs);
    $table->display();
});
