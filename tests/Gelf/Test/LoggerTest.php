<?php

/*
 * This file is part of the php-gelf package.
 *
 * (c) Benjamin Zikarsky <http://benjamin-zikarsky.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gelf\Test;

use Gelf\Logger;
use Gelf\MessageInterface;
use Gelf\PublisherInterface;
use PHPUnit_Framework_TestCase as TestCase;
use Psr\Log\LogLevel;
use Exception;
use Closure;

class LoggerTest extends TestCase
{

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|PublisherInterface
     */
    protected $publisher;

    /**
     * @var Logger
     */
    protected $logger;
    protected $facility = "test-facility";

    public function setUp()
    {
        $this->publisher = $this->getMock('\Gelf\Publisher');
        $this->logger = new Logger($this->publisher, $this->facility);
    }

    public function testPublisher()
    {
        $this->assertEquals($this->publisher, $this->logger->getPublisher());

        $newPublisher = $this->getMock('\Gelf\Publisher');
        $this->logger->setPublisher($newPublisher);
        $this->assertEquals($newPublisher, $this->logger->getPublisher());
    }

    public function testFacility()
    {
        $this->assertEquals($this->facility, $this->logger->getFacility());

        $newFacility = "foobar-facil";
        $this->logger->setFacility($newFacility);
        $this->assertEquals($newFacility, $this->logger->getFacility());
        
        $newFacility = null;
        $this->logger->setFacility($newFacility);
        $this->assertEquals($newFacility, $this->logger->getFacility());
    }

    public function testSimpleLog()
    {
        $test = $this;
        $facility = $this->facility;
        $this->validatePublish(
            function (MessageInterface $message) use ($test, $facility) {
                $test->assertEquals("test", $message->getShortMessage());
                $test->assertEquals(LogLevel::ALERT, $message->getLevel());
                $test->assertEquals($facility, $message->getFacility());
            }
        );

        $this->logger->log(LogLevel::ALERT, "test");
    }

    public function testLogContext()
    {
        $test = $this;
        $additionals = array('test' => 'bar', 'abc' => 'buz');
        $this->validatePublish(
            function (MessageInterface $message) use ($test, $additionals) {
                $test->assertEquals("foo bar", $message->getShortMessage());
                $test->assertEquals($additionals, $message->getAllAdditionals());
            }
        );

        $this->logger->log(LogLevel::NOTICE, "foo {test}", $additionals);
    }

    public function testLogException()
    {
        $test = $this;
        $line = __LINE__ + 2; // careful, offset is the line-distance to the throw statement
        try {
            throw new Exception("test-message", 123);
        } catch (Exception $e) {
            $this->validatePublish(
                function (MessageInterface $message) use ($e, $line, $test) {
                    $test->assertTrue(strstr($message->getFullMessage(), $e->getMessage()) !== false);
                    $test->assertTrue(strstr($message->getFullMessage(), get_class($e)) !== false);
                    $test->assertEquals($line, $message->getLine());
                    $test->assertEquals(__FILE__, $message->getFile());
                }
            );

            $this->logger->log(LogLevel::ALERT, $e->getMessage(), array('exception' => $e));
        }
    }

    // @see https://github.com/bzikarsky/gelf-php/issues/9
    public function testStringZeroMessage()
    {
        $test = $this;
        $this->validatePublish(
             function (MessageInterface $message) use ($test) {
                 $test->assertEquals("0", $message->getShortMessage());
             }
        );

        $this->logger->info('0');
    }

    // @see https://github.com/bzikarsky/gelf-php/issues/9
    public function testNumericZeroMessage()
    {
        $test = $this;
        $this->validatePublish(
             function (MessageInterface $message) use ($test) {
                 $test->assertEquals(0, $message->getShortMessage());
             }
        );

        $this->logger->alert(0);
    }

    private function validatePublish(Closure $validator)
    {
        $this->publisher->expects($this->once())->method('publish')->will(
            $this->returnCallback($validator)
        );
    }
}
