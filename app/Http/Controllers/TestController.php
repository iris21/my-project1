<?php

namespace App\Http\Controllers;

use App\Http\Controllers;
use App\Http\Requests;
//use Illuminate\Http\Request;
use Request;


class TestController extends BaseController
{

    public function test()
    {
        //$aaa = "1";

        //$aaa = Input::get('aaa');
        $aaa = Request::input('aaa');

        if(!isset($aaa)) {
            $aaa = "aaaa";
        }
        return view('test', compact('aaa'));
    }
}
