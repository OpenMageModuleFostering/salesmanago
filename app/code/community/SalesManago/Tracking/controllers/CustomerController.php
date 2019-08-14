<?php
class SalesManago_Tracking_CustomerController extends Mage_Core_Controller_Front_Action{
	
	protected function _getHelper(){
        return Mage::helper('tracking');
    }

	public function exportAction(){
        $active = Mage::getStoreConfig('salesmanago_tracking/general/active');
        if($active == 1) {
            $clientId = Mage::getStoreConfig('salesmanago_tracking/general/client_id');
            $apiSecret = Mage::getStoreConfig('salesmanago_tracking/general/api_secret');
            $ownerEmail = Mage::getStoreConfig('salesmanago_tracking/general/email');
            $tags = Mage::getStoreConfig('salesmanago_tracking/general/tags');
            $endPoint = Mage::getStoreConfig('salesmanago_tracking/general/endpoint');

            $key = $this->getRequest()->getParam('key');

            if (isset($key) && isset($apiSecret) && $key == sha1($apiSecret)) {

                $apiKey = md5(time() . $apiSecret);

                $data_to_json = array(
                    'apiKey' => $apiKey,
                    'clientId' => $clientId,
                    'requestTime' => time(),
                    'sha' => sha1($apiKey . $clientId . $apiSecret),

                    'upsertDetails' => array(),
                    'owner' => $ownerEmail,
                );

                $collection = Mage::getModel('customer/customer')->getCollection()->addAttributeToSelect('*');

                $i = 0;
                foreach ($collection as $item) {
                    if (!isset($item['salesmanago_contact_id']) || empty($item['salesmanago_contact_id']) || $item['salesmanago_contact_id'] == 0) {
                        $status = Mage::getModel('newsletter/subscriber')->loadByEmail($item['email'])->isSubscribed();

                        if ($status) {
                            $data_to_json['upsertDetails'][$i]['forceOptIn'] = true;
                        } else {
                            $data_to_json['upsertDetails'][$i]['forceOptOut'] = true;
                        }
                        $data_to_json['upsertDetails'][$i]['contact']['email'] = $item['email'];
                        $data_to_json['upsertDetails'][$i]['contact']['name'] = $item['firstname'] . ' ' . $item['lastname'];

                        $i++;
                    }
                }

                $json = json_encode($data_to_json);
                $result = $this->_do_post_request('https://' . $endPoint . '/api/contact/batchupsert', $json);

                $r = json_decode($result, true);

                $return = array();
                if (isset($r['success']) && $r['success'] == true && isset($r['contactIds']) && is_array($r['contactIds'])) {
                    foreach ($r['contactIds'] as $key => $value) {
                        $customer = Mage::getModel('customer/customer')->setWebsiteId(Mage::app()->getStore()->getWebsiteId())->loadByEmail($key);
                        try {
                            $return[$key]['msg'] = 'Successfully added';
                            $customer->setData('salesmanago_contact_id', $value)->save();
                        } catch (Exception $e) {
                            $return[$key]['msg'] = 'Upsert error';
                            Mage::log($e->getMessage());
                        }
                    }
                } else {
                    $return['msg'] = 'BatchUpsert error';
                }
                return $return;
            }
        }
	}
	
