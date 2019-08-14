<?php

class SalesManago_Tracking_Model_Observer {
 
	protected function _getHelper(){
        return Mage::helper('tracking');
    }
	
	/*
	* BK Changes to Sales Manago Module.
	* Adding Observer to Review event.
	* Will send Sales Manago API If a customer review is approved.
	* @author:Carlos Alonso de Linaje.
	* @date: 28/Oct/2015
	*/
	public function review_save_after($observer)
	{
	    $active = Mage::getStoreConfig('salesmanago_tracking/general/active');
        if($active == 1) {
            $object = $observer->getEvent()->getObject();
            $review = $object->getData();
            //Only send data if is a Customer (not just Guest)
            //And if the review is approved
            if ($review['status_id']==1 && array_key_exists('customer_id', $review))
            {
                $r =$this->_getHelper()->salesmanagoReviewSync($review);
                return $r;
                Mage::log($r);
            }
        }
	    return false;
	}
    public function customer_login($observer) {
        $active = Mage::getStoreConfig('salesmanago_tracking/general/active');
        if($active == 1) {
            $customer = $observer->getCustomer()->getData();

            if (is_array($customer) && !empty($customer)) {
                if (!isset($customer['salesmanago_contact_id']) || empty($customer['salesmanago_contact_id'])) {
                    $data = $this->_getHelper()->_setCustomerData($customer);

                    $r = $this->_getHelper()->salesmanagoContactSync($data);

                    if ($r == false || (isset($r['success']) && $r['success'] == false)) {
                        $data['status'] = 0;
                        $data['action'] = 2; //logowanie
                        $this->_getHelper()->setSalesmanagoCustomerSyncStatus($data);
                    }

                    if (isset($r['contactId']) && !empty($r['contactId'])) {
                        try {
                            $observer->getCustomer()->setData('salesmanago_contact_id', $r['contactId'])->save();
                        } catch (Exception $e) {
                            Mage::log($e->getMessage());
                        }
                    }
                }
            }
        }
		if(!isset($_COOKIE['smclient']) || empty($_COOKIE['smclient'])){
                $period = time() + 36500 * 86400;
                $customerData = Mage::getSingleton('customer/session')->getCustomer();
                $contactId = $customerData->getSalesmanagoContactId();
                $this->_getHelper()->sm_create_cookie('smclient', $contactId, $period);
        }
    }
  
    public function customer_register_success($observer) {
        $active = Mage::getStoreConfig('salesmanago_tracking/general/active');
        if($active == 1) {
            $customer = $observer->getCustomer()->getData();

            if (is_array($customer) && !empty($customer)) {
                $data = $this->_getHelper()->_setCustomerData($customer);

                $r = $this->_getHelper()->salesmanagoContactSync($data);

                if ($r == false || (isset($r['success']) && $r['success'] == false)) {
                    $data['status'] = 0;
                    $data['action'] = 1; //rejestracja
                    $this->_getHelper()->setSalesmanagoCustomerSyncStatus($data);
                }

                if (isset($r['contactId']) && !empty($r['contactId'])) {
                    try {
                        $observer->getCustomer()->setData('salesmanago_contact_id', $r['contactId'])->save();
                    } catch (Exception $e) {
                        Mage::log($e->getMessage());
                    }
                }
            }
        }
		if(!isset($_COOKIE['smclient']) || empty($_COOKIE['smclient'])){
                $period = time() + 36500 * 86400;
                $customerData = Mage::getSingleton('customer/session')->getCustomer();
                $contactId = $customerData->getSalesmanagoContactId();
                $this->_getHelper()->sm_create_cookie('smclient', $contactId, $period);
        }
		
	}
	
    /*
    * Dodanie zdarzenia addContactExtEvent na podsumowaniu zam�wienia z typem PURCHASE
    */
	public function checkout_onepage_controller_success_action($observer){
        $active = Mage::getStoreConfig('salesmanago_tracking/general/active');
        if($active == 1) {
            $orderId = Mage::getSingleton('checkout/session')->getLastOrderId();
            $orderDetails = Mage::getModel('sales/order')->load($orderId);
            $r = $this->_getHelper()->salesmanagoOrderSync($orderDetails);

            if ($r == false || (isset($r['success']) && $r['success'] == false)) {
                $data = array(
                    'customerEmail' => $orderDetails->getCustomerEmail(),
                    'entity_id' => $orderDetails->getCustomerId(),
                    'order_id' => $orderDetails->getEntityId(),
                    'status' => 0,
                    'action' => 3, //zlozenie zamowienia: addContactExtEvent - PURCHASE
                );
                $this->_getHelper()->setSalesmanagoCustomerSyncStatus($data);
            }
            $this->_getHelper()->salesmanagoSalesSync($orderDetails);
            $eventId = Mage::getSingleton('core/session')->getEventId();
            if (isset($eventId) && !empty($eventId)) {
                Mage::getSingleton('core/session')->unsEventId();
            }
        }
	}
	
