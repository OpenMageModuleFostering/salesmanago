<?php
class SalesManago_Tracking_Model_Customersync extends Mage_Core_Model_Abstract
{
    protected function _construct()
    {
		parent::_construct();
        $this->_init('tracking/customersync');
    }
}