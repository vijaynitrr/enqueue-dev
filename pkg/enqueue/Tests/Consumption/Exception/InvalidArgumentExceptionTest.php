<?php
namespace Enqueue\Tests\Consumption;

use Enqueue\Consumption\Exception\ExceptionInterface;
use Enqueue\Consumption\Exception\InvalidArgumentException;
use Enqueue\Test\ClassExtensionTrait;

class InvalidArgumentExceptionTest extends \PHPUnit_Framework_TestCase
{
    use ClassExtensionTrait;

    public function testShouldImplementExceptionInterface()
    {
        $this->assertClassImplements(ExceptionInterface::class, InvalidArgumentException::class);
    }

    public function testShouldExtendLogicException()
    {
        $this->assertClassExtends(\LogicException::class, InvalidArgumentException::class);
    }

    public function testCouldBeConstructedWithoutAnyArguments()
    {
        new InvalidArgumentException();
    }

    public function testThrowIfAssertInstanceOfNotSameAsExpected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'The argument must be an instance of SplQueue but got SplStack.'
        );

        InvalidArgumentException::assertInstanceOf(new \SplStack(), \SplQueue::class);
    }

    public function testShouldDoNothingIfAssertDestinationInstanceOfSameAsExpected()
    {
        InvalidArgumentException::assertInstanceOf(new \SplQueue(), \SplQueue::class);
    }
}
