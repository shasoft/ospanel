<?php

namespace Shasoft\OsPanel;

use Shasoft\Filesystem\Link;

trait TraitOsPanel
{
    static private ?int $version = null;
    static private ?string $home = null;
    static private string $projectIni = '/.osp/project.ini';
    static private string $public_html = 'public_html';
    static private string $timeOfChange = 'timeOfChange';
    static private ?array $options = null;
    static private ?array $hostsRunning = null;
    private array $domains = [];

    protected function osPanelHas(): bool
    {
        if (is_null(self::$version)) {
            self::$version = 0;
            if (getenv('OSP_ACTIVE_ENV') !== false) {
                $home = getenv('HOME');
                if (!empty($home)) {
                    $home = dirname($home);
                    $pathDomains = $home . '/home';
                    if (file_exists($pathDomains)) {
                        //-- OSPanel Version 6.0
                        self::$version = 6;
                        self::$home = $home;
                    }
                }
            }
        }
        return !empty(self::$version);
    }

    private function osPanelPath(?string $path = null): string
    {
        return self::$home . (empty($path) ? '' : ('/' . $path));
    }

    private function _load_ini(string $filepath): array|false
    {
        if (file_exists($filepath)) {
            return parse_ini_file($filepath, true, INI_SCANNER_RAW);
        }
        return false;
    }

    private function _mkdir(string $filepath): bool
    {
        $path = dirname($filepath);
        if (!file_exists($path)) {
            return mkdir($path, 0777, true);
        }
        return true;
    }

    private function _getTemplateHost(): string
    {
        //
        $tmp = explode("\\", str_replace("::", "\\", $this->toString()));
        $nameMethod = array_pop($tmp);
        if (str_starts_with($nameMethod, 'test')) {
            $nameMethod = substr($nameMethod, 4);
        }
        $nameClass = array_pop($tmp);
        if (str_ends_with($nameClass, 'Test')) {
            $nameClass = substr($nameClass, 0,  -4);
        }
        $prefix = hash('crc32', implode('\\', $tmp));
        //
        return strtolower($prefix . '-' . $nameClass . '-' . $nameMethod . '{i}.net');
    }

    private function _createLink(string $from, string $to): bool
    {
        $ret = false;
        if (is_link($to)) {
            @rmdir($to);
        }
        if (file_exists($from)) {
            $ret = symlink($from, $to);
        }
        return $ret;
    }

    protected function osPanelHostCreate(string $filepath): string
    {
        // Получить шаблон имени домена
        $templateHost = $this->_getTemplateHost();
        // Сформировать имя хоста на основе шаблона
        $host = str_replace('{i}', '', $templateHost);
        $index = 1;
        while (array_key_exists($host, $this->domains)) {
            $host = str_replace('{i}', ++$index, $templateHost);
        }
        $this->domains[$host] = $filepath;
        //
        $this->osPanelHas();
        //
        switch (self::$version) {
            case 6: {
                    if (is_null(self::$options)) {
                        // Определим параметры текущего профиля
                        $profileName = explode(' ', getenv('OSP_ACTIVE_ENV'))[0];
                        $filepathIni = $this->osPanelPath('home/' . $profileName . self::$projectIni);
                        foreach ($this->_load_ini($filepathIni) as $_options) {
                            self::$options = $_options;
                            break;
                        }
                    }
                    $filepathHostIni = $this->osPanelPath('home/' . $host . self::$projectIni);
                    //-- Определим текущие параметры
                    $hostsOptions = $this->_load_ini($filepathHostIni);
                    $hostOptionsOld = $this->_load_ini($filepathHostIni);
                    if ($hostsOptions === false) {
                        $hostOptionsOld = [];
                    } else {
                        $hostOptionsOld = $hostsOptions[$host] ?? [];
                    }
                    if (array_key_exists(self::$timeOfChange, $hostOptionsOld)) {
                        $timeOfChange = $hostOptionsOld[self::$timeOfChange];
                        unset($hostOptionsOld[self::$timeOfChange]);
                    } else {
                        $timeOfChange = time();
                    }
                    //-- Определим новое значение
                    $hostOptionsNew = self::$options;
                    $hostOptionsNew['filepath'] = $filepath;
                    $hostOptionsNew['public_dir'] = '{base_dir}\\' . self::$public_html;
                    // Проверить: были изменения?
                    $hasChange = false;
                    if (count($hostOptionsOld) != count($hostOptionsNew)) {
                        $hasChange = true;
                    } else {
                        foreach ($hostOptionsNew as $key => $value) {
                            if ($value != $hostOptionsOld[$key]) {
                                $hasChange = true;
                                break;
                            }
                        }
                    }
                    // Сохранить (если были изменения)
                    $hasChange = true;
                    if ($hasChange) {
                        // Установить время изменения
                        $hostOptionsNew[self::$timeOfChange] = $timeOfChange;
                        // Установить новое значение
                        $hostsOptions[$host] = $hostOptionsNew;
                        //
                        $filepathHostPublicHtml = $this->osPanelPath('home/' . $host . '/' . self::$public_html);
                        // Создать директорию
                        $this->_mkdir($filepathHostPublicHtml);
                        // Создать ссылку
                        $this->_createLink($hostOptionsNew['filepath'], $filepathHostPublicHtml);
                        // Сформировать Ini файл
                        $contentIni = '';
                        foreach ($hostsOptions as $_host => $_options) {
                            $contentIni .= "[$_host]" . PHP_EOL;
                            foreach ($_options as $key => $val) {
                                $contentIni .= "$key=$val" . PHP_EOL;
                            }
                            $contentIni .= PHP_EOL;
                        }
                        // Сохранить Ini файл
                        $this->_mkdir($filepathHostIni);
                        file_put_contents($filepathHostIni, $contentIni);
                    }
                }
                break;
        }
        //
        return $host;
    }

