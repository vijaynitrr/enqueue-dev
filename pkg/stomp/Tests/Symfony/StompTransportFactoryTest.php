<?php
namespace Enqueue\Stomp\Tests\Symfony;

use Enqueue\Symfony\TransportFactoryInterface;
use Enqueue\Test\ClassExtensionTrait;
use Enqueue\Stomp\Client\StompDriver;
use Enqueue\Stomp\StompConnectionFactory;
use Enqueue\Stomp\Symfony\StompTransportFactory;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class StompTransportFactoryTest extends \PHPUnit_Framework_TestCase
{
    use ClassExtensionTrait;

    public function testShouldImplementTransportFactoryInterface()
    {
        $this->assertClassImplements(TransportFactoryInterface::class, StompTransportFactory::class);
    }

    public function testCouldBeConstructedWithDefaultName()
    {
        $transport = new StompTransportFactory();

        $this->assertEquals('stomp', $transport->getName());
    }

    public function testCouldBeConstructedWithCustomName()
    {
        $transport = new StompTransportFactory('theCustomName');

        $this->assertEquals('theCustomName', $transport->getName());
    }

    public function testShouldAllowAddConfiguration()
    {
        $transport = new StompTransportFactory();
        $tb = new TreeBuilder();
        $rootNode = $tb->root('foo');

        $transport->addConfiguration($rootNode);
        $processor = new Processor();
        $config = $processor->process($tb->buildTree(), []);

        $this->assertEquals([
            'host' => 'localhost',
            'port' => 61613,
            'login' => 'guest',
            'password' => 'guest',
            'vhost' => '/',
            'sync' => true,
            'connection_timeout' => 1,
            'buffer_size' => 1000,
        ], $config);
    }

    public function testShouldCreateService()
    {
        $container = new ContainerBuilder();

        $transport = new StompTransportFactory();

        $serviceId = $transport->createContext($container, [
            'uri' => 'tcp://localhost:61613',
            'login' => 'guest',
            'password' => 'guest',
            'vhost' => '/',
            'sync' => true,
            'connection_timeout' => 1,
            'buffer_size' => 1000,
        ]);

        $this->assertEquals('enqueue.transport.stomp.context', $serviceId);
        $this->assertTrue($container->hasDefinition($serviceId));

        $context = $container->getDefinition('enqueue.transport.stomp.context');
        $this->assertInstanceOf(Reference::class, $context->getFactory()[0]);
        $this->assertEquals('enqueue.transport.stomp.connection_factory', (string) $context->getFactory()[0]);
        $this->assertEquals('createContext', $context->getFactory()[1]);

        $this->assertTrue($container->hasDefinition('enqueue.transport.stomp.connection_factory'));
        $factory = $container->getDefinition('enqueue.transport.stomp.connection_factory');
        $this->assertEquals(StompConnectionFactory::class, $factory->getClass());
        $this->assertSame([[
            'uri' => 'tcp://localhost:61613',
            'login' => 'guest',
            'password' => 'guest',
            'vhost' => '/',
            'sync' => true,
            'connection_timeout' => 1,
            'buffer_size' => 1000,
        ]], $factory->getArguments());
    }

    public function testShouldCreateDriver()
    {
        $container = new ContainerBuilder();

        $transport = new StompTransportFactory();

        $serviceId = $transport->createDriver($container, []);

        $this->assertEquals('enqueue.client.stomp.driver', $serviceId);
        $this->assertTrue($container->hasDefinition($serviceId));

        $driver = $container->getDefinition($serviceId);
        $this->assertSame(StompDriver::class, $driver->getClass());
    }
}
