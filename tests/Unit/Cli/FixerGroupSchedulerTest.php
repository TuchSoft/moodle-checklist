<?php

namespace Tuchsoft\MoodleChecklist\Tests\Unit\Cli;

use PHPUnit\Framework\TestCase;
use Tuchsoft\MoodleChecklist\Check\FixableCheckInterface;
use Tuchsoft\MoodleChecklist\Cli\FixerGroupScheduler;

class FixerGroupSchedulerTest extends TestCase
{
    public function testEmptyInputReturnsEmptyWaves(): void
    {
        $scheduler = new FixerGroupScheduler();
        $this->assertSame([], $scheduler->schedule([]));
    }

    public function testSingleGroupReturnsSingleWave(): void
    {
        $scheduler = new FixerGroupScheduler();
        $checks = [
            $this->createCheck('php', []),
            $this->createCheck('php', []),
        ];

        $waves = $scheduler->schedule($checks);

        $this->assertCount(1, $waves);
        $this->assertArrayHasKey('php', $waves[0]);
        $this->assertCount(2, $waves[0]['php']);
    }

    public function testBootstrapRunsBeforeMetadata(): void
    {
        $scheduler = new FixerGroupScheduler();
        $checks = [
            $this->createCheck('metadata', ['bootstrap']),
            $this->createCheck('bootstrap', []),
        ];

        $waves = $scheduler->schedule($checks);

        $this->assertCount(2, $waves);
        $this->assertArrayHasKey('bootstrap', $waves[0]);
        $this->assertArrayHasKey('metadata', $waves[1]);
    }

    public function testIndependentGroupsRunInSameWave(): void
    {
        $scheduler = new FixerGroupScheduler();
        $checks = [
            $this->createCheck('php', ['metadata']),
            $this->createCheck('js', ['metadata']),
            $this->createCheck('css', ['metadata']),
            $this->createCheck('metadata', ['bootstrap']),
            $this->createCheck('bootstrap', []),
        ];

        $waves = $scheduler->schedule($checks);

        $this->assertCount(3, $waves);
        $this->assertArrayHasKey('bootstrap', $waves[0]);
        $this->assertArrayHasKey('metadata', $waves[1]);
        $this->assertCount(3, $waves[2]);
        $this->assertArrayHasKey('php', $waves[2]);
        $this->assertArrayHasKey('js', $waves[2]);
        $this->assertArrayHasKey('css', $waves[2]);
    }

    public function testCircularDependencyThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);

        $scheduler = new FixerGroupScheduler();
        $checks = [
            $this->createCheck('a', ['b']),
            $this->createCheck('b', ['a']),
        ];

        $scheduler->schedule($checks);
    }

    private function createCheck(string $group, array $dependencies): FixableCheckInterface
    {
        return new class($group, $dependencies) implements FixableCheckInterface {
            public function __construct(private string $group, private array $dependencies)
            {
            }

            public static function getName(): string
            {
                return 'mock';
            }

            public function canFix(): bool
            {
                return true;
            }

            public function fix(bool $apply): bool
            {
                return true;
            }

            public function getFixerGroup(): string
            {
                return $this->group;
            }

            public function getFixerDependencies(): array
            {
                return $this->dependencies;
            }
        };
    }
}
