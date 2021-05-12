### 安装
> composer require thinkers/fastadmin_apidoc

### 介绍
基于fastadmin api文档生成代码基础上修改，保留了全部的原生命令 新增以下功能
- 指定任意目录生成文档
- 排除指定的类的不生成文档

### 命令行
```bash
php think apidoc
```

### 配置
配置文件位于 extra/apidoc.php

```php
[
    "includeDir" => [//需要生成文档的目录 填写项目根目录的相对路径
        '/addons/test/controller/',
        '/application/admin/controller/',
    ],
    
    "excludeClass" => [//支持排除指定的类
        'addons\test\controller\Index',
        addons\test\controller\Index::class,
    ]
```

### 如何支持fastadmin后台插件一键生成文档
修改/application/admin/controller/Command.php文件 找到doexecute()方法 添加一下代码
```php
$commandName = "\\app\\admin\\command\\" . ucfirst($commandtype);
//在上面👆这一行后添加下面👇的代码 即可将命令替换为apidoc 一键生成文档就支持自定义目录 和 排除指定类啦 
if ($commandtype === "api") {
    $commandName = "\\thinkers\\apidoc\\commands\\". ucfirst($commandtype);
}
```




