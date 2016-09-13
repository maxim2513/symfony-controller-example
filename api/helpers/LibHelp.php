<?php
/**
 * Created by IntelliJ IDEA.
 * User: maxim
 * Date: 7/13/16
 * Time: 1:15 PM
 */

namespace api\helpers;


class LibHelp
{
    public static function parseDataPost($var = '')
    {
        $param = \Yii::$app->request->post($var);
        return (isset($param) && !empty(trim($param))) ? trim($param) : null;
    }

    public static function validateDate($date, $format = 'Y-m-d')
    {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) == $date;
    }

    

}