<?php
/*
 * This file is part of the prooph/psb-bernard-producer.
 * (c) 2016 prooph software GmbH <contact@prooph.de>
 * (c) 2016 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare (strict_types=1);

namespace Prooph\ServiceBus\Message\HumusAmqp;

use Humus\Amqp\DeliveryResult;
use Humus\Amqp\Envelope;
use Humus\Amqp\Queue;
use Prooph\Common\Messaging\MessageFactory;
use Prooph\ServiceBus\CommandBus;

/**
 * Class AmqpCommandConsumerCallback
 * @package Dimabay\ServiceBus
 */
final class AmqpCommandConsumerCallback
{
    /**
     * @var CommandBus
     */
    private $commandBus;

    /**
     * @var MessageFactory
     */
    private $messageFactory;

    /**
     * AmqpCommandConsumerCallback constructor.
     * @param CommandBus $commandBus
     * @param MessageFactory $messageFactory
     */
    public function __construct(CommandBus $commandBus, MessageFactory $messageFactory)
    {
        $this->commandBus = $commandBus;
        $this->messageFactory = $messageFactory;
    }

    /**
     * @param Envelope $envelope
     * @param Queue $queue
     * @return DeliveryResult
     */
    public function __invoke(Envelope $envelope, Queue $queue)
    {
        $data = json_decode($envelope->getBody(), true);
        $data['created_at'] = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u', $data['created_at']);

        try {
            $command = $this->messageFactory->createMessageFromArray($envelope->getType(), $data);
            $this->commandBus->dispatch($command);
        } catch (\Exception $e) {
            return DeliveryResult::MSG_REJECT();
        }

        return DeliveryResult::MSG_ACK();
    }
}
