<?php

/**
 * @author Oleg Isaev (PandCar)
 * @contacts vk.com/id50416641, t.me/pandcar, github.com/pandcar
 */

namespace XrTools\Gateway;

use Exception;
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
	 * @var string
	 */
	protected $user_agent_prefix = 'Gateway client';
	/**
	 * @var array
	 */
	protected $localCache = [];
	/**
	 * @var bool
	 */
	protected $debug = false;

	/**
	 * Конструктор класса
	 * @param Utils $utils
	 * @param CacheManager $mc
	 * @param array $connectionParams
	 * @param string $serviceUse
	 * @param array $opt
	 * @throws Exception
	 */
	public function __construct(
		Utils $utils,
		CacheManager $mc,
		array $connectionParams,
		string $serviceUse,
		array $opt = []
	){
		$this->dbg = $utils->dbg();
		$this->mc = $mc;

		if (
			empty($connectionParams['host'])
			|| empty($connectionParams['client_name'])
			|| empty($connectionParams['secret_key'])
		){
			throw new Exception('Gateway Client: Params list is empty or invalid');
		}

		$this->params = $connectionParams;

		if (empty($serviceUse)) {
			throw new Exception('Gateway Client: Service use not specified');
		}

		$this->serviceUse = $serviceUse;

		if (isset($opt['debug'])) {
			$this->debug = !! $opt['debug'];
		}
	}

	/**
	 * @param array $opt
	 *  - path  Путь к api (по умолчанию gateway_api)
	 * @return ClientAPI
	 */
	public function api(array $opt = []): ClientAPI
	{
		if ($this->debug) {
			$opt['debug'] = true;
		}

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
	 * 	- debug       Отладка
	 * 	- post_build  Кодировать POST данные в строку (по умолчанию TRUE)
	 * 	- json        Декодировать из JSON
	 * @return mixed
	 */
	public function query( string $path, array $input = [], array $opt = [])
	{
		$debug = $opt['debug'] ?? $this->debug;

		$cache = ! empty($opt['cache']);
		$post_build = $opt['post_build'] ?? true;

		if (empty($path)) {
			if ($debug)
				$this->dbg->log('Request path not specified', __METHOD__);
			return false;
		}

		// Собираем структуру входных данных
		$input = $this->inputBuildAndMerge($input);

		if ($cache)
		{
			// Если ключ не задан
			$cacheKey = ! empty($opt['cache_key'])
				? $opt['cache_key']
				: 'gateway_'.$this->serviceUse.'_'.$path.'_'.md5( json_encode($input) );

			// Если время в секундах не задано, формируем сами
			$cacheSec = ! empty($opt['cache_time'])
				? $opt['cache_time']
				: 3600;

			$localCache = ! empty($opt['cache_local']);
			$loadFromLocal = false;

			$result = null;

			if ($localCache && isset($this->localCache[ $cacheKey ])) {
				$result = $this->localCache[ $cacheKey ];
				$loadFromLocal = true;
			} else {
				$result = $this->mc->get($cacheKey);
			}

			if ($localCache && ! isset($this->localCache[ $cacheKey ])) {
				$this->localCache[ $cacheKey ] = $result;
			}

			// Если данные успешно получены
			if ($result)
			{
				if ($debug) {
					$this->dbg->log('Data loaded from '.($loadFromLocal ? 'local ' : '').'cache, key: '.$cacheKey, __METHOD__);
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
		if (! empty($input['_get'])) {
			$url .= (substr_count($url, '?') == 0 ? '?' : '&') . http_build_query($input['_get'], '', '&');
		}

		// Добавляем данные для авторизации в системе gateway
		$input = $this->inputBuildAndMerge($input, [
			'_cookie' => [
				'gateway_name' => $this->params['client_name'],
				'gateway_key'  => $this->params['secret_key'],
			]
		]);

		// Создаём строковое представление
		$cookie = http_build_query($input['_cookie'], '', '; ');

		$post = $input['_post'];

		// Создаём строковое представление
		if ($post_build) {
			$post = http_build_query($post, '', '&');
		}

		$user_agent = "{$this->user_agent_prefix} {$this->params['client_name']}";
		$timeout_ms = $opt['timeout'] ?? $this->params['timeout'] ?? 10000;
		$connect_timeout_ms = $opt['connect_timeout'] ??  $this->params['connect_timeout'] ?? 200;

		// Формируем cURL запрос
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_ENCODING, '');
		curl_setopt($ch, CURLOPT_COOKIE, $cookie);
		curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
		curl_setopt($ch, CURLOPT_TIMEOUT_MS, $timeout_ms);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $connect_timeout_ms);

		if (! empty($post)) {
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		}

		if (! empty($this->params['disable_ssl_check'])) {
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
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
			$report = 'Address: '.$url_orig." <br>\n";

			if (! empty($input))
			{
				// Копируем
				$pr_input = $input;

				// Фильтруем пустые типы
				$pr_input = array_filter($pr_input, function ($item){
					return ! empty($item);
				});

				// Скрываем ключ в дебаге
				if (! empty($pr_input['_cookie']['gateway_key'])) {
					$pr_input['_cookie']['gateway_key'] = '*****';
				}

				$report .= print_r($pr_input, true)." <br>\n";
			}

			// Если ошибка
			if ($result === false) {
				$report .= 'Error: '.$error." <br>\n";
				$this->dbg->log($report, __METHOD__);
			}
			// Если успешно
			else {
				$report .= 'Response code: '.$info['http_code'].', Answer received:'." <br>\n".
					$result;
				$this->dbg->log($report, __METHOD__);
			}
		}

		if ($result === false) {
			return false;
		}

		// Кэшируем
		if ($cache)
		{
			$this->mc->set($cacheKey, $result, $cacheSec);

			if ($localCache) {
				$this->localCache[ $cacheKey ] = $result;
			}
		}

		// Обработка и возврат результата
		return $this->resultProcessing($result, $opt);
	}

	/**
	 * Создаёт структуру запроса и сливает со вторым если нужно
	 * @param array $input1
	 * @param array $input2
	 * @return array
	 */
	public function inputBuildAndMerge(array $input1 = [], array $input2 = []): array
	{
		// Если не выбран тип отправляемых данных то поумолчанию POST
		if (! isset($input1['_get']) && ! isset($input1['_post']) && ! isset($input1['_cookie'])) {
			$input1 = ['_post' => $input1];
		}

		if (! isset($input2['_get']) && ! isset($input2['_post']) && ! isset($input2['_cookie'])) {
			$input2 = ['_post' => $input2];
		}

		// Собираем и сливаем
		return [
			'_cookie' => array_merge($input1['_cookie'] ?? [], $input2['_cookie'] ?? []),
			'_get'    => array_merge($input1['_get'] ?? [], $input2['_get'] ?? []),
			'_post'   => array_merge($input1['_post'] ?? [], $input2['_post'] ?? []),
		];
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
