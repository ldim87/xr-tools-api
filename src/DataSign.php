<?php

/**
 * @author Oleg Isaev (PandCar)
 * @contacts vk.com/id50416641, t.me/pandcar, github.com/pandcar
 */

namespace XrTools;

use JsonException;

class DataSign
{
	private string $algo = 'sha256';

	/**
	 * @param string $secretKey
	 */
	function __construct(
		private readonly string $secretKey
	){}

	/**
	 * @param string|int|array|bool|null $content
	 * @param int $expiry
	 * @param bool $unique
	 * @return string
	 */
	function encode(string|int|array|bool|null $content, int $expiry = 0, bool $unique = false): string
	{
		return $this->encodeByKey(null, $content, $expiry, $unique);
	}

	/**
	 * @param string $encrypted
	 * @return string|int|array|bool|null
	 */
	function decode(string $encrypted): string|int|array|bool|null
	{
		return $this->decodeByKey(null, $encrypted);
	}

	/**
	 * @param string|int|array|null $key
	 * @param mixed $content
	 * @param int $expiry
	 * @param bool $unique
	 * @return string
	 */
	function encodeByKey(string|int|array|null $key, mixed $content, int $expiry = 0, bool $unique = false): string
	{
		$data = [];

		if ($unique) {
			$data['u'] = mt_rand();
		}

		$data['t'] = $expiry ? time() + $expiry : 0;
		$data['c'] = $content;

		$base64 = base64_encode( json_encode($data));

		$hash = $this->getHash($key, $base64);

		return $hash.'.'.$base64;
	}

	/**
	 * @param string|int|array|null $key
	 * @param string $encrypted
	 * @return mixed
	 */
	function decodeByKey(string|int|array|null $key, string $encrypted): mixed
	{
		if (! $encrypted) {
			return false;
		}

		$exp = explode('.', $encrypted);

		if (count($exp) != 2) {
			return false;
		}

		$hash = $this->getHash($key, $exp[1]);

		if ($hash != $exp[0]) {
			return false;
		}

		$json = base64_decode($exp[1]);

		try {
			$data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			return false;
		}

		if (! isset($data['t']) || ! isset($data['c'])) {
			return false;
		}

		if ($data['t'] != 0 && $data['t'] < time()) {
			return false;
		}

		return $data['c'];
	}

	/**
	 * @param string|int|array|null $key
	 * @param string $base64
	 * @return string
	 */
	function getHash(string|int|array|null $key, string $base64): string
	{
		$val = [
			$this->secretKey,
			...((array) $key),
			$base64
		];

		return hash(
			$this->algo,
			json_encode($val, JSON_UNESCAPED_UNICODE)
		);
	}

	/**
	 * @param string $algo
	 * @return $this
	 */
	function forkAlgo(string $algo): self
	{
		$new = clone $this;
		$new->algo = $algo;

		return $new;
	}

	/**
	 * @return $this
	 */
	function forkMd5(): self
	{
		return $this->forkAlgo('md5');
	}

	/**
	 * @return $this
	 */
	function forkSha256(): self
	{
		return $this->forkAlgo('sha256');
	}
}

