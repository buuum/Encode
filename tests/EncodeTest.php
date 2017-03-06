<?php

declare(strict_types=1);

class EncodeTest extends \PHPUnit\Framework\TestCase
{


    public static $data = [
        'key1'   => 'value 1',
        'key2'   => 'value 2',
        'newkey' => 'Key ??? %$·"!!"·$%&'
    ];

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
    }

    public static function setUpBeforeClass()
    {

    }

    public function testEncode()
    {
        \Buuum\Encoding\Encode::$key = 'My Secret Key';
        $code = \Buuum\Encoding\Encode::encode(self::$data);
        $this->assertEquals(\Buuum\Encoding\Encode::decode($code), self::$data);
    }

    /**
     * @dataProvider getAlgorithms
     */
    public function testEncodeByType($alg)
    {
        \Buuum\Encoding\Encode::$key = 'My Secret Key';
        \Buuum\Encoding\Encode::setAlgorithm($alg);
        $code = \Buuum\Encoding\Encode::encode(self::$data);
        $this->assertEquals(\Buuum\Encoding\Encode::decode($code), self::$data);
    }

    /**
     * @dataProvider getAlgorithms
     * @expectedException Exception
     */
    public function testEncodeDelayKo($alg)
    {
        \Buuum\Encoding\Encode::$key = 'My Secret Key';
        \Buuum\Encoding\Encode::setAlgorithm($alg);
        $code = \Buuum\Encoding\Encode::encode(self::$data, ['delay' => 2]);
        \Buuum\Encoding\Encode::decode($code);
    }

    /**
     * @dataProvider getAlgorithms
     * @expectedException Exception
     */
    public function testEncodeExpiresKo($alg)
    {
        \Buuum\Encoding\Encode::$key = 'Another !! Secret Key';
        \Buuum\Encoding\Encode::setAlgorithm($alg);
        $code = \Buuum\Encoding\Encode::encode(self::$data, ['expires' => 1]);
        sleep(2);
        \Buuum\Encoding\Encode::decode($code);
    }

    /**
     * @dataProvider getAlgorithms
     */
    public function testEncodeDelayOk($alg)
    {
        \Buuum\Encoding\Encode::$key = 'My new Secret Key';
        \Buuum\Encoding\Encode::setAlgorithm($alg);
        $code = \Buuum\Encoding\Encode::encode(self::$data, ['delay' => 1]);
        sleep(2);
        $this->assertEquals(\Buuum\Encoding\Encode::decode($code), self::$data);
    }

    /**
     * @dataProvider getAlgorithms
     */
    public function testExpiresOK($alg)
    {
        \Buuum\Encoding\Encode::$key = 'My ////new Secret Key ??? %$·"!!"·$%&';
        \Buuum\Encoding\Encode::setAlgorithm($alg);
        $code = \Buuum\Encoding\Encode::encode(self::$data, ['expires' => 10]);
        $this->assertEquals(\Buuum\Encoding\Encode::decode($code), self::$data);
    }

    public function getAlgorithms()
    {
        return [
            ['RIJNDAELE'],
            ['RIJNDAELC'],
            ['BLOWFISH'],
            ['3DES'],
            ['GOST']
        ];
    }

    ///**
    // * @expectedException \Buuum\Exception\HttpRouteNotFoundException
    // */
    //public function testExceptionHttpRouteNotFound()
    //{
    //    $dispatcher = new \Buuum\Dispatcher(self::$router->getData());
    //    $dispatcher->dispatchRequest('POST', '/items/11');
    //}

}