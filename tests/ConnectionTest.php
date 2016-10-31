<?php

class ConnectionTest extends TestCase
{
    public function testConnection()
    {
        $connection = DB::connection('mongodb');
        $this->assertInstanceOf('Jenssegers\Mongodb\Connection', $connection);
    }

    public function testReconnect()
    {
        $c1 = DB::connection('mongodb');
        $c2 = DB::connection('mongodb');
        $this->assertEquals(spl_object_hash($c1), spl_object_hash($c2));

        $c1 = DB::connection('mongodb');
        DB::purge('mongodb');
        $c2 = DB::connection('mongodb');
        $this->assertNotEquals(spl_object_hash($c1), spl_object_hash($c2));
    }

    public function testDb()
    {
        $connection = DB::connection('mongodb');
        $this->assertInstanceOf('MongoDB\Database', $connection->getMongoDB());

        $connection = DB::connection('mongodb');
        $this->assertInstanceOf('MongoDB\Client', $connection->getMongoClient());
    }

    public function testCollection()
    {
        $collection = DB::connection('mongodb')->getCollection('unittest');
        $this->assertInstanceOf('Jenssegers\Mongodb\Collection', $collection);

        $collection = DB::connection('mongodb')->collection('unittests');
        $this->assertInstanceOf('Jenssegers\Mongodb\Query\Builder', $collection);

        $collection = DB::connection('mongodb')->table('unittests');
        $this->assertInstanceOf('Jenssegers\Mongodb\Query\Builder', $collection);
    }

    // public function testDynamic()
    // {
    //     $dbs = DB::connection('mongodb')->listCollections();
    //     $this->assertTrue(is_array($dbs));
    // }

    // public function testMultipleConnections()
    // {
    //     global $app;

    //     # Add fake host
    //     $db = $app['config']['database.connections']['mongodb'];
    //     $db['host'] = array($db['host'], '1.2.3.4');

    //     $connection = new Connection($db);
    //     $mongoclient = $connection->getMongoClient();

    //     $hosts = $mongoclient->getHosts();
    //     $this->assertEquals(1, count($hosts));
    // }

    public function testQueryLog()
    {
        DB::enableQueryLog();

        $this->assertEquals(0, count(DB::getQueryLog()));

        DB::collection('items')->get();
        $this->assertEquals(1, count(DB::getQueryLog()));

        DB::collection('items')->insert(['name' => 'test']);
        $this->assertEquals(2, count(DB::getQueryLog()));

        DB::collection('items')->count();
        $this->assertEquals(3, count(DB::getQueryLog()));

        DB::collection('items')->where('name', 'test')->update(['name' => 'test']);
        $this->assertEquals(4, count(DB::getQueryLog()));

        DB::collection('items')->where('name', 'test')->delete();
        $this->assertEquals(5, count(DB::getQueryLog()));
    }

    public function testSchemaBuilder()
    {
        $schema = DB::connection('mongodb')->getSchemaBuilder();
        $this->assertInstanceOf('Jenssegers\Mongodb\Schema\Builder', $schema);
    }

    public function testDriverName()
    {
        $driver = DB::connection('mongodb')->getDriverName();
        $this->assertEquals('mongodb', $driver);
    }

    public function testAuth()
    {
        Config::set('database.connections.mongodb.username', 'foo');
        Config::set('database.connections.mongodb.password', 'bar');
        Config::set('database.connections.mongodb.options.database', 'custom');

        $connection = DB::connection('mongodb');
        $this->assertEquals('mongodb://127.0.0.1/custom', (string) $connection->getMongoClient());
    }

    public function testCustomHostAndPort()
    {
        Config::set('database.connections.mongodb.host', 'db1');
        Config::set('database.connections.mongodb.port', 27000);

        $connection = DB::connection('mongodb');
        $this->assertEquals("mongodb://db1:27000", (string) $connection->getMongoClient());
    }

    public function testHostWithPorts()
    {
        Config::set('database.connections.mongodb.port', 27000);
        Config::set('database.connections.mongodb.host', ['db1:27001', 'db2:27002', 'db3:27000']);

        $connection = DB::connection('mongodb');
        $this->assertEquals('mongodb://db1:27001,db2:27002,db3:27000', (string) $connection->getMongoClient());
    }
}
