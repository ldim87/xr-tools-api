<?php

/**
 * @author Oleg Isaev (PandCar)
 * @contacts vk.com/id50416641, t.me/pandcar, github.com/pandcar
 */

namespace XrTools\Gateway;

use \XrTools\Utils;
use \XrTools\CacheManager;

class Client
{
	/**
	 * @var array Параметры для осуществления запросов
	 */
	protected $params = [];

	/**
	 * @var string Имя сервиса с которым работаем
	 */
	protected $serviceUse;

	/**
	 * @var Utils\DebugMessages
	 */
	protected $dbg;

	/**
	 * @var CacheManager
	 */
	protected $mc;

	/**
	 * Конструктор класса
	 * @param Utils $utils
	 * @param CacheManager $mc
	 * @param array $connectionParams
	 * @param string $serviceUse
	 * @throws \Exception
	 */
	public function __construct(
		Utils $utils,
		CacheManager $mc,
		array $connectionParams,
		string $serviceUse
	){
		$this->dbg = $utils->dbg();
		$this->mc = $mc;

		if (
			empty($connectionParams['host'])
			|| empty($connectionParams['client_name'])
			|| empty($connectionParams['secret_key'])
		){
			throw new \Exception('Params list is empty or invalid');
		}

		$this->params = $connectionParams;

		if (empty($serviceUse)) {
			throw new \Exception('Service use not specified');
		}

		$this->serviceUse = $serviceUse;
	}

	/**
	 * @param array $opt
	 *  - path  Путь к api (по умолчанию gateway_api)
	 * @return ClientAPI
	 */
	public function api(array $opt = [])
	{
		return new ClientAPI(
			$this->dbg, 
			$this, 
			$opt
		);
	}

	/**
	 * Запрос к сервисам
	 * @param  string  $path   Путь
	 * @param  array   $input  Данные (POST по умолчанию)
	 * @param  array   $opt    Опции
	 * 	- debug          Отладка
	 * 	- no_post_build  Не кодировать POST данные в строку
	 * 	- json           Декодировать из JSON
	 * @return mixed
	 */
	public function query( string $path, array $input = [], array $opt = [])
	{
		$debug = ! empty($opt['debug']);
		$cache = ! empty($opt['cache']);

		if (empty($path)) {
			if ($debug)
				$this->dbg->log('Request path not specified', __METHOD__);
			return false;
		}

		// Если не выбран тип отправляемых данных то поумолчанию POST
		if (! isset($input['_get']) && ! isset($input['_post']) && ! isset($input['_cookie']))
		{
			$input = [
				'_post' => $input,
			];
		}

		if ($cache)
		{
			// Если ключ не задан
			if (empty($opt['cache_key'])) {
				$opt['cache_key'] = 'gateway_'.$this->serviceUse.'_'.$path.'_'.md5( json_encode($input) );
			}

			// Если время в секундах не залано, формируем сами
			if (empty($opt['cache_time'])) {
				$opt['cache_time'] = 3600;
			}

			$result = $this->mc->get($opt['cache_key']);

			// Если данные успешно получены
			if ($result)
			{
				if ($debug) {
					$this->dbg->log('Data loaded from cache, key: '.$opt['cache_key'], __METHOD__);
				}

				// Обработка и возврат результата
				return $this->resultProcessing($result, $opt);
			}
		}

		$ch = curl_init();

		if (empty($ch)) {
			if ($debug)
				$this->dbg->log('Did not rise cURL', __METHOD__);
			return false;
		}

		$base_url = 'http://'.$this->params['host'].(! empty($this->params['port']) ? ':'.$this->params['port'] : null);

		// Определяем путь
		// :TODO: сделать более гибкими настройки
		if ($this->serviceUse == 'main') {
			$url = $base_url.'/ajax/'.$path;
		} else {
			$url = $base_url.'/api/'.$this->serviceUse.'/'.$path;
		}

		// Для отображения в отладке
		$url_orig = $url;

		// Добавляем параметры GET если переданы
		if (! empty($input['_get']) && is_array($input['_get'])) {
			$url .= (substr_count($url, '?') == 0 ? '?' : '&') . http_build_query($input['_get'], null, '&');
		}

		// Идентифицируем сервис
		$cookie = [
			'gateway_name' => $this->params['client_name'],
			'gateway_key'  => $this->params['secret_key'],
		];

		// Добавляем COOKIE если переданы
		if (! empty($input['_cookie']) && is_array($input['_cookie'])) {
			$cookie = array_merge($input['_cookie'], $cookie);
		}

		// Для отображения в отладке
		$input['_cookie'] = $cookie;

		$cookie = http_build_query($cookie, null, '; ');

		$post = $input['_post'] ?? [];

		// Собираем POST в строку если не запрещено
		if (empty($opt['no_post_build'])) {
			$post = http_build_query($post, null, '&');
		}

		$user_agent = '1000.menu Gateway service "'.$this->params['client_name'].'"';

		// Формируем cURL запрос
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_ENCODING, '');
		curl_setopt($ch, CURLOPT_TIMEOUT, 20);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
		curl_setopt($ch, CURLOPT_COOKIE, $cookie);
		curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);

		if (! empty($post)) {
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		}

		// Выполняем запрос
		$result = curl_exec($ch);

		$error = curl_error($ch);
		$info = curl_getinfo($ch);

		// Закрываем
		curl_close($ch);

		// Отладка
		if ($debug)
		{
			$report = 'Address: '.$url_orig."\n";

			if (! empty($input)) {
				$report .= print_r($input, true);
			}

			if ($result === false) {
				$report .= 'Error: '.$error."\n";
				$this->dbg->log($report, __METHOD__);
			}
			else {
				$report .= 'Response code: '.$info['http_code']."\n".
					'Answer received:'."\n".
					$result;
				$this->dbg->log($report, __METHOD__);
			}
		}

		if ($result === false) {
			return false;
		}

		// Кэшируем
		if ($cache) {
			$this->mc->set($opt['cache_key'], $result, $opt['cache_time']);
		}

		// Обработка и возврат результата
		return $this->resultProcessing($result, $opt);
	}

	/**
	 * Обработка результата
	 * @param string $result
	 * @param array  $opt
	 * @return mixed
	 */
	protected function resultProcessing(string $result, array $opt = [])
	{
		$debug = ! empty($opt['debug']);

		// Если ждём JSON то декодируем
		if (! empty($opt['json']))
		{
			$result = json_decode($result, true);

			if ( json_last_error() != JSON_ERROR_NONE )
			{
				if ($debug) {
					$this->dbg->log('JSON error: ' . json_last_error_msg(), __METHOD__);
				}

				return false;
			}
		}

		return $result;
	}
}
