<?php
namespace Emartech\Emarsys\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Class Uninstall
 * @package Emartech\Emarsys\Setup
 */
class Event extends AbstractDb
{
  /**
   * construct
   * @return void
   */
    // @codingStandardsIgnoreLine
    protected function _construct()
    {
        $this->_init('emarsys_events_data', 'event_id');
    }
}