    private function osPanelGetUrlApi(string $command): string
    {
        // Определим ссылку для рестарта
        $ret = null;
        $contentBat = explode(' ', file_get_contents($this->osPanelPath('bin/osp.bat')));
        $findString = '/api/cmd/';
        foreach ($contentBat as $line) {
            $pos = strpos($line, $findString);
            if ($pos !== false) {
                $pos = strpos($line, '/', $pos + strlen($findString));
                $ret = substr($line, 0, $pos + 1);
                break;
            }
        }
        return $ret . $command;
    }

    protected function osPanelHostHas(string $host): bool
    {
        $ret = false;
        if ($this->osPanelHas()) {
            //== Определим список работающих доменов
            //-- Алгоритм № 1
            if (is_null(self::$hostsRunning)) {
                // http: //ospanel/getprojects - получить текущие проекты
                // http: //ospanel/getmodules                
                // 
                $program = $this->_load_ini($this->osPanelPath('config/program.ini'));
                $main = $program['main'] ?? [];
                $api_ip = $main['api_ip'] ?? null;
                if (!empty($api_ip)) {
                    $api_port = $main['api_port'] ?? 80;
                    $url = 'http://' . $api_ip . ':' . $api_port . '/getprojects';
                    $rc = file_get_contents($url);
                    if ($rc !== false) {
                        $rcJson = json_decode($rc, true);
                        $rcJson = array_filter($rcJson, function (array $options) {
                            return
                                strtolower($options['enabled']) == 'true'
                                &&
                                strtolower($options['defected']) == 'false';
                        });
                        //var_export($rcJson);
                        self::$hostsRunning = array_flip(array_keys($rcJson));
                    }
                }
            }
            //-- Алгоритм № 2
            if (is_null(self::$hostsRunning)) {
                // Если изменений не было, то проверим наличие домена 
                $all = file_get_contents($this->osPanelGetUrlApi('all'));
                if ($all !== false) {
                    if ($all !== false) {
                        $pos = strpos($all, '───');
                        if ($pos !== false) {
                            $pos = strpos($all, "\n", $pos);
                            if ($pos !== false) {
                                $all = substr($all, $pos);
                            }
                            $pos = strpos($all, '───');
                            if ($pos !== false) {
                                $all = substr($all, 0, $pos);
                                $pos = strrpos($all, "\n");
                                if ($pos !== false) {
                                    $all = substr($all, 0, $pos);
                                }
                                $pos = strrpos($all, "\n");
                                if ($pos !== false) {
                                    $all = substr($all, 0, $pos);
                                }
                            }
                        }
                    }
                    self::$hostsRunning = array_flip(array_filter(array_map(
                        function (string $line) {
                            $pos = strpos($line, ' ');
                            if ($pos !== false) {
                                $line = preg_replace("/ {2,}/", " ", $line);
                                $line = preg_replace("/\\e\\[\d{1,}m/", "", $line);
                                $line = str_replace("не задано", "НеЗадано", $line);
                                $tmp = array_values(array_filter(explode(" ", $line), function (string $line) {
                                    return !empty(trim($line));
                                }));
                                if (mb_strtolower($tmp[4]) == 'ошибка') {
                                    $line = '';
                                } else {
                                    $line = $tmp[0];
                                }
                            }
                            return trim($line);
                        },
                        explode("\n", $all)
                    ), function (string $line) {
                        return !empty($line);
                    }));
                }
            }
            // Файл Ini с параметрами домена
            $filepathHostIni = $this->osPanelPath('home/' . $host . self::$projectIni);
            //-- Определим текущие параметры
            $hostsOptions = $this->_load_ini($filepathHostIni);
            if (array_key_exists($host, $hostsOptions)) {
                //
                $hostOptions = $hostsOptions[$host];
                //
                if (array_key_exists($host, self::$hostsRunning)) {
                    $osPanelLock = $this->osPanelPath('temp/OSPanel.lock');
                    $timeOfCreation = filemtime($osPanelLock);

                    $timeOfChange = $hostOptions[self::$timeOfChange] ?? 0;
                    if ($timeOfChange < $timeOfCreation) {
                        $ret = true;
                    }
                    //echo $osPanelLock . PHP_EOL . $timeOfCreation . PHP_EOL . $timeOfChange . PHP_EOL . var_export($ret, true);
                }
                // Проверим что создана ссылка на папку
                $filepathHostPublicHtml = $this->osPanelPath('home/' . $host . '/' . self::$public_html);
                if (!file_exists($filepathHostPublicHtml)) {
                    $this->_createLink(
                        $hostOptions['filepath'],
                        $filepathHostPublicHtml
                    );
                }
            }
        }
        return $ret;
    }
}