	public function syncAction(){
        $active = Mage::getStoreConfig('salesmanago_tracking/general/active');
        if($active == 1) {
            $key = $this->getRequest()->getParam('key');
            $action = $this->getRequest()->getParam('action');

            $clientId = Mage::getStoreConfig('salesmanago_tracking/general/client_id');
            $apiSecret = Mage::getStoreConfig('salesmanago_tracking/general/api_secret');
            $ownerEmail = Mage::getStoreConfig('salesmanago_tracking/general/email');
            $tags = Mage::getStoreConfig('salesmanago_tracking/general/tags');
            $endPoint = Mage::getStoreConfig('salesmanago_tracking/general/endpoint');

            if (isset($key) && isset($apiSecret) && $key == sha1($apiSecret)) {
                $model = Mage::getModel('tracking/customersync');
                $collection = $model->getCollection()->addFieldToFilter('status', 0);
                $dateTime = new DateTime('NOW');

                foreach ($collection as $item) {
                    if (!isset($action) || !ctype_digit($action)) {
                        $action = $item->getAction();
                    }
                    $counter = $item->getCounter();

                    if ($counter < 5) {
                        switch ($action) {
                            case 1: //register
                                $customer = Mage::getModel('customer/customer')->load($item->getCustomerId())->getData();
                                $data = $this->_getHelper()->_setCustomerData($customer);

                                $r = $this->_getHelper()->salesmanagoContactSync($data);

                                if ($r == false || (isset($r['success']) && $r['success'] == false)) {
                                    try {
                                        $model->load($item->getCustomerSyncId())->setCounter($counter + 1)->setUpdateTime($dateTime->format('c'))->setId($item->getCustomerSyncId())->save();
                                    } catch (Exception $e) {
                                        Mage::log($e->getMessage());
                                    }
                                } elseif (isset($r['success']) && $r['success'] == true) {
                                    try {
                                        $model->load($item->getCustomerSyncId())->setCounter($counter + 1)->setUpdateTime($dateTime->format('c'))->setStatus(1)->setId($item->getCustomerSyncId())->save();
                                    } catch (Exception $e) {
                                        Mage::log($e->getMessage());
                                    }
                                }

                                break;
                            case 2: //login 
                                $customer = Mage::getModel('customer/customer')->load($item->getCustomerId())->getData();

                                if (!isset($customer['salesmanago_contact_id']) || empty($customer['salesmanago_contact_id'])) {
                                    $data = $this->_getHelper()->_setCustomerData($customer);

                                    $r = $this->_getHelper()->salesmanagoContactSync($data);

                                    if ($r == false || (isset($r['success']) && $r['success'] == false)) {
                                        try {
                                            $model->load($item->getCustomerSyncId())->setCounter($counter + 1)->setUpdateTime($dateTime->format('c'))->setId($item->getCustomerSyncId())->save();
                                        } catch (Exception $e) {
                                            Mage::log($e->getMessage());
                                        }
                                    } elseif (isset($r['success']) && $r['success'] == true) {
                                        try {
                                            $model->load($item->getCustomerSyncId())->setCounter($counter + 1)->setUpdateTime($dateTime->format('c'))->setStatus(1)->setId($item->getCustomerSyncId())->save();
                                        } catch (Exception $e) {
                                            Mage::log($e->getMessage());
                                        }
                                    }
                                }
                                break;
                            case 3: //order purchase
                                $orderId = $item->getOrderId();
                                $orderDetails = Mage::getModel('sales/order')->load($orderId);

                                $r = $this->_getHelper()->salesmanagoOrderSync($orderDetails);

                                if ($r == false || (isset($r['success']) && $r['success'] == false)) {
                                    try {
                                        $model->load($item->getCustomerSyncId())->setCounter($counter + 1)->setUpdateTime($dateTime->format('c'))->setId($item->getCustomerSyncId())->save();
                                    } catch (Exception $e) {
                                        Mage::log($e->getMessage());
                                    }
                                } elseif (isset($r['success']) && $r['success'] == true) {
                                    try {
                                        $model->load($item->getCustomerSyncId())->setCounter($counter + 1)->setUpdateTime($dateTime->format('c'))->setStatus(1)->setId($item->getCustomerSyncId())->save();
                                    } catch (Exception $e) {
                                        Mage::log($e->getMessage());
                                    }
                                }
                                break;
                            case 4: //newsletter subscribe / unsubscribe
                                $status = Mage::getModel('newsletter/subscriber')->loadByEmail($item->getEmail())->isSubscribed();

                                $apiKey = md5(time() . $apiSecret);

                                $data_to_json = array(
                                    'apiKey' => $apiKey,
                                    'clientId' => $clientId,
                                    'requestTime' => time(),
                                    'email' => $item->getEmail(),
                                    'sha' => sha1($apiKey . $clientId . $apiSecret),
                                    'owner' => $ownerEmail,
                                );

                                if ($status) {
                                    $data_to_json['forceOptIn'] = true;
                                } else {
                                    $data_to_json['forceOptOut'] = true;
                                }

                                $json = json_encode($data_to_json);
                                $result = $this->_do_post_request('https://' . $endPoint . '/api/contact/update', $json);

                                $r = json_decode($result, true);

                                if ($r == false || (isset($r['success']) && $r['success'] == false)) {
                                    try {
                                        $model->load($item->getCustomerSyncId())->setCounter($counter + 1)->setUpdateTime($dateTime->format('c'))->setId($item->getCustomerSyncId())->save();
                                    } catch (Exception $e) {
                                        Mage::log($e->getMessage());
                                    }
                                } elseif (isset($r['success']) && $r['success'] == true) {
                                    try {
                                        $model->load($item->getCustomerSyncId())->setCounter($counter + 1)->setUpdateTime($dateTime->format('c'))->setStatus(1)->setId($item->getCustomerSyncId())->save();
                                    } catch (Exception $e) {
                                        Mage::log($e->getMessage());
                                    }
                                }
                                break;
                            default:
                                break;
                        }
                    }
                }
            }
        }
	}
	
	private function _do_post_request($url, $data) {
        $active = Mage::getStoreConfig('salesmanago_tracking/general/active');
        if($active == 1) {
            $connection_timeout = Mage::getStoreConfig('salesmanago_tracking/general/connection_timeout');

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            if (isset($connection_timeout) && !empty($connection_timeout)) {
                curl_setopt($ch, CURLOPT_TIMEOUT_MS, $connection_timeout);
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Content-Length: ' . strlen($data)));

            $result = curl_exec($ch);

            if (curl_errno($ch) > 0) {
                if (curl_errno($ch) == 28) {
                    Mage::log("TIMEOUT ERROR NO: " . curl_errno($ch));
                } else {
                    Mage::log("ERROR NO: " . curl_errno($ch));
                }
                return false;
            }

            return $result;
        }
	}
}