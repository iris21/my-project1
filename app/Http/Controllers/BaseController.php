<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller as Controller;
use Illuminate\Support\Facades\Request;

class BaseController extends Controller
{
	/**
	 * Setup the layout used by the controller.
	 *
	 * @return void
	 */
	/*
	public $shop_id;

	protected function setupLayout()
	{
		if ( ! is_null($this->layout))
		{
			$this->layout = View::make($this->layout);
		}
	}

	public function __construct()
	{
		$this->beforeFilter('csrf', array('on' => array('post', 'delete', 'put')));
		if(Auth::check())
			$this->shop_id = Auth::user()->shop_id;
	}
	*/
}
