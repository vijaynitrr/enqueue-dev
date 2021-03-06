<?php
namespace Enqueue\Bundle\Tests\Unit;

use Enqueue\AmqpExt\Symfony\AmqpTransportFactory;
use Enqueue\AmqpExt\Symfony\RabbitMqTransportFactory;
use Enqueue\Bundle\DependencyInjection\Compiler\BuildClientRoutingPass;
use Enqueue\Bundle\DependencyInjection\Compiler\BuildExtensionsPass;
use Enqueue\Bundle\DependencyInjection\Compiler\BuildMessageProcessorRegistryPass;
use Enqueue\Bundle\DependencyInjection\Compiler\BuildQueueMetaRegistryPass;
use Enqueue\Bundle\DependencyInjection\Compiler\BuildTopicMetaSubscribersPass;
use Enqueue\Bundle\DependencyInjection\EnqueueExtension;
use Enqueue\Bundle\EnqueueBundle;
use Enqueue\Stomp\Symfony\RabbitMqStompTransportFactory;
use Enqueue\Stomp\Symfony\StompTransportFactory;
use Enqueue\Symfony\DefaultTransportFactory;
use Enqueue\Symfony\NullTransportFactory;
use Enqueue\Test\ClassExtensionTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class EnqueueBundleTest extends \PHPUnit_Framework_TestCase
{
    use ClassExtensionTrait;

    public function testShouldExtendBundleClass()
    {
        $this->assertClassExtends(Bundle::class, EnqueueBundle::class);
    }

    public function testCouldBeConstructedWithoutAnyArguments()
    {
        new EnqueueBundle();
    }

    public function testShouldRegisterExpectedCompilerPasses()
    {
        $extensionMock = $this->createMock(EnqueueExtension::class);

        $container = $this->createMock(ContainerBuilder::class);
        $container
            ->expects($this->at(0))
            ->method('addCompilerPass')
            ->with($this->isInstanceOf(BuildExtensionsPass::class))
        ;
        $container
            ->expects($this->at(1))
            ->method('addCompilerPass')
            ->with($this->isInstanceOf(BuildClientRoutingPass::class))
        ;
        $container
            ->expects($this->at(2))
            ->method('addCompilerPass')
            ->with($this->isInstanceOf(BuildMessageProcessorRegistryPass::class))
        ;
        $container
            ->expects($this->at(3))
            ->method('addCompilerPass')
            ->with($this->isInstanceOf(BuildTopicMetaSubscribersPass::class))
        ;
        $container
            ->expects($this->at(4))
            ->method('addCompilerPass')
            ->with($this->isInstanceOf(BuildQueueMetaRegistryPass::class))
        ;
        $container
            ->expects($this->at(5))
            ->method('getExtension')
            ->willReturn($extensionMock)
        ;

        $bundle = new EnqueueBundle();
        $bundle->build($container);
    }

    public function testShouldRegisterDefaultAndNullTransportFactories()
    {
        $extensionMock = $this->createEnqueueExtensionMock();

        $container = new ContainerBuilder();
        $container->registerExtension($extensionMock);

        $extensionMock
            ->expects($this->at(0))
            ->method('addTransportFactory')
            ->with($this->isInstanceOf(DefaultTransportFactory::class))
        ;
        $extensionMock
            ->expects($this->at(1))
            ->method('addTransportFactory')
            ->with($this->isInstanceOf(NullTransportFactory::class))
        ;

        $bundle = new EnqueueBundle();
        $bundle->build($container);
    }

    public function testShouldRegisterStompAndRabbitMqStompTransportFactories()
    {
        $extensionMock = $this->createEnqueueExtensionMock();

        $container = new ContainerBuilder();
        $container->registerExtension($extensionMock);

        $extensionMock
            ->expects($this->at(2))
            ->method('addTransportFactory')
            ->with($this->isInstanceOf(StompTransportFactory::class))
        ;
        $extensionMock
            ->expects($this->at(3))
            ->method('addTransportFactory')
            ->with($this->isInstanceOf(RabbitMqStompTransportFactory::class))
        ;

        $bundle = new EnqueueBundle();
        $bundle->build($container);
    }

    public function testShouldRegisterAmqpAndRabbitMqAmqpTransportFactories()
    {
        $extensionMock = $this->createEnqueueExtensionMock();

        $container = new ContainerBuilder();
        $container->registerExtension($extensionMock);

        $extensionMock
            ->expects($this->at(4))
            ->method('addTransportFactory')
            ->with($this->isInstanceOf(AmqpTransportFactory::class))
        ;
        $extensionMock
            ->expects($this->at(5))
            ->method('addTransportFactory')
            ->with($this->isInstanceOf(RabbitMqTransportFactory::class))
        ;

        $bundle = new EnqueueBundle();
        $bundle->build($container);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|EnqueueExtension
     */
    private function createEnqueueExtensionMock()
    {
        $extensionMock = $this->createMock(EnqueueExtension::class);
        $extensionMock
            ->expects($this->once())
            ->method('getAlias')
            ->willReturn('enqueue')
        ;
        $extensionMock
            ->expects($this->once())
            ->method('getNamespace')
            ->willReturn(false)
        ;

        return $extensionMock;
    }
}
