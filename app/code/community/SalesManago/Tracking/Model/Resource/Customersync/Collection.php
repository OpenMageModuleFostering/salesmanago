<?php
class SalesManago_Tracking_Model_Resource_Customersync_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    public function _construct()
    {
        $this->_init('tracking/customersync');
    }
}