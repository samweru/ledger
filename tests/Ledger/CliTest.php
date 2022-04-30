<?php

use Ledger\Cli;

namespace Ledger;

class CliTest extends \PHPUnit\Framework\TestCase{

	public function setUp():void{

		/**
		 * trx last [<offset>]
		 */
		Cli::cmd("trx last", function($offset = null){

			return sprintf(sprintf("Last:Trx[%s]", $offset));
		});

		/**
		 * trx <trx_type> <tenant_no> <amount>
		 */
		Cli::cmd("trx", function($trx_type, $tenant_no, $amount){

			return sprintf("trx_type:%s|tenant_no:%s|amount:%s", $trx_type, $tenant_no, $amount);
		});
	}

	public function testTrxLast(){

		$this->assertEquals(Cli::run("trx last"), "Last:Trx[]");
		$this->assertEquals(Cli::run("trx last 1"), "Last:Trx[1]");
	}

	public function testTrx(){

		$expected = "trx_type:Rent:Due|tenant_no:001|amount:1500";

		$this->assertEquals(Cli::run("trx Rent:Due 001 1500"), $expected);	
	}
}