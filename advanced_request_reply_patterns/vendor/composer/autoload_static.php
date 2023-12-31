<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitb18b73e16254ee3c9c07e50bb3e2a2b5
{
    public static $prefixLengthsPsr4 = array (
        'R' => 
        array (
            'Root\\AdvancedRequestReplyPatterns\\' => 34,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Root\\AdvancedRequestReplyPatterns\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitb18b73e16254ee3c9c07e50bb3e2a2b5::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitb18b73e16254ee3c9c07e50bb3e2a2b5::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInitb18b73e16254ee3c9c07e50bb3e2a2b5::$classMap;

        }, null, ClassLoader::class);
    }
}
