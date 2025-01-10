<?php

namespace Shasoft\OsPanel;


class OsPanelUtils
{
    static public function templateHost(string $classname, string $methodName): string
    {
        //
        if (str_starts_with($methodName, 'test')) {
            $methodName = substr($methodName, 4);
        }
        if (str_ends_with($classname, 'Test')) {
            $classname = substr($classname, 0,  -4);
        }
        $tmp = explode("\\", $classname);
        $class = array_pop($tmp);
        $prefix = hash('crc32', implode("\\", $tmp));
        //
        return strtolower($prefix . '-' . $class . '-' . $methodName . '{i}.net');
    }
}
