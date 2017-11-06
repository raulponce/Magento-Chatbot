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

namespace Werules\Chatbot\Block\Webhook;

class Index extends \Magento\Framework\View\Element\Template
{
    protected $_helper;
    protected $_chatbotAPI;
    protected $_messageModel;
    protected $_objectManager;
//    protected $_cronWorker;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Werules\Chatbot\Helper\Data $helperData,
        \Werules\Chatbot\Model\ChatbotAPI $chatbotAPI,
        \Werules\Chatbot\Model\MessageFactory $message
//        \Werules\Chatbot\Cron\Worker $cronWorker
    )
    {
        $this->_helper = $helperData;
        $this->_chatbotAPI = $chatbotAPI;
        $this->_messageModel = $message;
        $this->_objectManager = $objectManager;
//        $this->_cronWorker = $cronWorker;
        parent::__construct($context);
    }

    public function getConfigValue($code)
    {
        return $this->_helper->getConfigValue($code);
    }
}
