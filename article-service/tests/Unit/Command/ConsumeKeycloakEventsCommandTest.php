<?php

namespace App\Tests\Unit\Command;

use App\Command\ConsumeKeycloakEventsCommand;
use App\MessageHandler\KeycloakEventHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

class ConsumeKeycloakEventsCommandTest extends TestCase
{
    private function createCommand(
        ?KeycloakEventHandler $eventHandler = null,
        ?LoggerInterface $logger = null
    ): ConsumeKeycloakEventsCommand {
        return new ConsumeKeycloakEventsCommand(
            $eventHandler ?? $this->createStub(KeycloakEventHandler::class),
            $logger ?? $this->createStub(LoggerInterface::class),
            'localhost:9092',
            'keycloak-events'
        );
    }

    public function testCommandHasCorrectName(): void
    {
        $command = $this->createCommand();

        $this->assertEquals('app:consume-keycloak-events', $command->getName());
    }

    public function testCommandHasCorrectDescription(): void
    {
        $command = $this->createCommand();

        $this->assertEquals('Consume Keycloak events from Kafka', $command->getDescription());
    }

    public function testCommandHasTimeoutOption(): void
    {
        $command = $this->createCommand();
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('timeout'));

        $option = $definition->getOption('timeout');
        $this->assertEquals('t', $option->getShortcut());
        $this->assertEquals(1000, $option->getDefault());
    }

    public function testProcessMessageWithValidJson(): void
    {
        $payload = json_encode([
            'type' => 'REGISTER',
            'userId' => 'user-123'
        ]);

        $eventHandler = $this->createMock(KeycloakEventHandler::class);
        $eventHandler
            ->expects($this->once())
            ->method('handle')
            ->with([
                'type' => 'REGISTER',
                'userId' => 'user-123'
            ]);

        $command = $this->createCommand($eventHandler);
        $this->invokeProcessMessage($command, $payload);
    }

    public function testProcessMessageWithInvalidJsonLogsWarning(): void
    {
        $payload = 'invalid json {{{';

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('warning')
            ->with('Invalid JSON in Kafka message', ['payload' => $payload]);

        $eventHandler = $this->createMock(KeycloakEventHandler::class);
        $eventHandler
            ->expects($this->never())
            ->method('handle');

        $command = $this->createCommand($eventHandler, $logger);
        $this->invokeProcessMessage($command, $payload);
    }

    public function testProcessMessageWithMissingTypeDisplaysUnknown(): void
    {
        $payload = json_encode([
            'userId' => 'user-123'
        ]);

        $eventHandler = $this->createMock(KeycloakEventHandler::class);
        $eventHandler
            ->expects($this->once())
            ->method('handle');

        $command = $this->createCommand($eventHandler);
        $output = $this->invokeProcessMessage($command, $payload);

        $this->assertStringContainsString('Event: unknown', $output);
    }

    public function testProcessMessageWithMissingUserIdDisplaysUnknown(): void
    {
        $payload = json_encode([
            'type' => 'REGISTER'
        ]);

        $eventHandler = $this->createMock(KeycloakEventHandler::class);
        $eventHandler
            ->expects($this->once())
            ->method('handle');

        $command = $this->createCommand($eventHandler);
        $output = $this->invokeProcessMessage($command, $payload);

        $this->assertStringContainsString('User: unknown', $output);
    }

    public function testProcessMessageLogsErrorWhenHandlerThrows(): void
    {
        $payload = json_encode([
            'type' => 'REGISTER',
            'userId' => 'user-123'
        ]);

        $exception = new \Exception('Handler failed');

        $eventHandler = $this->createMock(KeycloakEventHandler::class);
        $eventHandler
            ->expects($this->once())
            ->method('handle')
            ->willThrowException($exception);

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('error')
            ->with('Error processing event', $this->callback(function ($context) use ($exception) {
                return isset($context['event']) && $context['exception'] === $exception;
            }));

        $command = $this->createCommand($eventHandler, $logger);
        $output = $this->invokeProcessMessage($command, $payload);

        $this->assertStringContainsString('Error processing event: Handler failed', $output);
    }

    public function testProcessMessageDisplaysEventInfo(): void
    {
        $payload = json_encode([
            'type' => 'UPDATE_PROFILE',
            'userId' => 'user-456'
        ]);

        $command = $this->createCommand();
        $output = $this->invokeProcessMessage($command, $payload);

        $this->assertStringContainsString('Event: UPDATE_PROFILE', $output);
        $this->assertStringContainsString('User: user-456', $output);
    }

    #[DataProvider('eventTypesProvider')]
    public function testProcessMessageHandlesDifferentEventTypes(string $eventType): void
    {
        $payload = json_encode([
            'type' => $eventType,
            'userId' => 'user-789'
        ]);

        $eventHandler = $this->createMock(KeycloakEventHandler::class);
        $eventHandler
            ->expects($this->once())
            ->method('handle')
            ->with($this->callback(function ($event) use ($eventType) {
                return $event['type'] === $eventType;
            }));

        $command = $this->createCommand($eventHandler);
        $this->invokeProcessMessage($command, $payload);
    }

    public static function eventTypesProvider(): array
    {
        return [
            'register event' => ['REGISTER'],
            'update profile event' => ['UPDATE_PROFILE'],
            'delete account event' => ['DELETE_ACCOUNT'],
            'unknown event' => ['UNKNOWN_EVENT'],
        ];
    }

    private function invokeProcessMessage(ConsumeKeycloakEventsCommand $command, string $payload): string
    {
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('processMessage');

        $input = new ArrayInput([]);
        $output = new BufferedOutput();
        $io = new SymfonyStyle($input, $output);

        $method->invoke($command, $payload, $io);

        return $output->fetch();
    }
}
