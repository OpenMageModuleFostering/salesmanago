<?php
$installer = $this;
 
$installer->startSetup();

$installer->run("
DROP TABLE IF EXISTS {$installer->getTable('tracking/customersync')};
CREATE TABLE {$installer->getTable('tracking/customersync')} (
  `customer_sync_id` int(11) unsigned NOT NULL auto_increment,
  `email` varchar(255) NULL,
  `customer_id` int(11) NULL,
  `order_id` int(11) NULL,
  `hash` varchar(40) NOT NULL default '0',
  `status` smallint(3) NOT NULL default '0',
  `action` smallint(3) NOT NULL default '0',
  `counter` smallint(3) NOT NULL default '0',
  `created_time` datetime NULL,
  `update_time` datetime NULL,
  PRIMARY KEY (`customer_sync_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
");

$installer->addAttribute('customer', 'salesmanago_contact_id', array(
    'type' => 'varchar',
    'input' => 'text',
    'label' => 'SALESmanago Contact ID',
    'global' => 1,
    'visible' => 1,
    'required' => 0,
    'user_defined' => 1,
    'default' => '0',
    'visible_on_front' => 0
));
Mage::getSingleton('eav/config')
    ->getAttribute('customer', 'salesmanago_contact_id')
    ->setData('used_in_forms', array('adminhtml_customer'))
    ->save();
 
$installer->endSetup();
