<?php

namespace Ledger;

Use Strukt\Event;
Use Strukt\Raise;
use Strukt\Ref;
use Strukt\Type\Str;

class Cli{

	public static $cmds;

	public static function cmd($name, callable $func){

		static::$cmds[$name] = $func;
	}

	public static function splitLn(string $line){

		return preg_split('/\s+/', $line);
	}

	public static function getCmd($cmd_name){

		if(array_key_exists($cmd_name, static::$cmds))
			return Event::create(static::$cmds[$cmd_name]);		

		return null;
	}

	public static function getDoc($cmd_name){

		$rfunc = Ref::func(static::$cmds[$cmd_name])->getRef();

        $doc = $rfunc->getDocComment();

        if(!empty($doc))
            $doc = Str::create(strval($doc))->replace(["/**","* ", "*/"], "");

        return sprintf(" \n%s", trim($doc));
	}

	public static function run(string $line){
		
		$parts = static::splitLn($line);
		$part1 = array_shift($parts);
		$part2 = array_shift($parts);
		$cmd_name = sprintf("%s %s", $part1, $part2);
		
		$cmd = static::getCmd($cmd_name);

		if(is_null($cmd)){

			$cmd_name = $part1;
			$cmd = static::getCmd($cmd_name);
			array_unshift($parts, $part2);
		}

		if(is_null($cmd))
			new Raise("Command not found!");

		if(!empty($parts))
			$cmd = $cmd->applyArgs($parts);

		$nargs = Ref::func(static::$cmds[$cmd_name])
					->getRef()
					->getNumberOfRequiredParameters();

		$doc = null;
		if(count($parts) < $nargs)
			$doc = static::getDoc($cmd_name);

		if(is_null($doc))
			$cmd->exec();
		else
			echo $doc;
	}
}
