<?php

namespace Ledger\Cli;

class Buffer{

	private static $buffer = [];

	/**
	* Add element to start of whichever buffer[key]
	*/
	public static function attach(string $key, string $val){

		if(!array_key_exists($key, static::$buffer))
			static::$buffer[$key] = [];

		array_unshift(static::$buffer[$key], $val);
	}

	public static function add(string $key, string $val){

		static::$buffer[$key][] = $val;
	}

	/**
	* Empty buffer[key] and return contents
	*/
	public static function purge(string $key){

		$contents = [];

		if(array_key_exists($key, static::$buffer)){

			$contents = static::$buffer[$key];
			unset(static::$buffer[$key]);
		}	

		return $contents;
	}
}