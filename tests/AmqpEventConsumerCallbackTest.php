<?php
/*
 * This file is part of the prooph/humus-amqp-producer.
 * (c) 2016 prooph software GmbH <contact@prooph.de>
 * (c) 2016 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare (strict_types=1);

namespace ProophTest\ServiceBus\Message\HumusAmqp;

use Humus\Amqp\DeliveryResult;
use Humus\Amqp\Envelope;
use Humus\Amqp\Queue;
use PHPUnit_Framework_TestCase as TestCase;
use Prooph\Common\Messaging\Message;
use Prooph\Common\Messaging\MessageFactory;
use Prooph\EventStore\Exception\ConcurrencyException;
use Prooph\ServiceBus\EventBus;
use Prooph\ServiceBus\Exception\MessageDispatchException;
use Prooph\ServiceBus\Message\HumusAmqp\AmqpEventConsumerCallback;

/**
 * Class AmqpEventConsumerCallbackTest
 * @package ProophTest\ServiceBus\Message\HumusAmqp
 */
class  AmqpEventConsumerCallbackTest extends TestCase
{
    /**
     * @test
     */
    public function it_acks_message_when_all_good()
    {
        $time = (string) microtime(true);
        if (false === strpos($time, '.')) {
            $time .= '.0000';
        }
        $now = \DateTimeImmutable::createFromFormat('U.u', $time);

        $envelope = $this->prophesize(Envelope::class);
        $envelope
            ->getBody()
            ->willReturn('{"message_name":"test-event","uuid":"ccefedef-85e1-4fd0-b247-ed13d378b050","version":1,"payload":[],"metadata":[],"created_at":"' . $now->format('Y-m-d\TH:i:s.u') . '"}')
            ->shouldBeCalled();
        $envelope->getType()->willReturn('test-event')->shouldBeCalled();

        $queue = $this->prophesize(Queue::class);

        $event = $this->prophesize(Message::class);

        $messageFactory = $this->prophesize(MessageFactory::class);
        $messageFactory
            ->createMessageFromArray(
                'test-event',
                [
                    'message_name' => 'test-event',
                    'uuid' => 'ccefedef-85e1-4fd0-b247-ed13d378b050',
                    'version' => 1,
                    'payload' => [],
                    'metadata' => [],
                    'created_at' => $now,
                ]
            )
            ->willReturn($event->reveal())
            ->shouldBeCalled();

        $eventBus = $this->prophesize(EventBus::class);
        $eventBus->dispatch($event)->shouldBeCalled();

        $amqpEventConsumerCallback = new AmqpEventConsumerCallback($eventBus->reveal(), $messageFactory->reveal());
        $deliveryResult = $amqpEventConsumerCallback($envelope->reveal(), $queue->reveal());
        $this->assertEquals(DeliveryResult::MSG_ACK(), $deliveryResult);
    }

    /**
     * @test
     */
    public function it_rejects_message_when_created_at_missing()
    {
        $envelope = $this->prophesize(Envelope::class);
        $envelope
            ->getBody()
            ->willReturn('{"message_name":"test-event","uuid":"ccefedef-85e1-4fd0-b247-ed13d378b050","version":1,"payload":[],"metadata":[]}')
            ->shouldBeCalled();

        $queue = $this->prophesize(Queue::class);

        $messageFactory = $this->prophesize(MessageFactory::class);

        $eventBus = $this->prophesize(EventBus::class);

        $amqpEventConsumerCallback = new AmqpEventConsumerCallback($eventBus->reveal(), $messageFactory->reveal());
        $deliveryResult = $amqpEventConsumerCallback($envelope->reveal(), $queue->reveal());
        $this->assertEquals(DeliveryResult::MSG_REJECT(), $deliveryResult);
    }

