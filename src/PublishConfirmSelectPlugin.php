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

namespace Prooph\ServiceBus\Message\HumusAmqp;

use Humus\Amqp\Producer;
use Prooph\Common\Event\ActionEvent;
use Prooph\EventStore\EventStore;
use Prooph\EventStore\Plugin\Plugin;
use Prooph\ServiceBus\Exception\RuntimeException;

/**
 * Class PublishConfirmSelectPlugin
 * @package Prooph\ServiceBus\Message\HumusAmqp
 */
final class PublishConfirmSelectPlugin implements Plugin
{
    /**
     * @var Producer
     */
    private $producer;

    /**
     * @param Producer $producer
     */
    public function __construct(Producer $producer)
    {
        $this->producer = $producer;
    }

    /**
     * @param EventStore $eventStore
     * @return void
     */
    public function setUp(EventStore $eventStore)
    {
        $eventStore->getActionEventEmitter()->attachListener('commit.pre', [$this, 'onEventStoreCommitPre']);
        $eventStore->getActionEventEmitter()->attachListener('commit.post', [$this, 'onEventStoreCommitPost'], -1000);
    }

    /**
     * Start confirm select
     *
     * @param ActionEvent $actionEvent
     */
    public function onEventStoreCommitPre(ActionEvent $actionEvent)
    {
        $recordedEvents = $actionEvent->getParam('recordedEvents', []);

        $countRecordedEvents = count($recordedEvents);

        $this->producer->confirmSelect();

        $this->producer->setConfirmCallback(
            function (int $deliveryTag, bool $multiple) use (& $countRecordedEvents) {
                return ($deliveryTag <= $countRecordedEvents);
            },
            function (int $deliveryTag, bool $multiple, bool $requeue) use (&$result) {
                throw new RuntimeException('Could not publish all events');
            }
        );
    }

    /**
     * Publish recorded events on the event bus
     *
     * @param ActionEvent $actionEvent
     */
    public function onEventStoreCommitPost(ActionEvent $actionEvent)
    {
        $this->producer->waitForConfirm(1);
    }
}
