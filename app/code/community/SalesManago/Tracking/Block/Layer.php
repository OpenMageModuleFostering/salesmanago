<?php
/*
 * Copyright (C) 2012 Clearspring Technologies, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
?>
<?php

class SalesManago_Tracking_Block_Layer extends Mage_Core_Block_Template
{
    protected function _construct()
    {
        parent::_construct();
        $active = Mage::getStoreConfig('salesmanago_tracking/general/active');
        if($active == 1) {
            $this->setTemplate('salesmanago/tracking/tracking.phtml');
        }
    }
    
    public function getClientId(){
      return Mage::getStoreConfig('salesmanago_tracking/general/client_id');
    }
  
    public function getClientApiSecret(){
      return Mage::getStoreConfig('salesmanago_tracking/general/api_secret');
    }
  
    public function getClientEmail(){
      return Mage::getStoreConfig('salesmanago_tracking/general/email');
    }
    
    public function getEndPoint(){
      return Mage::getStoreConfig('salesmanago_tracking/general/endpoint');
    }
	
	public function getAdditionalJs(){
      return Mage::getStoreConfig('salesmanago_tracking/general/additional_js');
    }

    public function isActive(){
        return Mage::getStoreConfig('salesmanago_tracking/general/active');
    }


    public function getTagsRegistration(){
      $tags = Mage::getStoreConfig('salesmanago_tracking/general/tagsRegistration');
      $tags = explode(',', $tags);
      return $tags;
    }

    public function getTagsNewsletter(){
        $tags = Mage::getStoreConfig('salesmanago_tracking/general/tagsNewsletter');
        $tags = explode(',', $tags);
        return $tags;
    }

    public function getTagsPurchase(){
        $tags = Mage::getStoreConfig('salesmanago_tracking/general/tagsPurchase');
        $tags = explode(',', $tags);
        return $tags;
    }
    
    public function getClientSalesManagoId(){
		if(Mage::getSingleton('customer/session')->isLoggedIn()) {
			$customerData = Mage::getSingleton('customer/session')->getCustomer();
			return $customerData->getSalesmanagoContactId();
		}
		
		return false;
	}
}
