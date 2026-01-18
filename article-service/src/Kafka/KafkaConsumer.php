<?php

namespace App\Kafka;

use RdKafka\Conf;
use RdKafka\KafkaConsumer as RdKafkaConsumer;
use RdKafka\Message;

class KafkaConsumer
{
    private RdKafkaConsumer $consumer;

    public function __construct(string $brokers, string $groupId)
    {
        $conf = new Conf();
        $conf->set('metadata.broker.list', $brokers);
        $conf->set('group.id', $groupId);
        $conf->set('auto.offset.reset', 'earliest');
        $conf->set('enable.auto.commit', 'true');
        $conf->set('allow.auto.create.topics', 'true');

        $this->consumer = new RdKafkaConsumer($conf);
    }

    public function subscribe(array $topics): void
    {
        $this->consumer->subscribe($topics);
    }

    public function consume(int $timeoutMs = 1000): ?Message
    {
        $message = $this->consumer->consume($timeoutMs);

        if ($message->err === RD_KAFKA_RESP_ERR_NO_ERROR) {
            return $message;
        }

        if ($message->err === RD_KAFKA_RESP_ERR__PARTITION_EOF ||
            $message->err === RD_KAFKA_RESP_ERR__TIMED_OUT) {
            return null;
        }

        if ($message->err === RD_KAFKA_RESP_ERR_UNKNOWN_TOPIC_OR_PART) {
            return null;
        }

        throw new \RuntimeException($message->errstr(), $message->err);
    }

    public function close(): void
    {
        $this->consumer->close();
    }
}
