<?php
/**
 * Magento Chatbot Integration
 * Copyright (C) 2017
 *
 * This file is part of Werules/Chatbot.
 *
 * Werules/Chatbot is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace Werules\Chatbot\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    protected $storeManager;
    protected $objectManager;

    //const XML_PATH_CHATBOT = 'werules_chatbot_general/';

    public function __construct(Context $context,
        ObjectManagerInterface $objectManager,
        StoreManagerInterface $storeManager
    )
    {
        $this->objectManager = $objectManager;
        $this->storeManager  = $storeManager;
        parent::__construct($context);
    }

    public function getConfigValue($field, $storeId = null)
    {
        return $this->scopeConfig->getValue(
            $field, ScopeInterface::SCOPE_STORE, $storeId
        );
    }

    protected function getJsonResponse($success)
    {
        header_remove('Content-Type'); // TODO
        header('Content-Type: application/json'); // TODO
        if ($success)
            $arr = array("status" => "success", "success" => true);
        else
            $arr = array("status" => "error", "success" => false);
        return json_encode($arr);
    }

    public function getJsonSuccessResponse()
    {
        return $this->getJsonResponse(true);
    }

    public function getJsonErrorResponse()
    {
        return $this->getJsonResponse(false);
    }

//    public function getConfig($code, $storeId = null)
//    {
//        return $this->getConfigValue(self::XML_PATH_CHATBOT . $code, $storeId);
//    }
}