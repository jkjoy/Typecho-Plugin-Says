# Typecho Says Plugin 🗨️

[![License](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Typecho](https://img.shields.io/badge/Typecho-1.2+-green.svg)](https://typecho.org)

> 在 Typecho 博客系统中实现类似微博的说说功能，支持API提交、后台管理和前端展示

## 当前版本
V1.2.0

## 使用方法

将插件目录Says放入Typecho的插件目录即可使用

在要使用的页面上，添加：

```php
<?php \TypechoPlugin\Says\Plugin::render(10, '#says', '/memos/');?>
```
10为显示条数，#says为显示的容器，/memos/为API接口地址

如果你还有特殊的需求，还可以指定相关的样式和JS
```php
<?php \TypechoPlugin\Says\Plugin::render(10, '#says', '/memos/', ['css' => '', 'markdown' => '//mirrors.sustech.edu.cn/cdnjs/ajax/libs/marked/15.0.7/marked.min.js', 'js' => './says.js']);?>
```
这样配置，系统将会不加载默认样式，并加载公共的markd库和本地的JS文件

后台菜单位置：后台 > 管理 > 说说

** 令牌一定要生成一个足够长的，可以用32位md5生成

## 特别感谢

[Typecho - https://typecho.org](https://typecho.org)

[Marked - https://marked.js.org](https://marked.js.org)

[蚂蚱 - https://qiyu.pub/](https://qiyu.pub/)

## 版权所有
本项目遵循MIT协议，请自由使用。