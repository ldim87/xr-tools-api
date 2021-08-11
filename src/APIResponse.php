<?php

/**
 * @author Oleg Isaev (PandCar)
 * @contacts vk.com/id50416641, t.me/pandcar, github.com/pandcar
 */

namespace XrTools;

class APIResponse extends \Exception
{
	private $message_data;

	/**
	 * Json encodes the message and calls the parent constructor.
	 *
	 * @param null           $message
	 * @param int            $code
	 * @param \Exception|null $previous
	 */
	public function __construct($message = "", $code = 0, \Exception $previous = null)
	{
		if(is_array($message)){
			$this->message_data = $message;
			$message = 'See message data via getData() method';
		} else {
			$this->message_data = null;
		}

		parent::__construct($message, $code, $previous);
	}

	/**
	 * Returns the json decoded message.
	 *
	 * @param bool $assoc
	 *
	 * @return mixed
	 */
	public function getData()
	{
		return $this->message_data ?? $this->getMessage();
	}
}
