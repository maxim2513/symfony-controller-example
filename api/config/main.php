<?php
$params = array_merge(
    require(__DIR__ . '/../../common/config/params.php'),
    require(__DIR__ . '/../../common/config/params-local.php'),
    require(__DIR__ . '/params.php'),
    require(__DIR__ . '/params-local.php')
);

return [
    'id' => 'app-api',
    'basePath' => dirname(__DIR__),
    'controllerNamespace' => 'api\controllers',
    'bootstrap' => ['log'],

    /*
    * This module section makes it easy to version the API section of code
    */

    'modules' => [
        'v1' => [
            'basePath' => '@app/modules/v1',
            'class' => 'api\modules\v1\Module'   // here is our v1 modules
        ],
    ],

    'components' => [

        'geolocation' => [
            'class' => 'rodzadra\geolocation\Geolocation',
            'config' => [
                'provider' => 'geoplugin',
                'return_formats' => 'php',
            ],
        ],
        'flickr' => [
            'class' => 'api\lib\Flickr',
            'api_key' => 'fcc815c52721d785fadd2c5934f50ce7',
            'path_upload' => '/upload/'
        ],

        'googleApi' => [
            'class' => 'api\lib\GoogleApi',
            // API Keys !!!
            'geocode_api_key' => 'AIzaSyDbbxol8SuW-BT-50vQ5pxBUkB3MfPxgzQ',
        ],
        'foursquare' => [
            'class' => 'api\lib\Foursquare',
            // API Keys !!!
            'config' => [
                'client_id' =>'TEIAHSQAJOAICZN3CJS3JV114YLOYXY44FQXJCR1YF3V0G4W',
                'client_secret' =>'CV3TNHVL2Y1HUQPUZRSTPYUIXGUSGNDSEAO5MIJIKD5VJQEC',
                'v' =>'20140723'
            ],
        ],
        'skyscanner'=>[
            'class' => 'api\lib\Skyscanner',
            // API Keys !!!
            'apiKey' => 'tr724056331748982315734715764532',
        ],

        'user' => [
            'identityClass' => 'api\modules\v1\models\User',
            'enableAutoLogin' => false,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        
        /*
        * Allows wrapping of server responses in an envelope if suppress_response_code is in the get params
        * Tip : When in DEBUG mode you will get extra data from exceptions/errors in the response. Change
        * `suppress_response_code` to what ever your trigger may be for it.
        */

        'response' => [
            'class' => 'yii\web\Response',
            'on beforeSend' => function ($event) {
                $response = $event->sender;
                if ($response->data !== null && Yii::$app->request->get('suppress_response_code')) {
                    $response->data = [
                        'success' => $response->isSuccessful,
                        'data' => $response->data,
                    ];
                    $response->statusCode = 200;
                }
            },
        ],

        /*
        * Use the default handler. If you want to override this behavior 
        * just point this to the action you want to handle the error.
        */

        'errorHandler' => [
            //           'errorAction' => 'site/error',	// action handler
        ],

        'urlManager' => [
            'enablePrettyUrl' => true,
            'enableStrictParsing' => true,
            'showScriptName' => false,
            'rules' => [
                [
                    'class' => 'yii\rest\UrlRule',
                    'controller' => 'v1/user',   // our simple rest api rule
                    'pluralize' => false
                ],
                [
                    'class' => 'yii\rest\UrlRule',
                    'controller' => 'v1/trip',   // our simple rest api rule
                    'pluralize' => false
                ],

                /*
                * route anything here into our versioned path of stuff...
                * this is typically for controllers that act on Get/Post params directly
                * and use the regular controller (not the rest version)
                * this route will try to match the following url structure -
                *
                * yoursitehere/api/v(version#)/(controller name)/(action name)
                */

                '<controller>/<action>' => 'v1/<controller>/<action>',
            ],
        ],

        'assetManager' => [

        ],
    ],
    'aliases' => [
        '@uploads' => '@appRoot/uploads',
        '@webapp'=>'localhost',
    ],
    'params' => $params,
];
