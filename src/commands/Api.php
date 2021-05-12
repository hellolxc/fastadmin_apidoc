<?php

namespace thinkers\apidoc\commands;

use Exception;
use think\Config;
use think\console\Input;
use think\console\Output;
use think\console\Command;
use think\console\input\Option;
use app\admin\command\Api\library\Builder;

class Api extends Command
{
    /** @var array 文档配置文件 */
    protected $config;

    /** @var string 语言 */
    protected $language;
    
    /** @var Input 输入*/
    protected $input;

    /** @var string fastadmin api库位置 */
    protected $fastadminApidocDir = "/application/admin/command/Api";

    protected function configure()
    {
        $this
            ->setName('apidoc')
            ->addOption('url', 'u', Option::VALUE_OPTIONAL, 'default api url', '')
            ->addOption('module', 'm', Option::VALUE_OPTIONAL, 'module name(admin/index/api)', 'api')
            ->addOption('output', 'o', Option::VALUE_OPTIONAL, 'output index file name', 'api.html')
            ->addOption('template', 'e', Option::VALUE_OPTIONAL, '', 'index.html')
            ->addOption('force', 'f', Option::VALUE_OPTIONAL, 'force override general file', false)
            ->addOption('title', 't', Option::VALUE_OPTIONAL, 'document title', Config::get('site.name'))
            ->addOption('author', 'a', Option::VALUE_OPTIONAL, 'document author', Config::get('site.name'))
            ->addOption('class', 'c', Option::VALUE_OPTIONAL | Option::VALUE_IS_ARRAY, 'extend class', null)
            ->addOption('language', 'l', Option::VALUE_OPTIONAL, 'language', 'zh-cn')
            ->setDescription('Build Api document from project');
    }

    protected function execute(Input $input, Output $output)
    {
        $this->init();
        $this->input = $input;

        $builder = new Builder($this->getAllClass());
        $content = $builder->render($this->loadTemplateFile(), $this->getApiDocConfig());
        if (!file_put_contents($this->getOutputFilePath(), $content)) {
            throw new Exception('Cannot save the content to ' . $this->getOutputFilePath());
        }

        $output->info("Build Successed!");
    }

    /**
     * 获取所有类
     * @return array
     * @throws Exception
     */
    protected function getAllClass()
    {
        $classes = $this->getModuleDirClass();

        $extraClass = $this->input->getOption('class');

        $classes = array_merge($classes, $extraClass);

        foreach ($this->config['includeDir'] as $dir) {
            $dir = realpath(ROOT_PATH . $dir);
            $classes = array_merge($classes, $this->getClassByDir($dir));
        }

        //排除掉不需要的类
        return array_diff($classes, $this->config['excludeClass']);
    }

    /**
     * 获取模块下面所有的控制器类
     * @throws Exception
     */
    protected function getModuleDirClass()
    {
        $module = $this->input->getOption('module');
        $moduleDir = APP_PATH . $module . DS;
        if (!is_dir($moduleDir)) {
            throw new Exception('module not found');
        }
        $controllerDir = $moduleDir . Config::get('url_controller_layer') . DS;

        return $this->getClassByDir($controllerDir);
    }

