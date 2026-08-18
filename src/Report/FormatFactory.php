<?php

namespace Tuchsoft\MoodleChecklist\Report;

use ReflectionClass;
use Composer\Autoload\ClassLoader;
use Tuchsoft\MoodleChecklist\Report\Format\Base\FormatInterface;

class FormatFactory {
    protected static array $formats = [];
    protected static ?ClassLoader $autoloader = null;
    protected static bool $buildInLoaded = false;

    private static function getComposerAutoloader(): ClassLoader
    {
        if (self::$autoloader === null) {
            foreach (spl_autoload_functions() as $function) {
                if (is_array($function) && $function[0] instanceof ClassLoader) {
                    self::$autoloader = $function[0];
                    break;
                }
            }
        }

        if (self::$autoloader === null) {
            throw new \Exception('Composer autoloader not found.');
        }

        return self::$autoloader;
    }

    public static function register(string $fqnOrNamespace): void {
        if (class_exists($fqnOrNamespace)) {
            self::registerClass($fqnOrNamespace);
        } else {
            self::registerNamespace($fqnOrNamespace);
        }
    }

    protected static function registerClass(string $fqn): void {
        if (!is_subclass_of($fqn, FormatInterface::class)) {
            return;
        }

        $reflectionClass = new ReflectionClass($fqn);
        if ($reflectionClass->isAbstract()) {
            return;
        }

        $name = $fqn::getName();
        self::$formats[$name] = $fqn;
    }

    protected static function registerBuiltIn(): void {
        if (self::$buildInLoaded) return;
        self::$buildInLoaded = true;
        self::registerNamespace('Tuchsoft\\MoodleChecklist\\Report\\Format');
    }

    protected static function registerNamespace(string $namespace): void {
        $autoloader = self::getComposerAutoloader();
        $prefix = rtrim($namespace, '\\') . '\\';
        $paths = [];

        foreach ($autoloader->getPrefixesPsr4() as $ns => $dirs) {
            if ($ns === $prefix) {
                $paths = $dirs;
                break;
            }
        }

        if (empty($paths)) {
            // Namespace not found in Composer's autoloader configuration.
            return;
        }

        foreach ($paths as $path) {
            if (!is_dir($path)) {
                continue;
            }

            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));

            foreach ($files as $file) {
                if ($file->isDir() || $file->getExtension() !== 'php') {
                    continue;
                }

                $relativePath = substr($file->getPathname(), strlen($path) + 1);
                $className = str_replace(DIRECTORY_SEPARATOR, '\\', substr($relativePath, 0, -4));
                $fqn = $prefix . $className;

                if (class_exists($fqn)) {
                    self::registerClass($fqn);
                }
            }
        }
    }

    public static function createFormat(string $name, array $options): ?FormatInterface {
        self::registerBuiltIn();
        if (!isset(self::$formats[$name])) {
           throw new \Exception('Format ' . $name . ' does not exist.');
        }
        $fqn = self::$formats[$name];
        return new $fqn($options);
    }

    public static function getRegisteredFormats(): array {
        self::registerBuiltIn();
        return self::$formats;
    }
}