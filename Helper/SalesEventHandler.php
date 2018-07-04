<?php


namespace Emartech\Emarsys\Helper;


use Emartech\Emarsys\Model\ResourceModel\Event;
use Emartech\Emarsys\Model\SettingsFactory;
use Emartech\Emarsys\Model\EventFactory;
use Magento\Sales\Model\OrderFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Newsletter\Model\Subscriber;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

class SalesEventHandler extends AbstractHelper
{
  protected $logger;
  protected $orderFactory;
  protected $eventFactory;
  protected $eventResource;
  protected $subscriber;
  protected $emarsysData;

  public function __construct(
    Data $emarsysData,
    OrderFactory $orderFactory,
    EventFactory $eventFactory,
    Event $eventResource,
    Subscriber $subscriber,
    LoggerInterface $logger
  )
  {
    $this->orderFactory = $orderFactory;
    $this->eventFactory = $eventFactory;
    $this->eventResource = $eventResource;
    $this->logger = $logger;
    $this->subscriber = $subscriber;

    $this->emarsysData = $emarsysData;
  }

  /**
   * @param $event_type
   * @param $orderData
   * @throws \Exception
   * @throws \Magento\Framework\Exception\AlreadyExistsException
   */
  public function store($event_type, $orderData)
  {
    if (!$this->emarsysData->isEnabled(Data::SALES_EVENTS)) return;

    /** @var \Emartech\Emarsys\Model\Event $eventModel */
    $eventModel = $this->eventFactory->create();
    $eventModel->setData('event_type', $event_type);
    $eventModel->setData('event_data', json_encode($orderData));
    $this->eventResource->save($eventModel);

    $this->logger->info('event_type: ' . $event_type . ', event_data: ' . json_encode($orderData));
  }
}