cdn-cache
=========

PHP 实现的简易的代理服务器功能，主要用于对静态内容进行缓存。需与 SwitchySharp 之类的 proxy 管理插件配合使用才能更好地发挥作用。

#### 配置

在 Apache 中的配置是类似这样的：

	Listen localhost:8086
	<VirtualHost *:8086>
		ServerName localhost:8086
		DocumentRoot "D:/cdn-cache"
		<Location />
			Order Allow,Deny
			Allow from all
		</Location>
		DirectoryIndex agent.php
		RewriteEngine On
		RewriteRule ^/(.*)$  D:/cdn-cache/agent.php [NS,QSA,L]
	</VirtualHost>
