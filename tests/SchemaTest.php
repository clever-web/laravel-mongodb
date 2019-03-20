<?php

class SchemaTest extends TestCase
{
    public function tearDown(): void
    {
        Schema::drop('newcollection');
        Schema::drop('newcollection_two');
    }

    public function testCreate()
    {
        Schema::create('newcollection');
        $this->assertTrue(Schema::hasCollection('newcollection'));
        $this->assertTrue(Schema::hasTable('newcollection'));
    }

    public function testCreateWithCallback()
    {
        $instance = $this;

        Schema::create('newcollection', function ($collection) use ($instance) {
            $instance->assertInstanceOf('Jenssegers\Mongodb\Schema\Blueprint', $collection);
        });

        $this->assertTrue(Schema::hasCollection('newcollection'));
    }

    public function testCreateWithOptions()
    {
        Schema::create('newcollection_two', null, ['capped' => true, 'size' => 1024]);
        $this->assertTrue(Schema::hasCollection('newcollection_two'));
        $this->assertTrue(Schema::hasTable('newcollection_two'));
    }

    public function testDrop()
    {
        Schema::create('newcollection');
        Schema::drop('newcollection');
        $this->assertFalse(Schema::hasCollection('newcollection'));
    }

    public function testBluePrint()
    {
        $instance = $this;

        Schema::collection('newcollection', function ($collection) use ($instance) {
            $instance->assertInstanceOf('Jenssegers\Mongodb\Schema\Blueprint', $collection);
        });

        Schema::table('newcollection', function ($collection) use ($instance) {
            $instance->assertInstanceOf('Jenssegers\Mongodb\Schema\Blueprint', $collection);
        });
    }

    public function testIndex()
    {
        Schema::collection('newcollection', function ($collection) {
            $collection->index('mykey1');
        });

        $index = $this->getIndex('newcollection', 'mykey1');
        $this->assertEquals(1, $index['key']['mykey1']);

        Schema::collection('newcollection', function ($collection) {
            $collection->index(['mykey2']);
        });

        $index = $this->getIndex('newcollection', 'mykey2');
        $this->assertEquals(1, $index['key']['mykey2']);

        Schema::collection('newcollection', function ($collection) {
            $collection->string('mykey3')->index();
        });

        $index = $this->getIndex('newcollection', 'mykey3');
        $this->assertEquals(1, $index['key']['mykey3']);
    }

    public function testPrimary()
    {
        Schema::collection('newcollection', function ($collection) {
            $collection->string('mykey', 100)->primary();
        });

        $index = $this->getIndex('newcollection', 'mykey');
        $this->assertEquals(1, $index['unique']);
    }

    public function testUnique()
    {
        Schema::collection('newcollection', function ($collection) {
            $collection->unique('uniquekey');
        });

        $index = $this->getIndex('newcollection', 'uniquekey');
        $this->assertEquals(1, $index['unique']);
    }

    public function testDropIndex()
    {
        Schema::collection('newcollection', function ($collection) {
            $collection->unique('uniquekey');
            $collection->dropIndex('uniquekey_1');
        });

        $index = $this->getIndex('newcollection', 'uniquekey');
        $this->assertEquals(null, $index);

        Schema::collection('newcollection', function ($collection) {
            $collection->unique('uniquekey');
            $collection->dropIndex(['uniquekey']);
        });

        $index = $this->getIndex('newcollection', 'uniquekey');
        $this->assertEquals(null, $index);

        Schema::collection('newcollection', function ($collection) {
            $collection->index(['field_a', 'field_b']);
        });

        $index = $this->getIndex('newcollection', 'field_a_1_field_b_1');
        $this->assertNotNull($index);

        Schema::collection('newcollection', function ($collection) {
            $collection->dropIndex(['field_a', 'field_b']);
        });

        $index = $this->getIndex('newcollection', 'field_a_1_field_b_1');
        $this->assertFalse($index);

        Schema::collection('newcollection', function ($collection) {
            $collection->index(['field_a', 'field_b'], 'custom_index_name');
        });

        $index = $this->getIndex('newcollection', 'custom_index_name');
        $this->assertNotNull($index);

        Schema::collection('newcollection', function ($collection) {
            $collection->dropIndex('custom_index_name');
        });

        $index = $this->getIndex('newcollection', 'custom_index_name');
        $this->assertFalse($index);
    }

    public function testBackground()
    {
        Schema::collection('newcollection', function ($collection) {
            $collection->background('backgroundkey');
        });

        $index = $this->getIndex('newcollection', 'backgroundkey');
        $this->assertEquals(1, $index['background']);
    }

    public function testSparse()
    {
        Schema::collection('newcollection', function ($collection) {
            $collection->sparse('sparsekey');
        });

        $index = $this->getIndex('newcollection', 'sparsekey');
        $this->assertEquals(1, $index['sparse']);
    }

    public function testExpire()
    {
        Schema::collection('newcollection', function ($collection) {
            $collection->expire('expirekey', 60);
        });

        $index = $this->getIndex('newcollection', 'expirekey');
        $this->assertEquals(60, $index['expireAfterSeconds']);
    }

    public function testSoftDeletes()
    {
        Schema::collection('newcollection', function ($collection) {
            $collection->softDeletes();
        });

        Schema::collection('newcollection', function ($collection) {
            $collection->string('email')->nullable()->index();
        });

        $index = $this->getIndex('newcollection', 'email');
        $this->assertEquals(1, $index['key']['email']);
    }

    public function testFluent()
    {
        Schema::collection('newcollection', function ($collection) {
            $collection->string('email')->index();
            $collection->string('token')->index();
            $collection->timestamp('created_at');
        });

        $index = $this->getIndex('newcollection', 'email');
        $this->assertEquals(1, $index['key']['email']);

        $index = $this->getIndex('newcollection', 'token');
        $this->assertEquals(1, $index['key']['token']);
    }

    public function testGeospatial()
    {
        Schema::collection('newcollection', function ($collection) {
            $collection->geospatial('point');
            $collection->geospatial('area', '2d');
            $collection->geospatial('continent', '2dsphere');
        });

        $index = $this->getIndex('newcollection', 'point');
        $this->assertEquals('2d', $index['key']['point']);

        $index = $this->getIndex('newcollection', 'area');
        $this->assertEquals('2d', $index['key']['area']);

        $index = $this->getIndex('newcollection', 'continent');
        $this->assertEquals('2dsphere', $index['key']['continent']);
    }

    public function testDummies()
    {
        Schema::collection('newcollection', function ($collection) {
            $collection->boolean('activated')->default(0);
            $collection->integer('user_id')->unsigned();
        });
    }

    public function testSparseUnique()
    {
        Schema::collection('newcollection', function ($collection) {
            $collection->sparse_and_unique('sparseuniquekey');
        });

        $index = $this->getIndex('newcollection', 'sparseuniquekey');
        $this->assertEquals(1, $index['sparse']);
        $this->assertEquals(1, $index['unique']);
    }

    protected function getIndex($collection, $name)
    {
        $collection = DB::getCollection($collection);

        foreach ($collection->listIndexes() as $index) {
            if (isset($index['key'][$name])) {
                return $index;
            }
        }

        return false;
    }
}
