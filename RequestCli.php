<?php
// Cli 请求

class RequestCli extends Request {

	private $fake_get  = array();
	private $fake_post = array();

	//构造
	public function __construct($uri, $post_data) {
		$this->init(); // 初始化
		$this->method = empty($post_data) ? 'GET' : 'POST';
		$this->time   = time();
		$this->uri    = $uri;
		$this->format = 'text';
		$uri_info     = parse_url($uri);
		isset($uri_info['query']) && parse_str($uri_info['query'], $this->fake_get);
		$post_data && parse_str($post_data, $this->fake_post);
	}

	// http://php.net/cli
	private function init() {
		// 确保这些设置是正确的
		ini_set('max_execution_time', 0);
		ini_set('register_argc_argv', true);
		ini_set('implicit_flush', true);
		ini_set('html_errors', false);
		// attention!
		ini_set('memory_limit', '1024M');
		ob_implicit_flush(true);
		// attention!
		while (@ob_end_flush());
	}

	//获取Get数据（模拟的）
	public function get($key = null) {
		if (empty($key)) {
			return $this->fake_get;
		}
		return isset($this->fake_get[$key]) ? $this->fake_get[$key] : null;
	}

	//获取CLI POST数据（模拟的）
	public function post($key = null, $check_method = true) {
		if ($check_method && $this->method != 'POST') {
			throw new Exception("非POST请求方法");
		}
		if (empty($key)) {
			return $this->fake_post;
		}
		return isset($this->fake_post[$key]) ? $this->fake_post[$key] : null;
	}

}