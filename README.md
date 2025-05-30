# Typecho Says Plugin 🗨️

[![License](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Typecho](https://img.shields.io/badge/Typecho-1.2+-green.svg)](https://typecho.org)

> 在 Typecho 博客系统中实现类似微博的说说功能，支持API提交、后台管理和前端展示

将插件目录Says放入Typecho的插件目录即可使用

在要使用的页面上，添加：

```php
<?php \TypechoPlugin\Says\Plugin::render(10, '#says', '/memos/');?>
```
10为显示条数，#says为显示的容器，/memos/为API接口地址

后台菜单位置：后台 > 管理 > 说说

** 令牌一定要生成一个足够长的，可以用32位md5生成