	/*
	* BK Changes to Sales Manago Module.
	* Adding Observer to Whislist event.
	* Will Save WishList products on customer's Custom Details 
	* @author:Carlos Alonso de Linaje.
	* @date: 17/Oct/2015
	*/
	public function wishlist_item_collection_products_after_load($observer)
	{
	    Mage::log('whislist');
	    $active = Mage::getStoreConfig('salesmanago_tracking/general/active');
        if($active == 1) 
        {       
            $_itemCollection = Mage::helper('wishlist')->getWishlistItemCollection();
            foreach ($_itemCollection as $_item) 
            {
                $_products .= $_item->getProduct()->getId().',';
            }
            
            $clientId = Mage::getStoreConfig('salesmanago_tracking/general/client_id');
            $apiSecret = Mage::getStoreConfig('salesmanago_tracking/general/api_secret');
            $apiKey = md5(time() . $apiSecret);
            $ownerEmail = Mage::getStoreConfig('salesmanago_tracking/general/email');
            $email = Mage::getSingleton('customer/session')->getCustomer()->getEmail();
            $endPoint = Mage::getStoreConfig('salesmanago_tracking/general/endpoint');
            
            $data_to_json = array(
                    'apiKey' => $apiKey,
                    'clientId' => $clientId,
                    'requestTime' => time(),
                    'contact' => array(
                        'email' => $email,
                        'state' => 'CUSTOMER',
                    ),
                    'sha' => sha1($apiKey . $clientId . $apiSecret),
                    'owner' => $ownerEmail,
            );
            //BK Change Add a new tag on newsletter subscribe
            $data_to_json['properties'] = array(
                'WhislistDate_'.str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST)) => 
                        Mage::getModel('core/date')->date('Y-m-d H:i:s'),
                'WhislistProducts_'.str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST)) =>
                        $_products
            );      
            
            $json = json_encode($data_to_json);
            $result = $this->_getHelper()->_doPostRequest('https://' . $endPoint . '/api/contact/upsert', $json);
            $r = json_decode($result, true);
            return $r;
        }
	}
	
    /*
    * Dodanie (oraz na biezaco modyfikowanie) zdarzenia w koszyku addContactExtEvent z typem CART
    */
	public function checkout_cart_save_after($observer){
        $active = Mage::getStoreConfig('salesmanago_tracking/general/active');
        if($active == 1) {
            $cartHelper = Mage::getModel('checkout/cart')->getQuote();
            $items = $cartHelper->getAllVisibleItems();
            $itemsNamesList = array();
            foreach ($items as $item) {
                array_push($itemsNamesList, $item->getProduct()->getId());
            }


            $clientId = Mage::getStoreConfig('salesmanago_tracking/general/client_id');
            $apiSecret = Mage::getStoreConfig('salesmanago_tracking/general/api_secret');
            $ownerEmail = Mage::getStoreConfig('salesmanago_tracking/general/email');
            $endPoint = Mage::getStoreConfig('salesmanago_tracking/general/endpoint');

            $customerEmail = Mage::getSingleton('customer/session')->getCustomer()->getEmail();
            $isLoggedIn = Mage::getSingleton('customer/session')->isLoggedIn();
            //if (!empty($customerEmail)) {
                $apiKey = md5(time() . $apiSecret);
                $dateTime = new DateTime('NOW');

                $data_to_json = array(
                    'apiKey' => $apiKey,
                    'clientId' => $clientId,
                    'requestTime' => time(),
                    'sha' => sha1($apiKey . $clientId . $apiSecret),
                    'owner' => $ownerEmail,
                    'contactEvent' => array(
                        'date' => $dateTime->format('c'),
                        'contactExtEventType' => 'CART',
                        'products' => implode(',', $itemsNamesList),
                        'value' => $cartHelper->getGrandTotal(),
                    ),
                );

                $eventId = Mage::getSingleton('core/session')->getEventId();

                if (isset($eventId) && !empty($eventId)) {
                    $data_to_json['contactEvent']['eventId'] = $eventId;
                    $json = json_encode($data_to_json);
                    $result = $this->_getHelper()->_doPostRequest('https://' . $endPoint . '/api/contact/updateContactExtEvent', $json);
                } else {
                    if($isLoggedIn){
                        $data_to_json['email'] = $customerEmail;
                    }
                    else {
                        if(!empty($_COOKIE['smclient']))
                            $data_to_json['contactId'] = $_COOKIE['smclient'];
                    }

                    Mage::log(serialize($data_to_json), null, 'mylogfile.log');

                    $json = json_encode($data_to_json);
                    $result = $this->_getHelper()->_doPostRequest('https://' . $endPoint . '/api/contact/addContactExtEvent', $json);
                }

                $r = json_decode($result, true);

                if (!isset($eventId) && isset($r['eventId'])) {
                    Mage::getSingleton('core/session')->setEventId($r['eventId']);
                }
            //}
        }
	}
	
	public function newsletter_subscriber_save_before($observer){
        $active = Mage::getStoreConfig('salesmanago_tracking/general/active');
        if($active == 1) {
            $request = Mage::app()->getRequest();
            $moduleName = $request->getModuleName();
            $controllerName = $request->getControllerName();
            $actionName = $request->getActionName();

            if (($moduleName == 'newsletter' && $controllerName == 'manage' && $actionName == 'save') ||
                ($moduleName == 'newsletter' && $controllerName == 'subscribe' && $actionName == 'unsubscribe') ||
                ($moduleName == 'newsletter' && $controllerName == 'subscriber' && $actionName == 'new') ||
                ($moduleName == 'newsletter' && $controllerName == 'subscriber' && $actionName == 'unsubscribe') ||
                ($moduleName == 'admin' && $controllerName == 'newsletter_subscriber' && $actionName == 'massUnsubscribe')
            ) {

                $isAdmin = false;
                if ($moduleName == 'admin') $isAdmin = true;

                $clientId = Mage::getStoreConfig('salesmanago_tracking/general/client_id');
                $apiSecret = Mage::getStoreConfig('salesmanago_tracking/general/api_secret');
                $ownerEmail = Mage::getStoreConfig('salesmanago_tracking/general/email');
                $tags = Mage::getStoreConfig('salesmanago_tracking/general/tags');
                $endPoint = Mage::getStoreConfig('salesmanago_tracking/general/endpoint');


                $subscriber = $observer->getEvent()->getDataObject();
                $data = $subscriber->getData();
                $email = $data['subscriber_email'];
                $statusChange = $subscriber->getIsStatusChanged();

                $id = (int)Mage::app()->getFrontController()->getRequest()->getParam('id');
                $code = (string)Mage::app()->getFrontController()->getRequest()->getParam('code');

                $apiKey = md5(time() . $apiSecret);

                //BK Change to PROSPECT
                $data_to_json = array(
                    'apiKey' => $apiKey,
                    'clientId' => $clientId,
                    'requestTime' => time(),
                    'contact' => array(
                        'email' => $email,
                        'state' => 'PROSPECT',
                    ),
                    'sha' => sha1($apiKey . $clientId . $apiSecret),
                    'owner' => $ownerEmail,
                );
                //BK Change Add a new tag on newsletter subscribe
                $data_to_json['tags'] = array('Newsletter_'.
                		str_replace('.','_',parse_url(Mage::getBaseUrl (Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST)));
                //Mage::log($data_to_json);


                if ($data['subscriber_status'] == "1" && ($statusChange == true || ($id && $code) || $isAdmin)) {
                    $data_to_json['forceOptIn'] = true;
                } elseif ($data['subscriber_status'] == "3" && ($statusChange == true || ($id && $code) || $isAdmin)) {
                    $data_to_json['forceOptOut'] = true;
                } elseif ($actionName == 'massDelete') {
                    $data_to_json['forceOptOut'] = true;
                }
                
                
            
                $json = json_encode($data_to_json);
                $result = $this->_getHelper()->_doPostRequest('https://' . $endPoint . '/api/contact/upsert', $json);

                $r = json_decode($result, true);

                if ($r == false || (isset($r['success']) && $r['success'] == false)) {
                    $data['customerEmail'] = $email;
                    $data['status'] = 0;
                    $data['action'] = 4; //zapis / wypis z newslettera
                    $this->_getHelper()->setSalesmanagoCustomerSyncStatus($data);
                }

                if ($moduleName == 'newsletter' && $controllerName == 'subscriber' && $actionName == 'new') {
                    $period = time() + 3650 * 86400;
                    $this->_getHelper()->sm_create_cookie('smclient', $r['contactId'], $period);
                }

                return $r;
            }
        }
	}
	public function newsletter_subscriber_delete_after($observer)
    {
        $active = Mage::getStoreConfig('salesmanago_tracking/general/active');
        if ($active == 1) {
            $request = Mage::app()->getRequest();
            $moduleName = $request->getModuleName();
            $controllerName = $request->getControllerName();
            $actionName = $request->getActionName();

            if (($moduleName == 'admin' && $controllerName == 'newsletter_subscriber' && $actionName == 'massDelete')) {

                $clientId = Mage::getStoreConfig('salesmanago_tracking/general/client_id');
                $apiSecret = Mage::getStoreConfig('salesmanago_tracking/general/api_secret');
                $ownerEmail = Mage::getStoreConfig('salesmanago_tracking/general/email');
                $tags = Mage::getStoreConfig('salesmanago_tracking/general/tags');
                $endPoint = Mage::getStoreConfig('salesmanago_tracking/general/endpoint');

                $subscriber = $observer->getEvent()->getDataObject();
                $data = $subscriber->getData();
                $email = $data['subscriber_email'];
                $statusChange = $subscriber->getIsStatusChanged();

                $apiKey = md5(time() . $apiSecret);

                $data_to_json = array(
                    'apiKey' => $apiKey,
                    'clientId' => $clientId,
                    'requestTime' => time(),
                    'contact' => array(
                        'email' => $email,
                        'state' => 'CUSTOMER',
                    ),
                    'sha' => sha1($apiKey . $clientId . $apiSecret),
                    'owner' => $ownerEmail,
                    'forceOptOut' => true,
                );

                $json = json_encode($data_to_json);
                $result = $this->_getHelper()->_doPostRequest('https://' . $endPoint . '/api/contact/upsert', $json);

                $r = json_decode($result, true);

                return $r;
            }
        }
    }
}

