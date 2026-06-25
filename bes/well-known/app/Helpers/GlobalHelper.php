<?php

namespace App\Helpers;

class GlobalHelper
{	
	public static function get_setting($key, $default = null) {
		return \DB::table('settings')->where('key', $key)->value('value') ?? $default;
	}
}