<?php

namespace Ledger;

use Flatbase\Storage\Filesystem as Fs;
use Flatbase\Flatbase as Db;

class Connection{

	public static function getDb(){

		$storage = new Fs('./flatbase');
		$db = new Db($storage);

		return $db;
	}
}