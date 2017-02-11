<?php
	include("Api/Telegram/Telegram.php");

	class Werules_Chatbot_Model_Chatdata extends Mage_Core_Model_Abstract
	{
		// APIs
		private $api_type = "";
		private $tg_bot = "telegram";
		private $fb_bot = "facebook";
		private $wapp_bot = "whatsapp";
		private $wechat_bot = "wechat";

		// CONVERSATION STATES
		private $start_state = 0;
		private $list_cat_state = 1;
		private $list_prod_state = 2;
		private $search_state = 3;
		private $login_state = 4;
		private $list_orders_state = 5;
		private $reorder_state = 6;
		private $add2cart_state = 7;
		private $checkout_state = 9;
		private $track_order_state = 10;
		private $support_state = 11;
		private $send_email_state = 12;
		private $clear_cart_state = 13;

		// COMMANDS
		private $cmd_list = "start,list_cat,search,login,list_orders,reorder,add2cart,checkout,clear_cart,track_order,support,send_email,exit_support";
		private $start_cmd = "";
		private $listacateg_cmd = "";
		private $search_cmd = "";
		private $login_cmd = "";
		private $listorders_cmd = "";
		private $reorder_cmd = "";
		private $add2cart_cmd = "";
		private $checkout_cmd = "";
		private $clearcart_cmd = "";
		private $trackorder_cmd = "";
		private $support_cmd = "";
		private $sendemail_cmd = "";
		private $cancel_cmd = "";

		// REGEX
		private $unallowed_characters = "/[^A-Za-z0-9 _]/";
		
		// DEFAULT MESSAGES
		private $errormsg = "";
		private $cancelmsg = "";
		private $positivemsg = array();

		public function _construct()
		{
			//parent::_construct();
			$this->_init('chatbot/chatdata'); // this is location of the resource file.
		}

		// GENERAL FUNCTIONS
		public function requestHandler($apiType) // handle request
		{
			$apiKey = $this->getApikey($apiType);
			if ($apiType == $this->tg_bot && $apiKey) // telegram api
			{
				// all logic goes here
				$this->telegramHandler($apiKey);
			}
			else if ($apiType == $this->fb_bot && $apiKey) // facebook api
			{
				// all logic goes here
				$this->facebookHandler($apiKey);
			}
			else
				return "error 101"; // TODO
		}

		private function getApikey($apiType) // check if bot integration is enabled
		{
			if ($apiType == $this->tg_bot) // telegram api
			{
				$enabled = Mage::getStoreConfig('chatbot_enable/telegram_config/enable_bot');
				$apikey = Mage::getStoreConfig('chatbot_enable/telegram_config/telegram_api_key');
				if ($enabled == 1 && $apikey) // is enabled and has API
					return $apikey;
			}
			else if ($apiType == $this->fb_bot)
			{
//				$enabled = Mage::getStoreConfig('chatbot_enable/facebook_config/enable_bot');
//				$apikey = Mage::getStoreConfig('chatbot_enable/facebook_config/facebook_api_key');
//				if ($enabled == 1 && $apikey) // is enabled and has API
//					return $apikey;
				return "error 101"; // TODO
			}
			return null;
		}

		private function addProd2Cart($prodId) // TODO add expiration date for sessions
		{
			$checkout = Mage::getSingleton('checkout/session');
			$cart = Mage::getModel("checkout/cart");
			try
			{
				$hasquote = $this->getSessionId() && $this->getQuoteId(); // has class quote and session ids
				if ($this->getIsLogged() == "1")
				{
					// if user is set as logged, then login using magento singleton
					Mage::getSingleton('customer/session')->loginById($this->getCustomerId());
					if (!$hasquote)
					{ // if class still dosen't have quote and session ids, init here
						// set current quote as customer quote
						$customer = Mage::getModel('customer/customer')->load((int)$this->getCustomerId());
						$quote = Mage::getModel('sales/quote')->loadByCustomer($customer);
						$cart->setQuote($quote);
						// attach checkout session to logged customer
						$checkout->setCustomer($customer);
						//$checkout->setSessionId($customersssion->getEncryptedSessionId());
						//$quote = $checkout->getQuote();
						//$quote->setCustomer($customer);
					}
				}
				if ($hasquote)
				{
					// init quote and session from chatbot class
					$cart->setQuote(Mage::getModel('sales/quote')->loadByIdWithoutStore((int)$this->getQuoteId()));
					$checkout->setSessionId($this->getSessionId());
				}
				// add product and save cart
				$cart->addProduct($prodId);
				$cart->save();
				$checkout->setCartWasUpdated(true);

				// update chatdata class data with quote and session ids
				$data = array(
					"session_id" => $checkout->getEncryptedSessionId(),
					"quote_id" => $checkout->getQuote()->getId()
				);
				$this->addData($data);
				$this->save();
			}
			catch (Exception $e)
			{
				return false;
			}

			return true;
		}

		private function getCommandString($cmdId)
		{
			if ($this->api_type == $this->tg_bot)
				$confpath = 'chatbot_enable/telegram_config/';
			else if ($this->api_type == $this->fb_bot)
				$confpath = 'chatbot_enable/facebook_config/';
//			else if ($this->api_type == $this->wapp_bot)
//				$confpath = 'chatbot_enable/whatsapp_config/';

			$config = Mage::getStoreConfig($confpath . 'enabled_commands');
			$enabledCmds = explode(',', $config);
			if (in_array($cmdId, $enabledCmds))
			{
				$config = Mage::getStoreConfig($confpath . 'commands_code');
				$commands = explode("\n", $config); // command codes split by linebreak
				$defaultCmds = explode(',', $this->cmd_list);
				if (count($commands) < count($defaultCmds))
				{
					return preg_replace( // remove all non-alphanumerics
						$this->unallowed_characters,
						'',
						str_replace( // replace whitespace for underscore
							' ',
							'_',
							trim(
								array_merge( // merge arrays
									$commands,
									array_slice(
										$defaultCmds,
										count($commands)
									)
								)[$cmdId - 1]
							)
						)
					);
				}
				return preg_replace( // remove all non-alphanumerics
					$this->unallowed_characters,
					'',
					str_replace( // replace whitespace for underscore
						' ',
						'_',
						trim(
							$commands[$cmdId - 1]
						)
					)
				);
			}
			return "";
		}

		private function getCommandValue($text, $cmd)
		{
			if (strlen($text) > strlen($cmd))
				return substr($text, strlen($cmd), strlen($text));
			return null;
		}

		private function checkCommand($text, $cmd)
		{
			if ($cmd)
				return substr($text, 0, strlen($cmd)) == $cmd;
			return false;
		}

		private function clearCart()
		{
			try
			{
				if ($this->getIsLogged() == "1")
				{
					// if user is set as logged, then login using magento singleton
					Mage::getSingleton('customer/session')->loginById($this->getCustomerId());
					// load quote from logged user and delete it
					Mage::getModel('sales/quote')->loadByCustomer((int)$this->getCustomerId())->delete();
					// clear checout session quote
					Mage::getSingleton('checkout/session')->setQuoteId(null);
					//Mage::getSingleton('checkout/cart')->truncate()->save();
				}
				$data = array(
					"session_id" => "",
					"quote_id" => ""
				);
				$this->addData($data);
				$this->save();
			}
			catch (Exception $e)
			{
				return false;
			}

			return true;
		}

		public function updateChatdata($datatype, $state)
		{
			try
			{
				$data = array($datatype => $state); // data to be insert on database
				$this->addData($data);
				$this->save();
			}
			catch (Exception $e)
			{
				return false;
			}

			return true;
		}

		private function excerpt($text, $size)
		{
			if (strlen($text) > $size)
			{
				$text = substr($text, 0, $size);
				$text = substr($text, 0, strrpos($text, " "));
				$etc = " ...";
				$text = $text . $etc;
			}
			return $text;
		}

		private function getOrdersIdsFromCustomer()
		{
			$ids = array();
			$orders = Mage::getResourceModel('sales/order_collection')
				->addFieldToSelect('*')
				->addFieldToFilter('customer_id', $this->getCustomerId())
				->setOrder('created_at', 'desc');
			foreach ($orders as $_order)
			{
				array_push($ids, $_order->getId());
			}
			return $ids;
		}

		private function getProductIdsBySearch($searchstring)
		{
			$ids = array();
			// Code to Search Product by $searchstring and get Product IDs
			$product_collection = Mage::getResourceModel('catalog/product_collection')
				->addAttributeToSelect('*')
				->addAttributeToFilter('name', array('like' => '%'.$searchstring.'%'))
				->load();

			foreach ($product_collection as $product) {
				$ids[] = $product->getId();
			}
			//return array of product ids
			return $ids;
		}

		private function validateTelegramCmd($cmd)
		{
			if ($cmd == "/")
				return null;
			return $cmd;
		}

		private function loadImageContent($productID)
		{
			$imagepath = Mage::getModel('catalog/product')->load($productID)->getSmallImage();
			if ($imagepath && $imagepath != "no_selection")
			{
				$absolutePath =
					Mage::getBaseDir('media') .
					"/catalog/product" .
					$imagepath;

				return curl_file_create($absolutePath, 'image/jpg');
			}
			return null;
		}

		// TELEGRAM FUNCTIONS
		private function prepareTelegramOrderMessages($orderID) // TODO add link to product name
		{
			$order = Mage::getModel('sales/order')->load($orderID);
			if ($order)
			{
				$message = Mage::helper('core')->__("Order") . " # " . $order->getIncrementId() . "\n\n";
				$items = $order->getAllVisibleItems();
				foreach($items as $item)
				{
					$message .= (int)$item->getQtyOrdered() . "x " .
						$item->getName() . "\n" .
						Mage::helper('core')->__("Price") . ": " . Mage::helper('core')->currency($item->getPrice(), true, false) . "\n\n";
				}
				$message .= Mage::helper('core')->__("Total") . ": " . Mage::helper('core')->currency($order->getGrandTotal(), true, false) . "\n" .
					Mage::helper('core')->__("Zipcode") . ": " . $order->getShippingAddress()->getPostcode();
				if ($this->reorder_cmd)
					$message .= "\n\n" . Mage::helper('core')->__("Reorder") . ": " . $this->reorder_cmd . $orderID;
				return $message;
			}
			return null;
		}

		private function prepareTelegramProdMessages($productID) // TODO add link to product name
		{
			$_product = Mage::getModel('catalog/product')->load($productID);
			if ($_product)
			{
				$message = $_product->getName() . "\n" .
					$this->excerpt($_product->getShortDescription(), 60) . "\n" .
					Mage::helper('core')->__("Add to cart") . ": " . $this->add2cart_cmd . $_product->getId();
				return $message;
			}
			return null;
		}

		private function telegramHandler($apiKey)
		{
			// Instances the Telegram class
			$telegram = new Telegram($apiKey);

			// Take text and chat_id from the message
			$text = $telegram->Text();
			$chat_id = $telegram->ChatID();

			// send feedback to user
			$telegram->sendChatAction(array('chat_id' => $chat_id, 'action' => 'typing'));

			// mage helper
			$magehelper = Mage::helper('core');

			$supportgroup = Mage::getStoreConfig('chatbot_enable/telegram_config/telegram_support_group');
			if ($supportgroup[0] == "g") // remove the 'g' from groupd id, and add '-'
				$supportgroup = "-" . ltrim($supportgroup, "g");

			// if it's a group message
			if ($telegram->messageFromGroup())
			{
				if ($chat_id == $supportgroup) // if the group sending the message is the support group
				{
					if ($telegram->ReplyToMessageID()) // if the message is replying another message
					{
						$telegram->sendMessage(array('chat_id' => $telegram->ReplyToMessageFromUserID(), 'text' => $magehelper->__("Message from support") . ":\n" . $text)); // TODO
						$telegram->sendMessage(array('chat_id' => $chat_id, 'text' => $magehelper->__("Message sent."))); // TODO
					}
					return;
				}
				$telegram->sendMessage(array('chat_id' => $chat_id, 'text' => $magehelper->__("I don't work with groups."))); // TODO
				return; // ignore all group messages
			}

			// Instances the model class
			$chatdata = $this->load($chat_id, 'telegram_chat_id');
			$chatdata->api_type = $this->tg_bot;

			// init messages
			$this->errormsg = $magehelper->__("Something went wrong, please try again.");
			$this->cancelmsg = $magehelper->__("Ok, canceled.");
			array_push($this->positivemsg, $magehelper->__("Ok"), $magehelper->__("Okay"), $magehelper->__("Cool"), $magehelper->__("Awesome"));
			// $this->positivemsg[array_rand($this->positivemsg)]

			// init commands
			$this->start_cmd = "/start";
			$this->listacateg_cmd = $this->validateTelegramCmd("/" . $chatdata->getCommandString(1));
			$this->search_cmd = $this->validateTelegramCmd("/" . $chatdata->getCommandString(2));
			$this->login_cmd = $this->validateTelegramCmd("/" . $chatdata->getCommandString(3));
			$this->listorders_cmd = $this->validateTelegramCmd("/" . $chatdata->getCommandString(4));
			$this->reorder_cmd = $this->validateTelegramCmd("/" . $chatdata->getCommandString(5));
			$this->add2cart_cmd = $this->validateTelegramCmd("/" . $chatdata->getCommandString(6));
			$this->checkout_cmd = $this->validateTelegramCmd("/" . $chatdata->getCommandString(7));
			$this->clearcart_cmd = $this->validateTelegramCmd("/" . $chatdata->getCommandString(8));
			$this->trackorder_cmd = $this->validateTelegramCmd("/" . $chatdata->getCommandString(9));
			$this->support_cmd = $this->validateTelegramCmd("/" . $chatdata->getCommandString(10));
			$this->sendemail_cmd = $this->validateTelegramCmd("/" . $chatdata->getCommandString(11));
			$this->cancel_cmd = $this->validateTelegramCmd("/" . $chatdata->getCommandString(12));

			// TODO DEBUG COMMANDS
//			$temp_var = $this->start_cmd . " - " .
//				$this->listacateg_cmd . " - " .
//				$this->search_cmd . " - " .
//				$this->login_cmd . " - " .
//				$this->listorders_cmd . " - " .
//				$this->reorder_cmd . " - " .
//				$this->add2cart_cmd . " - " .
//				$this->checkout_cmd . " - " .
//				$this->clearcart_cmd . " - " .
//				$this->trackorder_cmd . " - " .
//				$this->support_cmd . " - " .
//				$this->sendemail_cmd;
//			$telegram->sendMessage(array('chat_id' => $chat_id, 'text' => $temp_var));
//			$telegram->sendMessage(array('chat_id' => $chat_id, 'text' => $chatdata->getTelegramConvState()));

			if (!is_null($text) && !is_null($chat_id))
			{

				// start command
				if ($text == $this->start_cmd)
				{
					if ($chatdata->getTelegramChatId()) // TODO
					{
						$message = Mage::getStoreConfig('chatbot_enable/telegram_config/telegram_help_msg'); // TODO
						$content = array('chat_id' => $chat_id, 'text' => $message);
						$telegram->sendMessage($content);

//						$data = array(
//							//'customer_id' => $customerId,
//							'telegram_chat_id' => $chat_id
//						); // data to be insert on database
//						$model = Mage::getModel('chatbot/chatdata')->load($chatdata->getId())->addData($data); // insert data on database
//						$model->setId($chatdata->getId())->save(); // save (duh)
					}
					else // if customer id isnt on our database, means that we need to insert his data
					{
						$message = Mage::getStoreConfig('chatbot_enable/telegram_config/telegram_welcome_msg'); // TODO
						$content = array('chat_id' => $chat_id, 'text' => $message);
						$telegram->sendMessage($content);
						try
						{
							$hash = substr(md5(uniqid($chat_id, true)), 0, 150);
							Mage::getModel('chatbot/chatdata') // using magento model to insert data into database the proper way
							->setTelegramChatId($chat_id)
								->setHashKey($hash) // TODO
								->save();
						}
						catch (Exception $e)
						{
							$telegram->sendMessage(array('chat_id' => $chat_id, 'text' => $this->errormsg)); // TODO
						}
					}
					return;
				}

				// cancel command
				if ($this->cancel_cmd && $text == $this->cancel_cmd) // TODO
				{
					if ($chatdata->getTelegramConvState() == $this->list_cat_state)
					{
						$keyb = $telegram->buildKeyBoardHide(true); // hide keyboard built on listing categories
						$telegram->sendMessage(array('chat_id' => $chat_id, 'reply_markup' => $keyb, 'text' => $this->cancelmsg));
					}
					else if ($chatdata->getTelegramConvState() == $this->support_state)
					{
						$telegram->sendMessage(array('chat_id' => $chat_id, 'text' => $this->positivemsg[array_rand($this->positivemsg)] . ", " . $magehelper->__("exiting support mode.")));
						$telegram->sendMessage(array('chat_id' => $chat_id, 'text' => $magehelper->__("Done.")));
					}

					if (!$chatdata->updateChatdata('telegram_conv_state', $this->start_state))
						$telegram->sendMessage(array('chat_id' => $chat_id, 'text' => $this->errormsg));
					return;
				}

				// add2cart commands
				if ($chatdata->checkCommand($text, $this->add2cart_cmd)) // && $chatdata->getTelegramConvState() == $this->list_prod_state TODO
				{
					$cmdvalue = $chatdata->getCommandValue($text, $this->add2cart_cmd);
					if ($cmdvalue)
					{
						if ($chatdata->addProd2Cart($cmdvalue))
							$telegram->sendMessage(array('chat_id' => $chat_id, 'text' => $magehelper->__("Added. To checkout, send") . " " . $chatdata->checkout_cmd));
						else
							$telegram->sendMessage(array('chat_id' => $chat_id, 'text' => $this->errormsg));
					}
					return;
				}

				// states
				if ($chatdata->getTelegramConvState() == $this->list_cat_state) // TODO show only in stock products
				{
					$_category = Mage::getModel('catalog/category')->loadByAttribute('name', $text);

					if ($_category)
					{
						$productIDs = $_category->getProductCollection()->getAllIds();
						if ($productIDs)
						{
							$keyb = $telegram->buildKeyBoardHide(true); // hide keyboard built on listing categories
							foreach ($productIDs as $productID)
							{
								$message = $this->prepareTelegramProdMessages($productID);
								$image = $this->loadImageContent($productID);
								if ($image)
									$telegram->sendPhoto(array('chat_id' => $chat_id, 'reply_markup' => $keyb, 'photo' => $image, 'caption' => $message));
								else
									$telegram->sendMessage(array('chat_id' => $chat_id, 'reply_markup' => $keyb, 'text' => $message));
							}
							if (!$chatdata->updateChatdata('telegram_conv_state', $this->list_prod_state))
								$telegram->sendMessage(array('chat_id' => $chat_id, 'text' => $this->errormsg));
						}
						else
							$telegram->sendMessage(array('chat_id' => $chat_id, 'text' => $magehelper->__("Sorry, no products found in this category.")));
					}
					else
						$telegram->sendMessage(array('chat_id' => $chat_id, 'text' => $this->errormsg));
					return;
				}
				else if ($chatdata->getTelegramConvState() == $this->search_state) // TODO
				{
					$productIDs = $this->getProductIdsBySearch($text);
					if (!$chatdata->updateChatdata('telegram_conv_state', $this->start_state))
						$telegram->sendMessage(array('chat_id' => $chat_id, 'text' => $this->errormsg));
					else if ($productIDs)
					{
						foreach ($productIDs as $productID)
						{
							$message = $this->prepareTelegramProdMessages($productID);
							$image = $this->loadImageContent($productID);
							if ($image)
								$telegram->sendPhoto(array('chat_id' => $chat_id, 'photo' => $image, 'caption' => $message));
							else
								$telegram->sendMessage(array('chat_id' => $chat_id, 'text' => $message));
						}
					}
					else
						$telegram->sendMessage(array('chat_id' => $chat_id, 'text' => $magehelper->__("Sorry, no products found for this criteria.")));

					return;
				}
				else if ($chatdata->getTelegramConvState() == $this->support_state)
				{
					$telegram->forwardMessage(array('chat_id' => $supportgroup, 'from_chat_id' => $chat_id, 'message_id' => $telegram->MessageID()));
					$telegram->sendMessage(array('chat_id' => $chat_id, 'text' => $this->positivemsg[array_rand($this->positivemsg)] . ", " . $magehelper->__("we have sent your message to support.")));
					return;
				}

				// commands
				if ($this->listacateg_cmd && $text == $this->listacateg_cmd)
				{
					$helper = Mage::helper('catalog/category');
					$categories = $helper->getStoreCategories();
					if (!$chatdata->updateChatdata('telegram_conv_state', $this->list_cat_state))
						$telegram->sendMessage(array('chat_id' => $chat_id, 'text' => $this->errormsg));
					else if ($categories)
					{
						$option = array();
						foreach ($categories as $_category) // TODO fix buttons max size
						{
							array_push($option, $_category->getName());
						}

						$keyb = $telegram->buildKeyBoard(array($option));
						$telegram->sendMessage(array('chat_id' => $chat_id, 'reply_markup' => $keyb, 'text' => $magehelper->__("Select a category")));
					}
					else
						$telegram->sendMessage(array('chat_id' => $chat_id, 'text' => $this->errormsg));
					return;
				}
				else if ($this->checkout_cmd && $text == $this->checkout_cmd) // TODO
				{
					if ($chatdata->getIsLogged() == "1")
					{
						// if user is set as logged, then login using magento singleton
						$customersssion = Mage::getSingleton('customer/session');
						$customersssion->loginById((int)$chatdata->getCustomerId());
						// then set current quote as customer quote
						$customer = Mage::getModel('customer/customer')->load((int)$chatdata->getCustomerId());
						$quote = Mage::getModel('sales/quote')->loadByCustomer($customer);
						// set quote and session ids from logged user
						$quoteId = $quote->getId();
						$sessionId = $customersssion->getEncryptedSessionId();
					}
					else
					{
						// set quote and session ids from chatbot class
						$sessionId = $chatdata->getSessionId();
						$quoteId = $chatdata->getQuoteId();
					}
					$emptycart = true;
					if ($sessionId && $quoteId)
					{
						$cartUrl = Mage::helper('checkout/cart')->getCartUrl();
						if (!isset(parse_url($cartUrl)['SID']))
							$cartUrl .= "?SID=" . $sessionId; // add session id to url

						$cart = Mage::getModel('checkout/cart')->setQuote(Mage::getModel('sales/quote')->loadByIdWithoutStore((int)$quoteId));
						$ordersubtotal = $cart->getQuote()->getSubtotal();
						if ($ordersubtotal > 0)
						{
							$emptycart = false;
							$message = $magehelper->__("Products on cart") . ":\n";
							foreach ($cart->getQuote()->getItemsCollection() as $item) // TODO
							{
								$message .= $item->getQty() . "x " . $item->getProduct()->getName() . "\n" .
									$magehelper->__("Price") . ": " . Mage::helper('core')->currency($item->getProduct()->getPrice(), true, false) . "\n\n";
							}
							$message .= $magehelper->__("Total") . ": " .
								Mage::helper('core')->currency($ordersubtotal, true, false) . "\n\n" .
								"[" . $magehelper->__("Checkout Here") . "](" . $cartUrl . ")";

							if (!$chatdata->updateChatdata('telegram_conv_state', $this->checkout_state))
								$telegram->sendMessage(array('chat_id' => $chat_id, 'text' => $this->errormsg));
							else
								$telegram->sendMessage(array('chat_id' => $chat_id, 'parse_mode' => 'Markdown', 'text' => $message));
						}
						else if(!$this->clearCart()) // try to clear cart
							$telegram->sendMessage(array('chat_id' => $chat_id, 'text' => $this->errormsg));
					}
					if ($emptycart)
						$telegram->sendMessage(array('chat_id' => $chat_id, 'text' => $magehelper->__("Your cart is empty.")));
					return;
				}
				else if ($this->clearcart_cmd && $text == $this->clearcart_cmd)
				{
					if ($chatdata->clearCart())
					{
						if (!$chatdata->updateChatdata('telegram_conv_state', $this->clear_cart_state))
							$telegram->sendMessage(array('chat_id' => $chat_id, 'text' => $this->errormsg));
						else
							$telegram->sendMessage(array('chat_id' => $chat_id, 'text' => $magehelper->__("Cart cleared.")));
					}
					return;
				}
				else if ($this->search_cmd && $text == $this->search_cmd)
				{
					if (!$chatdata->updateChatdata('telegram_conv_state', $this->search_state))
						$telegram->sendMessage(array('chat_id' => $chat_id, 'text' => $this->errormsg));
					else
						$telegram->sendMessage(array('chat_id' => $chat_id, 'text' => $this->positivemsg[array_rand($this->positivemsg)] . ", " . $magehelper->__("what do you want to search for?")));
					return;
				}
				else if ($this->login_cmd && $text == $this->login_cmd) // TODO
				{
					$hashlink = Mage::getUrl('chatbot/settings/index/') . "hash/" . $chatdata->getHashKey();
					if (!$chatdata->updateChatdata('telegram_conv_state', $this->login_state))
						$telegram->sendMessage(array('chat_id' => $chat_id, 'text' => $this->errormsg));
					else
						$telegram->sendMessage(array('chat_id' => $chat_id, 'text' => $magehelper->__("To login to your account, access this link") . ": " . $hashlink));
					return;
				}
				else if ($this->listorders_cmd && $text == $this->listorders_cmd) // TODO
				{
					if ($chatdata->getIsLogged() == "1")
					{
						$telegram->sendMessage(array('chat_id' => $chat_id, 'text' => $this->positivemsg[array_rand($this->positivemsg)] . ", " . $magehelper->__("let me fetch that for you.")));
						$ordersIDs = $chatdata->getOrdersIdsFromCustomer();
						foreach($ordersIDs as $orderID)
						{
							$message = $chatdata->prepareTelegramOrderMessages($orderID);
							$telegram->sendMessage(array('chat_id' => $chat_id, 'text' => $message));
						}
						if (!$chatdata->updateChatdata('telegram_conv_state', $this->list_orders_state))
							$telegram->sendMessage(array('chat_id' => $chat_id, 'text' => $this->errormsg));
					}
					else
						$telegram->sendMessage(array('chat_id' => $chat_id, 'text' => $magehelper->__("Please login first.")));
					return;
				}
				else if ($chatdata->checkCommand($text, $this->reorder_cmd)) // TODO
				{
					$errorflat = false;
					$cmdvalue = $chatdata->getCommandValue($text, $this->reorder_cmd);
					if ($cmdvalue)
					{
						if ($chatdata->clearCart())
						{
							$order = Mage::getModel('sales/order')->load($cmdvalue);
							if ($order)
							{
								foreach($order->getAllVisibleItems() as $item) {
									if (!$chatdata->addProd2Cart($item->getProductId()))
										$errorflat = true;
								}
							}
							else
								$errorflat = true;
						}
						else
							$errorflat = true;
					}
					else
						$errorflat = true;

					if ($errorflat)
						$telegram->sendMessage(array('chat_id' => $chat_id, 'text' => $this->errormsg));
					else if (!$chatdata->updateChatdata('telegram_conv_state', $this->reorder_state))
							$telegram->sendMessage(array('chat_id' => $chat_id, 'text' => $this->errormsg));
					else // success!!
						$telegram->sendMessage(array('chat_id' => $chat_id, 'text' => $this->positivemsg[array_rand($this->positivemsg)] . ", " . $magehelper->__("to checkout, send") . " " . $chatdata->checkout_cmd));
					return;
				}
				else if ($this->trackorder_cmd && $text == $this->trackorder_cmd) // TODO
				{
					if (!$chatdata->updateChatdata('telegram_conv_state', $this->track_order_state))
						$telegram->sendMessage(array('chat_id' => $chat_id, 'text' => $this->errormsg));
					return;
				}
				else if ($this->support_cmd && $text == $this->support_cmd) // TODO
				{
					if (!$chatdata->updateChatdata('telegram_conv_state', $this->support_state))
						$telegram->sendMessage(array('chat_id' => $chat_id, 'text' => $this->errormsg));
					else
						$telegram->sendMessage(array('chat_id' => $chat_id, 'text' => $this->positivemsg[array_rand($this->positivemsg)] . ", " . $magehelper->__("what do you need support for?")));
					return;
				}
				else if ($this->sendemail_cmd && $text == $this->sendemail_cmd) // TODO
				{
					if (!$chatdata->updateChatdata('telegram_conv_state', $this->send_email_state))
						$telegram->sendMessage(array('chat_id' => $chat_id, 'text' => $this->errormsg));
					return;
				}
				else
					$telegram->sendMessage(array('chat_id' => $chat_id, 'text' => $magehelper->__("Sorry, I didn't understand that."))); // TODO
			}
		}

		// FACEBOOK FUNCTIONS
		private function facebookHandler($apiKey)
		{

		}

		// WHATSAPP FUNCTIONS
		private function whatsappHandler($apiKey)
		{

		}

		// WECHAT FUNCTIONS (maybe)
//		private function wechatHandler($apiKey)
//		{
//
//		}
	}