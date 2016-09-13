<?php
/**
 * Created by IntelliJ IDEA.
 * User: maxim
 * Date: 7/1/16
 * Time: 1:29 PM
 */

namespace api\helpers;


class Result
{
    public static $errorcodes = array(
        'generic' => array(
            'forbidden' => 403,
            'not_found' => 404,
            'db_error' => 410,
            'geo_required' => 420,
            'input_required' => 430,
            'already_exists' => 440,
            'error_sending_email' => 450,
        ),
        'user' => array(
            'invalid_login_method' => 1001,
            'invalid_login' => 1002,
            'invalid_nickname' => 1003,
            'invalid_email' => 1004,
            'user_email_exists' => 1005,
            'invalid_sns' => 1011,
            'invalid_sns_login' => 1012,
            'db_error' => 1101,
        ),
        'pic' => array(
            'invalid_file_type' => 2001,
            'invalid_dimensions' => 2002,
        ),
    );
    public static $errormsg = array(
        'invalid email' => ['user', 'invalid_email'],
        'user nickname exists' => ['user', 'user_email_exists'],
        'user email exists' => ['user', 'user_email_exists'],
        'invalid login' => ['user', 'invalid_login'],
        'invalid login method' => ['user', 'invalid_login_method'],
        'forbidden' => ['generic', 'forbidden'],
        'Notes can have maximum 224 characters' => ['generic', 'forbidden'],
        'Invalid Url' => ['generic', 'forbidden'],
        'invalid sns login' => ['user', 'invalid_sns_login'],
        'user not found' => ['user', 'invalid_login'],
        'invalid token or password' => ['user', 'invalid_login'],
        'invalid token' => ['user', 'invalid_login'],
        'token expired' => ['user', 'invalid_login'],
        'no geolocation information' => ['generic', 'geo_required'],
        'airport not set' => ['generic', 'input_required'],
        'require name and destination' => ['generic', 'input_required'],
        'db error' => ['generic', 'db_error'],
        'invalid file type' => ['pic', 'invalid_file_type'],
        'invalid dimensions (must be at least 200x200)' => ['pic', 'invalid_dimensions'],
        'You have already added this trip to Your Favorite List' => ['generic', 'forbidden'],
        'Can\'t heart your own trip' => ['generic', 'forbidden'],
        'require name and address' => ['generic', 'input_required'],
        'not found' => ['generic', 'not_found'],
        'please provide user parameter' => ['generic', 'forbidden'],
        'user not found ' => ['generic', 'forbidden'],
        'Day name can have maximum 50 characters' => ['generic', 'forbidden'],
        'invalid category' => ['generic', 'forbidden'],
        'error sending e-mail' => ['generic', 'error_sending_email'],
        'invite_code required' => ['generic', 'input_required'],
        'no airport information' => ['generic', 'geo_required'],
        'trip not set' => ['generic', 'input_required'],
        'no geolocation information for trip' => ['generic','geo_required'],
        'fetching-faires-failed'=>['generic','not_found']
    );

    public static function Error($msg = '')
    {
        $return = ['success' => 0];
        $return['msg'] = $msg;
        if (isset(self::$errormsg[$msg]) && $key = self::$errormsg[$msg]) {
            $return['error'] = self::$errorcodes[$key[0]][$key[1]];
        }
        if (!$msg) {
            unset($return['msg']);
        }
        return $return;

    }

    public static function Success(array $success = [])
    {
        $success['success'] = 1;
        return $success;
    }

}