    /**
     * 获取目录下所有的类
     * @param string $dir 目录
     * @return array
     * @throws Exception
     */
    protected function getClassByDir(string $dir)
    {
        if (is_dir($dir) === false) {
            throw new Exception('dir not found');
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        $classes = [];
        foreach ($files as $name => $file) {
            if (!$file->isDir() && $file->getExtension() == 'php') {
                $filePath  = $file->getRealPath();
                $classes[] = $this->getClassFromFile($filePath);
            }
        }

        return array_unique(array_filter($classes));
    }

    /**
     * 加载语言文件
     */
    protected function loadLanguageFile()
    {
        $langFile = $this->getFastadminApidocDir().DS. 'lang' . DS . $this->language . '.php';
        if (!is_file($langFile)) {
            throw new Exception('language file not found');
        }

        return include $langFile;
    }

    /**
     * 加载Api文档模板
     * @return string
     * @throws Exception
     */
    protected function loadTemplateFile()
    {
        $templateDir  = $this->getFastadminApidocDir() . DS . 'template' . DS;
        $templateFile = $templateDir . $this->input->getOption('template');
        
        if (is_file($templateFile) === false) {
            throw new Exception('template file not found');
        }
        
        return $templateFile;
    }

    /**
     * 获取api配置参数
     * @return array
     * @throws Exception
     */
    protected function getApiDocConfig()
    {
        $config = [
            'sitename'    => config('site.name'),
            'title'       => $this->input->getOption('title'),
            'author'      => $this->input->getOption('author'),
            'description' => '',
            'apiurl'      => $this->input->getOption('url'),
            'language'    => $this->language,
        ];

        return ['config' => $config, 'lang' => $this->loadLanguageFile()];
    }

   /**
     * 获取fastadmin api库目录位置
     * @return mixed
     * @throws Exception
     */
    protected function getFastadminApidocDir()
    {
        if (isset($this->config['fastadmin_apidoc_dir'])) {
            $dir = ROOT_PATH . $this->config['fastadmin_apidoc_dir'];
        } else {
            $dir = ROOT_PATH . $this->fastadminApidocDir;
        }

        if (is_dir($dir) === false) {
            throw new Exception('fastadmin_apidoc_dir not found');
        }

        return $dir;
    }

    /**
     * 获取Api文档路径
     * @return string
     */
    protected function getOutputFilePath()
    {
        return ROOT_PATH . 'public' . DS . $this->input->getOption('output');
    }

    protected function init()
    {
        //加载apidoc配置文件
        $this->config = config('apidoc');

        //语言文件
        $this->language = $this->input->getOption('language');
        $this->language = $this->language ?: 'zh-cn';

        //判断文档是否已存在
        $force = $this->input->getOption('force');
        if (is_file($this->getOutputFilePath()) && !$force) {
            throw new Exception("api index file already exists!\nIf you need to rebuild again, use the parameter --force=true ");
        }

        //如果PHP版本小于PHP7 则必须加载Zend OPcache扩展 详见help:https://forum.fastadmin.net/d/1321
        if (PHP_VERSION_ID < 70000) {
            if (extension_loaded('Zend OPcache') === false) {
                throw new Exception("Please make sure opcache already enabled, Get help:https://forum.fastadmin.net/d/1321");
            }

            $configuration = opcache_get_configuration();
            $directives    = $configuration['directives'];
            $configName    = request()->isCli() ? 'opcache.enable_cli' : 'opcache.enable';

            if (!$directives[$configName]) {
                throw new Exception("Please make sure {$configName} is turned on, Get help:https://forum.fastadmin.net/d/1321");
            }
        }
    }

    /**
     * get full qualified class name
     *
     * @param string $path_to_file
     * @return string
     * @author JBYRNE http://jarretbyrne.com/2015/06/197/
     */
    protected function getClassFromFile($path_to_file)
    {
        //Grab the contents of the file
        $contents = file_get_contents($path_to_file);

        //Start with a blank namespace and class
        $namespace = $class = "";

        //Set helper values to know that we have found the namespace/class token and need to collect the string values after them
        $getting_namespace = $getting_class = false;

        //Go through each token and evaluate it as necessary
        foreach (token_get_all($contents) as $token) {

            //If this token is the namespace declaring, then flag that the next tokens will be the namespace name
            if (is_array($token) && $token[0] == T_NAMESPACE) {
                $getting_namespace = true;
            }

            //If this token is the class declaring, then flag that the next tokens will be the class name
            if (is_array($token) && $token[0] == T_CLASS) {
                $getting_class = true;
            }

            //While we're grabbing the namespace name...
            if ($getting_namespace === true) {

                //If the token is a string or the namespace separator...
                if (is_array($token) && in_array($token[0], [T_STRING, T_NS_SEPARATOR])) {

                    //Append the token's value to the name of the namespace
                    $namespace .= $token[1];
                } elseif ($token === ';') {

                    //If the token is the semicolon, then we're done with the namespace declaration
                    $getting_namespace = false;
                }
            }

            //While we're grabbing the class name...
            if ($getting_class === true) {

                //If the token is a string, it's the name of the class
                if (is_array($token) && $token[0] == T_STRING) {

                    //Store the token's value as the class name
                    $class = $token[1];

                    //Got what we need, stope here
                    break;
                }
            }
        }

        //Build the fully-qualified class name and return it
        return $namespace ? $namespace . '\\' . $class : $class;
    }
}