<?php

namespace app\components;

use Enqueue\AmqpLib\AmqpConnectionFactory;
use Interop\Amqp\AmqpQueue as InteropAmqpQueue;
use Interop\Queue\Context;
use yii\base\Component;

class AmqpQueue extends Component
{
    /** @var Context */
    private $context;
    /** @var InteropAmqpQueue */
    private $queue;

    public $host = 'rabbitmq';
    public $port = 5672;
    public $user = 'user';
    public $pass = 'password';
    public $vhost = '/';
    public $queueName = 'balance';
    public $eventExchange = 'balance_events_exchange';
    public $eventQueueName = 'balance_events';

    public function init()
    {
        parent::init();
        $factory = new AmqpConnectionFactory([
            'host' => $this->host,
            'port' => $this->port,
            'user' => $this->user,
            'pass' => $this->pass,
            'vhost' => $this->vhost,
        ]);
        $this->context = $factory->createContext();
        $this->queue = $this->context->createQueue($this->queueName);
        $this->context->declareQueue($this->queue);
    }

    public function send($body)
    {
        $message = $this->context->createMessage($body);
        $this->context->createProducer()->send($this->queue, $message);
    }

    public function receive($timeout = 1000)
    {
        $consumer = $this->context->createConsumer($this->queue);
        $message = $consumer->receive($timeout);
        if ($message) {
            $consumer->acknowledge($message);
            return $message->getBody();
        }
        return null;
    }

    public function sendEvent($body)
    {
        $eventQueue = $this->context->createQueue($this->eventQueueName);
        $eventExchange = $this->context->createTopic($this->eventExchange);
        $eventExchange->setType('fanout');
        $this->context->declareTopic($eventExchange);
        $this->context->declareQueue($eventQueue);
        $message = $this->context->createMessage($body);
        $this->context->createProducer()->send($eventExchange, $message);
    }
}
