<?php

/**
 * @author Oleg Isaev (PandCar)
 * @contacts vk.com/id50416641, t.me/pandcar, github.com/pandcar
 */

namespace XrTools;

class APIResponse extends \Exception
{
	/**
	 * @var mixed
	 */
	private $data;

	/**
	 * Json encodes the message and calls the parent constructor.
	 * @param mixed $data
	 * @param int $code
	 * @param \Exception|null $previous
	 */
	public function __construct($data = null, $code = 0, \Exception $previous = null)
	{
		$this->data = $data;

		parent::__construct("See message data via getData() method", $code, $previous);
	}

	/**
	 * Returns the json decoded message.
	 * @return mixed
	 */
	public function getData()
	{
		return $this->data;
	}
}