    /**
     * @test
     */
    public function it_rejects_message_when_invalid_created_at_given()
    {
        $envelope = $this->prophesize(Envelope::class);
        $envelope
            ->getBody()
            ->willReturn('{"message_name":"test-event","uuid":"ccefedef-85e1-4fd0-b247-ed13d378b050","version":1,"payload":[],"metadata":[],"created_at":"invalid"}')
            ->shouldBeCalled();

        $queue = $this->prophesize(Queue::class);

        $messageFactory = $this->prophesize(MessageFactory::class);

        $eventBus = $this->prophesize(EventBus::class);

        $amqpEventConsumerCallback = new AmqpEventConsumerCallback($eventBus->reveal(), $messageFactory->reveal());
        $deliveryResult = $amqpEventConsumerCallback($envelope->reveal(), $queue->reveal());
        $this->assertEquals(DeliveryResult::MSG_REJECT(), $deliveryResult);
    }

    /**
     * @test
     */
    public function it_rejects_message_when_exception_occurred()
    {
        $time = (string) microtime(true);
        if (false === strpos($time, '.')) {
            $time .= '.0000';
        }
        $now = \DateTimeImmutable::createFromFormat('U.u', $time);

        $envelope = $this->prophesize(Envelope::class);
        $envelope
            ->getBody()
            ->willReturn('{"message_name":"test-event","uuid":"ccefedef-85e1-4fd0-b247-ed13d378b050","version":1,"payload":[],"metadata":[],"created_at":"' . $now->format('Y-m-d\TH:i:s.u') . '"}')
            ->shouldBeCalled();
        $envelope->getType()->willReturn('test-event')->shouldBeCalled();

        $queue = $this->prophesize(Queue::class);

        $event = $this->prophesize(Message::class);

        $messageFactory = $this->prophesize(MessageFactory::class);
        $messageFactory
            ->createMessageFromArray(
                'test-event',
                [
                    'message_name' => 'test-event',
                    'uuid' => 'ccefedef-85e1-4fd0-b247-ed13d378b050',
                    'version' => 1,
                    'payload' => [],
                    'metadata' => [],
                    'created_at' => $now,
                ]
            )
            ->willReturn($event->reveal())
            ->shouldBeCalled();

        $eventBus = $this->prophesize(EventBus::class);
        $eventBus->dispatch($event)->willThrow(new \Exception())->shouldBeCalled();

        $amqpEventConsumerCallback = new AmqpEventConsumerCallback($eventBus->reveal(), $messageFactory->reveal());
        $deliveryResult = $amqpEventConsumerCallback($envelope->reveal(), $queue->reveal());
        $this->assertEquals(DeliveryResult::MSG_REJECT(), $deliveryResult);
    }

    /**
     * @test
     */
    public function it_rejects_and_requeues_message_when_concurrency_exception_occurred()
    {
        $time = (string) microtime(true);
        if (false === strpos($time, '.')) {
            $time .= '.0000';
        }
        $now = \DateTimeImmutable::createFromFormat('U.u', $time);

        $envelope = $this->prophesize(Envelope::class);
        $envelope
            ->getBody()
            ->willReturn('{"message_name":"test-event","uuid":"ccefedef-85e1-4fd0-b247-ed13d378b050","version":1,"payload":[],"metadata":[],"created_at":"' . $now->format('Y-m-d\TH:i:s.u') . '"}')
            ->shouldBeCalled();
        $envelope->getType()->willReturn('test-event')->shouldBeCalled();

        $queue = $this->prophesize(Queue::class);

        $event = $this->prophesize(Message::class);

        $messageFactory = $this->prophesize(MessageFactory::class);
        $messageFactory
            ->createMessageFromArray(
                'test-event',
                [
                    'message_name' => 'test-event',
                    'uuid' => 'ccefedef-85e1-4fd0-b247-ed13d378b050',
                    'version' => 1,
                    'payload' => [],
                    'metadata' => [],
                    'created_at' => $now,
                ]
            )
            ->willReturn($event->reveal())
            ->shouldBeCalled();

        $eventBus = $this->prophesize(EventBus::class);
        $eventBus->dispatch($event)->willThrow(new MessageDispatchException('', 0, new ConcurrencyException()))->shouldBeCalled();

        $amqpEventConsumerCallback = new AmqpEventConsumerCallback($eventBus->reveal(), $messageFactory->reveal());
        $deliveryResult = $amqpEventConsumerCallback($envelope->reveal(), $queue->reveal());
        $this->assertEquals(DeliveryResult::MSG_REJECT_REQUEUE(), $deliveryResult);
    }
}
