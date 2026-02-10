<?php

namespace App\Tests\Integration\Command;

use App\Command\ConsumeKeycloakEventsCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\LazyCommand;

class ConsumeKeycloakEventsCommandTest extends KernelTestCase
{
    private Application $application;

    public function testCommandIsRegisteredInContainer(): void
    {
        $this->assertTrue($this->application->has('app:consume-keycloak-events'));
    }

    public function testCommandCanBeRetrievedFromContainer(): void
    {
        $command = $this->application->find('app:consume-keycloak-events');

        // Symfony wrap les commandes dans LazyCommand pour le lazy-loading
        if ($command instanceof LazyCommand) {
            $command = $command->getCommand();
        }

        $this->assertInstanceOf(ConsumeKeycloakEventsCommand::class, $command);
    }

    public function testCommandHasCorrectConfiguration(): void
    {
        $command = $this->application->find('app:consume-keycloak-events');
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('timeout'));
        $this->assertEquals(1000, $definition->getOption('timeout')->getDefault());
    }

    public function testCommandHelperTextIsAvailable(): void
    {
        $command = $this->application->find('app:consume-keycloak-events');

        $this->assertEquals('Consume Keycloak events from Kafka', $command->getDescription());
    }

    public function testCommandDependenciesAreInjected(): void
    {
        $command = $this->application->find('app:consume-keycloak-events');

        if ($command instanceof LazyCommand) {
            $command = $command->getCommand();
        }

        $reflection = new \ReflectionClass($command);

        // PHP 8.1+ : setAccessible() n'est plus nécessaire
        $eventHandlerProp = $reflection->getProperty('eventHandler');
        $this->assertNotNull($eventHandlerProp->getValue($command));

        $loggerProp = $reflection->getProperty('logger');
        $this->assertNotNull($loggerProp->getValue($command));

        $brokersProp = $reflection->getProperty('kafkaBrokers');
        $this->assertNotEmpty($brokersProp->getValue($command));

        $topicProp = $reflection->getProperty('kafkaTopic');
        $this->assertNotEmpty($topicProp->getValue($command));
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $this->application = new Application(self::$kernel);
    }
}
