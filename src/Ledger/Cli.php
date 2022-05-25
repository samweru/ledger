<?php

namespace Ledger;

// Use Strukt\Event;
Use Strukt\Raise;
use Strukt\Ref;
use Strukt\Type\Str;
use Strukt\Cmd;

class Cli{

	public static function splitLn(string $line){

		return preg_split('/\s+/', $line);
	}

	// public static function getDoc($cmd_name){

	// 	$rfunc = Ref::func(static::$cmds[$cmd_name])->getRef();

 //        $doc = $rfunc->getDocComment();

 //        if(!empty($doc))
 //            $doc = Str::create(strval($doc))->replace(["/**","* ", "*/"], "");

 //        $lines = [];
 //        foreach(explode("\n", $doc) as $line)
 //        	$lines[] = sprintf("  %s", trim($line));

 //        return sprintf("%s\n", implode("\n", $lines));
	// }

	public static function run(string $line){
		
		$parts = static::splitLn($line);
		$part1 = array_shift($parts);
		$part2 = array_shift($parts);

		$cmd_name = sprintf("%s %s", $part1, $part2);
		
		$cmd = @Cmd::get($cmd_name);

		if(is_null($cmd)){

			$cmd_name = $part1;
			$cmd = @Cmd::get($cmd_name);

			if(!empty($part2))
				array_unshift($parts, $part2);
		}

		if(is_null($cmd))
			new Raise("success:false|error:[command:unavailable]");

		$rFunc = Ref::func($cmd)->getRef();

		if($rFunc->isVariadic() && !empty($parts)){

			$temp = $parts;
			$parts = [];

			$params = $rFunc->getParameters();
			foreach($params as $idx=>$param)
				if(!$param->isVariadic())
					$parts[] = array_shift($temp);

			if(!empty($temp))
				$parts = array_merge($parts, $temp);
		}

		if(!empty($parts))
			return Cmd::exec($cmd_name, $parts);

		// $nargs = $rFunc->getNumberOfRequiredParameters();
		// if(count($parts) < $nargs)
			// return static::getDoc($cmd_name);

		return Cmd::exec($cmd_name);
	}
}
