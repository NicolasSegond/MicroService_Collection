<?php

namespace App\Command;

use App\Kafka\KafkaConsumer;
use App\MessageHandler\KeycloakEventHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:consume-keycloak-events',
    description: 'Consume Keycloak events from Kafka'
)]
class ConsumeKeycloakEventsCommand extends Command
{
    private bool $shouldStop = false;

    public function __construct(
        private KeycloakEventHandler $eventHandler,
        private LoggerInterface $logger,
        private string $kafkaBrokers,
        private string $kafkaTopic
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('timeout', 't', InputOption::VALUE_OPTIONAL, 'Consumer timeout in ms', 1000);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $timeout = (int) $input->getOption('timeout');

        $io->title('Keycloak Events Consumer');
        $io->info("Connecting to Kafka: {$this->kafkaBrokers}");
        $io->info("Topic: {$this->kafkaTopic}");

        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, fn() => $this->shouldStop = true);
            pcntl_signal(SIGINT, fn() => $this->shouldStop = true);
        }

        try {
            $consumer = new KafkaConsumer($this->kafkaBrokers, 'article-service-consumer');
            $consumer->subscribe([$this->kafkaTopic]);

            $io->success('Consumer started. Waiting for messages...');

            while (!$this->shouldStop) {
                if (function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }

                $message = $consumer->consume($timeout);

                if ($message === null) {
                    continue;
                }

                $this->processMessage($message->payload, $io);
            }

            $consumer->close();
            $io->info('Consumer stopped gracefully');

        } catch (\Exception $e) {
            $io->error("Kafka error: {$e->getMessage()}");
            $this->logger->error('Kafka consumer error', ['exception' => $e]);
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function processMessage(string $payload, SymfonyStyle $io): void
    {
        $event = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->warning('Invalid JSON in Kafka message', ['payload' => $payload]);
            return;
        }

        $io->writeln(sprintf(
            '[%s] Event: %s | User: %s',
            date('H:i:s'),
            $event['type'] ?? 'unknown',
            $event['userId'] ?? 'unknown'
        ));

        try {
            $this->eventHandler->handle($event);
        } catch (\Exception $e) {
            $this->logger->error('Error processing event', [
                'event' => $event,
                'exception' => $e
            ]);
            $io->warning("Error processing event: {$e->getMessage()}");
        }
    }
}
