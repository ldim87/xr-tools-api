<?php

/**
 * @author Oleg Isaev (PandCar)
 * @contacts vk.com/id50416641, t.me/pandcar, github.com/pandcar
 */

namespace XrTools;

use Exception;

class Signature
{
	protected string $secretKey;
	protected string $algo = 'sha256';

	/**
	 * @param string $secretKey
	 * @throws Exception
	 */
	function __construct(
		string $secretKey
	){
		if (empty($secretKey)) {
			throw new Exception('Secret key not specified');
		}

		$this->secretKey = $secretKey;
	}

	/**
	 * @param string $algo
	 * @return $this
	 */
	function setAlgo(string $algo): self
	{
		$new = clone $this;

		$new->algo = $algo;

		return $new;
	}

	/**
	 * @param string $string
	 * @return string
	 */
	function get(string $string): string
	{
		return $this->generateToken($string, 'now');
	}

	/**
	 * @param string $string
	 * @param string $hash
	 * @return bool
	 */
	function check(string $string, string $hash): bool
	{
		$times = [
			'now',
			'-1 day'
		];

		// Проверяем токены за 2 дня
		foreach ($times as $time)
		{
			$new_hash = $this->generateToken($string, $time);

			if ($hash == $new_hash) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string|int|float|array|bool|null $val
	 * @return string
	 */
	function encode(string|int|float|array|bool|null $val): string
	{
		$str = base64_encode(
			json_encode($val)
		);

		return $this->get($str).':'.$str;
	}

	/**
	 * @param string $val
	 * @return string|int|float|array|bool|null
	 */
	function decode(string $val): string|int|float|array|bool|null
	{
		$exp = explode(':', $val);

		if (count($exp) != 2) {
			return null;
		}

		if (! $this->check($exp[1], $exp[0])) {
			return null;
		}

		$data = json_decode(
			base64_decode($exp[1]),
			true
		);

		if (json_last_error() != JSON_ERROR_NONE) {
			return null;
		}

		return $data;
	}

	/**
	 * @param string $string
	 * @param string $time
	 * @return string
	 */
	protected function generateToken(string $string, string $time): string
	{
		return hash($this->algo, date('dmY', strtotime($time)).'_'.sha1($this->secretKey.'_'.$string));
	}
}
