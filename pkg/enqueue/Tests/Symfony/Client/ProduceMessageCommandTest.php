<?php
namespace Enqueue\Tests\Symfony\Client;

use Enqueue\Client\MessageProducerInterface;
use Enqueue\Symfony\Client\ProduceMessageCommand;
use Symfony\Component\Console\Tester\CommandTester;

class ProduceMessageCommandTest extends \PHPUnit_Framework_TestCase
{
    public function testCouldBeConstructedWithMessageProducerAsFirstArgument()
    {
        new ProduceMessageCommand($this->createProducerMock());
    }

    public function testShouldHaveCommandName()
    {
        $command = new ProduceMessageCommand($this->createProducerMock());

        $this->assertEquals('enqueue:produce', $command->getName());
    }

    public function testShouldHaveCommandAliases()
    {
        $command = new ProduceMessageCommand($this->createProducerMock());

        $this->assertEquals(['enq:p'], $command->getAliases());
    }

    public function testShouldHaveExpectedOptions()
    {
        $command = new ProduceMessageCommand($this->createProducerMock());

        $options = $command->getDefinition()->getOptions();
        $this->assertCount(0, $options);
    }

    public function testShouldHaveExpectedAttributes()
    {
        $command = new ProduceMessageCommand($this->createProducerMock());

        $arguments = $command->getDefinition()->getArguments();
        $this->assertCount(2, $arguments);

        $this->assertArrayHasKey('topic', $arguments);
        $this->assertArrayHasKey('message', $arguments);
    }

    public function testShouldExecuteConsumptionAndUseDefaultQueueName()
    {
        $producerMock = $this->createProducerMock();
        $producerMock
            ->expects($this->once())
            ->method('send')
            ->with('theTopic', 'theMessage')
        ;

        $command = new ProduceMessageCommand($producerMock);

        $tester = new CommandTester($command);
        $tester->execute([
            'topic' => 'theTopic',
            'message' => 'theMessage',
        ]);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|MessageProducerInterface
     */
    private function createProducerMock()
    {
        return $this->createMock(MessageProducerInterface::class);
    }
}
