<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitcac430917c25abc5ae38432b8babe806
{
    public static $files = array (
        '7b11c4dc42b3b3023073cb14e519683c' => __DIR__ . '/..' . '/ralouphie/getallheaders/src/getallheaders.php',
        'c964ee0ededf28c96ebd9db5099ef910' => __DIR__ . '/..' . '/guzzlehttp/promises/src/functions_include.php',
        'a0edc8309cc5e1d60e3047b5df6b7052' => __DIR__ . '/..' . '/guzzlehttp/psr7/src/functions_include.php',
        '37a3dc5111fe8f707ab4c132ef1dbc62' => __DIR__ . '/..' . '/guzzlehttp/guzzle/src/functions_include.php',
        '841780ea2e1d6545ea3a253239d59c05' => __DIR__ . '/..' . '/qiniu/php-sdk/src/Qiniu/functions.php',
    );

    public static $prefixLengthsPsr4 = array (
        'W' => 
        array (
            'Workerman\\' => 10,
            'Wechat\\' => 7,
        ),
        'S' => 
        array (
            'Symfony\\Component\\OptionsResolver\\' => 34,
        ),
        'Q' => 
        array (
            'Qiniu\\' => 6,
        ),
        'P' => 
        array (
            'Psr\\Http\\Message\\' => 17,
            'Pay\\' => 4,
        ),
        'O' => 
        array (
            'Overtrue\\Pinyin\\' => 16,
            'OSS\\' => 4,
        ),
        'G' => 
        array (
            'GuzzleHttp\\Psr7\\' => 16,
            'GuzzleHttp\\Promise\\' => 19,
            'GuzzleHttp\\' => 11,
            'GatewayWorker\\' => 14,
            'GatewayClient\\' => 14,
        ),
        'F' => 
        array (
            'Flc\\Dysms\\' => 10,
            'Flc\\Alidayu\\' => 12,
        ),
        'E' => 
        array (
            'Endroid\\QrCode\\' => 15,
        ),
        'A' => 
        array (
            'AliyunMNS\\' => 10,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Workerman\\' => 
        array (
            0 => __DIR__ . '/..' . '/workerman/workerman',
            1 => __DIR__ . '/..' . '/workerman/workerman-for-win',
        ),
        'Wechat\\' => 
        array (
            0 => __DIR__ . '/..' . '/zoujingli/wechat-php-sdk/Wechat',
        ),
        'Symfony\\Component\\OptionsResolver\\' => 
        array (
            0 => __DIR__ . '/..' . '/symfony/options-resolver',
        ),
        'Qiniu\\' => 
        array (
            0 => __DIR__ . '/..' . '/qiniu/php-sdk/src/Qiniu',
        ),
        'Psr\\Http\\Message\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/http-message/src',
        ),
        'Pay\\' => 
        array (
            0 => __DIR__ . '/..' . '/zoujingli/pay-php-sdk/src',
        ),
        'Overtrue\\Pinyin\\' => 
        array (
            0 => __DIR__ . '/..' . '/overtrue/pinyin/src',
        ),
        'OSS\\' => 
        array (
            0 => __DIR__ . '/..' . '/aliyuncs/oss-sdk-php/src/OSS',
        ),
        'GuzzleHttp\\Psr7\\' => 
        array (
            0 => __DIR__ . '/..' . '/guzzlehttp/psr7/src',
        ),
        'GuzzleHttp\\Promise\\' => 
        array (
            0 => __DIR__ . '/..' . '/guzzlehttp/promises/src',
        ),
        'GuzzleHttp\\' => 
        array (
            0 => __DIR__ . '/..' . '/guzzlehttp/guzzle/src',
        ),
        'GatewayWorker\\' => 
        array (
            0 => __DIR__ . '/..' . '/workerman/gateway-worker/src',
            1 => __DIR__ . '/..' . '/workerman/gateway-worker-for-win/src',
        ),
        'GatewayClient\\' => 
        array (
            0 => __DIR__ . '/..' . '/workerman/gatewayclient',
        ),
        'Flc\\Dysms\\' => 
        array (
            0 => __DIR__ . '/..' . '/flc/dysms/src',
        ),
        'Flc\\Alidayu\\' => 
        array (
            0 => __DIR__ . '/..' . '/flc/alidayu/src/Alidayu',
        ),
        'Endroid\\QrCode\\' => 
        array (
            0 => __DIR__ . '/..' . '/endroid/qrcode/src',
        ),
        'AliyunMNS\\' => 
        array (
            0 => __DIR__ . '/..' . '/aliyun/aliyun-mns-php-sdk/AliyunMNS',
        ),
    );

    public static $prefixesPsr0 = array (
        'P' => 
        array (
            'PHPExcel' => 
            array (
                0 => __DIR__ . '/..' . '/phpoffice/phpexcel/Classes',
            ),
        ),
    );

    public static $classMap = array (
        'EasyPeasyICS' => __DIR__ . '/..' . '/phpmailer/phpmailer/extras/EasyPeasyICS.php',
        'PHPMailer' => __DIR__ . '/..' . '/phpmailer/phpmailer/class.phpmailer.php',
        'PHPMailerOAuth' => __DIR__ . '/..' . '/phpmailer/phpmailer/class.phpmaileroauth.php',
        'PHPMailerOAuthGoogle' => __DIR__ . '/..' . '/phpmailer/phpmailer/class.phpmaileroauthgoogle.php',
        'POP3' => __DIR__ . '/..' . '/phpmailer/phpmailer/class.pop3.php',
        'SMTP' => __DIR__ . '/..' . '/phpmailer/phpmailer/class.smtp.php',
        'ntlm_sasl_client_class' => __DIR__ . '/..' . '/phpmailer/phpmailer/extras/ntlm_sasl_client.php',
        'phpmailerException' => __DIR__ . '/..' . '/phpmailer/phpmailer/class.phpmailer.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitcac430917c25abc5ae38432b8babe806::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitcac430917c25abc5ae38432b8babe806::$prefixDirsPsr4;
            $loader->prefixesPsr0 = ComposerStaticInitcac430917c25abc5ae38432b8babe806::$prefixesPsr0;
            $loader->classMap = ComposerStaticInitcac430917c25abc5ae38432b8babe806::$classMap;

        }, null, ClassLoader::class);
    }
}
