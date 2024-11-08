<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit68da0f0f54f3c57e0d3f45a0afd3ef11
{
    public static $files = array (
        '0e6d7bf4a5811bfa5cf40c5ccd6fae6a' => __DIR__ . '/..' . '/symfony/polyfill-mbstring/bootstrap.php',
        '5255c38a0faeba867671b61dfda6d864' => __DIR__ . '/..' . '/paragonie/random_compat/lib/random.php',
    );

    public static $prefixLengthsPsr4 = array (
        'S' => 
        array (
            'Symfony\\Polyfill\\Mbstring\\' => 26,
            'Symfony\\Component\\HttpFoundation\\' => 33,
        ),
        'B' => 
        array (
            'BKWPCommon\\' => 11,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Symfony\\Polyfill\\Mbstring\\' => 
        array (
            0 => __DIR__ . '/..' . '/symfony/polyfill-mbstring',
        ),
        'Symfony\\Component\\HttpFoundation\\' => 
        array (
            0 => __DIR__ . '/..' . '/symfony/http-foundation',
        ),
        'BKWPCommon\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $prefixesPsr0 = array (
        'B' => 
        array (
            'Buzz' => 
            array (
                0 => __DIR__ . '/..' . '/kriswallsmith/buzz/lib',
            ),
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit68da0f0f54f3c57e0d3f45a0afd3ef11::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit68da0f0f54f3c57e0d3f45a0afd3ef11::$prefixDirsPsr4;
            $loader->prefixesPsr0 = ComposerStaticInit68da0f0f54f3c57e0d3f45a0afd3ef11::$prefixesPsr0;

        }, null, ClassLoader::class);
    }
}
