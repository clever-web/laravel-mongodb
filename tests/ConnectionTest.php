<?php
use Illuminate\Support\Facades\DB;
use Jenssegers\Mongodb\Connection;

class ConnectionTest extends PHPUnit_Framework_TestCase {

	public function setUp() {}

	public function tearDown() {}

	public function testConnection()
	{
		$connection = DB::connection('mongodb');
		$this->assertInstanceOf('Jenssegers\Mongodb\Connection', $connection);

		$c1 = DB::connection('mongodb');
		$c2 = DB::connection('mongodb');
		$this->assertEquals($c1, $c2);

		$c1 = DB::connection('mongodb');
		$c2 = DB::reconnect('mongodb');
		$this->assertNotEquals($c1, $c2);
	}

	public function testDb()
	{
		$connection = DB::connection('mongodb');
		$this->assertInstanceOf('MongoDB', $connection->getMongoDB());
	}

	public function testCollection()
	{
		$collection = DB::connection('mongodb')->getCollection('unittest');
		$this->assertInstanceOf('MongoCollection', $collection);

		$collection = DB::connection('mongodb')->collection('unittests');
		$this->assertInstanceOf('Jenssegers\Mongodb\Query\Builder', $collection);

		$collection = DB::connection('mongodb')->table('unittests');
		$this->assertInstanceOf('Jenssegers\Mongodb\Query\Builder', $collection);
	}

	public function testDynamic()
	{
		$dbs = DB::connection('mongodb')->listCollections();
		$this->assertTrue(is_array($dbs));
	}

	/*public function testMultipleConnections()
	{
		global $app;

		# Add fake host
		$db = $app['config']['database.connections']['mongodb'];
		$db['host'] = array($db['host'], '1.2.3.4');

		$connection = new Connection($db);
		$mongoclient = $connection->getMongoClient();

		$hosts = $mongoclient->getHosts();
		$this->assertEquals(1, count($hosts));
	}*/

	public function testQueryLog()
	{
		$this->assertEquals(0, count(DB::getQueryLog()));

		DB::collection('items')->get();
		$this->assertEquals(1, count(DB::getQueryLog()));

		DB::collection('items')->insert(array('name' => 'test'));
		$this->assertEquals(2, count(DB::getQueryLog()));

		DB::collection('items')->count();
		$this->assertEquals(3, count(DB::getQueryLog()));

		DB::collection('items')->where('name', 'test')->update(array('name' => 'test'));
		$this->assertEquals(4, count(DB::getQueryLog()));

		DB::collection('items')->where('name', 'test')->delete();
		$this->assertEquals(5, count(DB::getQueryLog()));
	}

	public function testSchemaBuilder()
	{
		$schema = DB::connection('mongodb')->getSchemaBuilder();
		$this->assertInstanceOf('Jenssegers\Mongodb\Schema\Builder', $schema);
	}

}
