<?php

declare(strict_types=1);

namespace Medora\Authority\Tests\Unit;

use Medora\Authority\Core\Container;
use Medora\Authority\Support\Chunker;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    public function testSingletonReturnsTheSameInstance(): void
    {
        $this->container->singleton('service', static fn (): object => new \stdClass());

        $this->assertSame($this->container->get('service'), $this->container->get('service'));
    }

    public function testBindReturnsANewInstanceEachTime(): void
    {
        $this->container->bind('service', static fn (): object => new \stdClass());

        $this->assertNotSame($this->container->get('service'), $this->container->get('service'));
    }

    public function testInstanceStoresAScalar(): void
    {
        $this->container->instance('answer', 42);

        $this->assertSame(42, $this->container->get('answer'));
    }

    public function testAliasResolvesToTheConcreteBinding(): void
    {
        $this->container->singleton(Chunker::class, static fn (): Chunker => new Chunker());
        $this->container->alias('chunker', Chunker::class);

        $this->assertInstanceOf(Chunker::class, $this->container->get('chunker'));
    }

    public function testHasReflectsRegistrationAndAutoloadability(): void
    {
        $this->container->instance('answer', 42);

        $this->assertTrue($this->container->has('answer'));
        $this->assertTrue($this->container->has(Chunker::class));
        $this->assertFalse($this->container->has('\\No\\Such\\Class'));
    }

    public function testAutowiresAConstructorlessClass(): void
    {
        $this->assertInstanceOf(Chunker::class, $this->container->get(Chunker::class));
    }

    public function testAutowiredClassesAreMemoised(): void
    {
        $this->assertSame(
            $this->container->get(Chunker::class),
            $this->container->get(Chunker::class)
        );
    }

    public function testUnknownServiceThrows(): void
    {
        $this->expectException(RuntimeException::class);

        $this->container->get('definitely_not_a_service');
    }

    public function testRebindingClearsTheMemoisedInstance(): void
    {
        $this->container->singleton('service', static fn (): object => (object) ['v' => 1]);
        $first = $this->container->get('service');

        $this->container->singleton('service', static fn (): object => (object) ['v' => 2]);

        $this->assertNotSame($first, $this->container->get('service'));
        $this->assertSame(2, $this->container->get('service')->v);
    }
}
