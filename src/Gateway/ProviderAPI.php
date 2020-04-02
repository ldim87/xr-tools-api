<?php

/**
 * @author Oleg Isaev (PandCar)
 * @contacts vk.com/id50416641, t.me/pandcar, github.com/pandcar
 */

namespace XrTools\Gateway;

use \XrTools\FileControllerInterface;
use \XrTools\RouterMini;
use \XrTools\Utils;
use \XrTools\APIResponse;
use \XrTools\APIError;

class ProviderAPI
{
	/**
	 * @var RouterMini
	 */
	protected $rm;

	/**
	 * @var Utils
	 */
	protected $utils;

	/**
	 * @var string
	 */
	protected $secretKey;

	/**
	 * GatewayServer constructor.
	 * @param RouterMini $rm
	 * @param Utils $utils
	 * @param string $secretKey
	 * @throws \Exception
	 */
	public function __construct(
		RouterMini $rm,
		Utils $utils,
		string $secretKey
	){
		$this->rm = $rm;
		$this->utils = $utils;

		if (empty($secretKey)) {
			throw new \Exception('Secret key not specified');
		}

		$this->secretKey = $secretKey;
	}

	/**
	 * @param FileControllerInterface $controller
	 * @param string $path
	 * @param array $opt
	 */
	public function init(
		FileControllerInterface $controller,
		string $path,
		array $opt = []
	){
		$debug = ! empty($opt['debug']);

		$return = [
			'status'   => false,
			'message'  => '',
			'response' => null,
		];

		try {
			if (empty($_COOKIE['gateway_key']) || $_COOKIE['gateway_key'] != $this->secretKey) {
				throw new APIError('Access closed');
			}

			if (empty($_COOKIE['gateway_name'])) {
				throw new APIError('Missing name');
			}

			$list = $this->rm->getHandlerPath(
				$this->rm->getGo(),
				$path,
				'gateway_api',
				[
					'list' => true,
				]
			);

			if (empty($list)) {
				throw new APIError('Method not specified or not found');
			}

			// Массив данных
			$cont = [];

			foreach ($list as $item)
			{
				echo $controller->getFile($item, [], [
					'debug' => $debug,
					'cont'  => &$cont,
				]);
			}
		}
		catch (APIResponse $e) {
			$return['status'] = true;
			$return['response'] = $e->getData();
		}
		catch (APIError $e) {
			$return['message'] = $e->getMessage();
		}

		// Вывод json или отладка
		if ($debug) {
			echo $this->utils->arrays()->print($return);
		} else {
			$this->utils->strings()->echoJson($return, true);
		}
	}
}
