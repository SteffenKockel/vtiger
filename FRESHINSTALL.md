# Vtiger fresh install

## Install dependencies

On Ubuntu 12.4 you need the following packages. Very common and every distro should have similar packages.

<pre>
git php5-gd php5-adodb php-curl curl php5-dbg php5-pear unzip postfix
</pre>

Furthermore, one of the following webservers,
<pre>
apache2 
</pre>
**or**
<pre>
nginx php5-fpm
</pre>

MySQL

<pre>
mysql-server
php-mysql
</pre>

**or** PostgreSQL packages.
<pre>
postgresql
php5-pgsql
</pre>


## Clone the repo

You're on github. You will get it.

### Checkout a branch 

The `master` branch won't work here. If you plan an upgrade, check the branches named *`_migrate`. Or check this document for a section that matches your migration intentions. 

Migrations from version 5.x to 6.0.0 may be an often required task, which is covered here in two steps. The upgrade manual from vTiger states, that one should extract the patch into an existing 5.4 installation. This said, a migration from versions prior 5.4 should target version 5.4 before finally migrating to 6.0. This way it is painless. 

### Create a vhost

#### Apache

```
<Directory "/var/www/apps/vtiger/">
	
	Options -Indexes FollowSymLinks MultiViews
	AllowOverride None
	Order allow,deny
	Allow from all
	# DirectoryIndex index.php 

	php_admin_value max_execution_time 600
	php_admin_value memory_limit 256M
	php_admin_value upload_max_filesize 50M
	php_admin_value post_max_size 50M
	php_admin_value upload_tmp_dir "/tmp"
	php_admin_value include_path .:/usr/share/php:/usr/share/pear:/var/www/apps/vtiger/adodb/pear:/var/www/apps/vtiger/adodb
	php_admin_flag output_buffering on
	php_admin_flag allow_call_time_pass_reference on
	php_admin_flag short_open_tag on
	php_admin_flag expose_php off
	
	# Logging
#	php_flag  display_errors        on				# for debugging
#   php_admin_value error_reporting E_ALL	 		# 
#   php_value error_reporting       2039			# 
	php_admin_value error_log /var/log/apache2/vtiger.error.log

	# Session
	php_admin_value session.gc_maxlifetime 3600  	# increase session to 1 hour

</Directory>
```
####Nginx	

##### PHP-fpm

Copy `/etc/php5/fpm/pool.d/www.conf` to `/etc/php5/fpm/pool.d/vtiger.conf`. Customize the section header `[www]` to `[vtiger]`. Define either a different port or socket. Eg 

    listen = /var/run/vtiger-php5-fpm.sock

and add the following PHP-directives 
<pre>
php_value[newrelic.appname]= "vTiger"
php_admin_value[max_execution_time]=600
php_admin_value[memory_limit]=256M
php_admin_value[upload_max_filesize]=50M
php_admin_value[post_max_size]=50M
php_admin_value[upload_tmp_dir]=/tmp
php_admin_value[include_path]=.:/usr/share/php:/usr/share/pear:/var/www/apps/vtiger/adodb:/var/www/apps/vtiger
php_admin_flag[output_buffering]=on
php_admin_flag[short_open_tag]=on
php_flag[allow_call_time_pass_reference]=on
php_admin_flag[expose_php]=off

# Session
php_admin_value[date.timezone]=Europe/Berlin
php_admin_value[session.gc_maxlifetime]=3600			
php_admin_value[session.save_path]=/tmp
php_admin_value[session.save_handler]=files

# Logging
;php_admin_flag[show_errors]=on
;php_admin_flag[display_errors]=on
;php_admin_value[error_reporting]=E_ALL
php_admin_value[error_log]=/tmp/vtiger.php.error.log
php_admin_flag[log_errors]=on



</pre>

##### Vhost

<pre>

# still under construction. Fork and do better!

server {
        listen 127.80.80.80:80;
        server_name vtiger.dev;
        access_log /var/log/nginx/vtiger.access;
        error_log /var/log/nginx/vtiger.error;
        index index.php;
        root /var/www/apps/vtiger;

        location ~* ^.+\.(jpe?g|gif|png|ico|css|zip|tgz|gz|rar|bz2|doc|xls|exe|pdf|ppt|txt|tar|mid|midi|wav|bmp|rtf|js|swf|avi|mp3)$ {
                root            /var/www/apps/vtiger;
                expires         max;
        }

       location ~ \.php$ {
            alias                    /var/www/apps/vtiger;
            fastcgi_split_path_info  ^(.+\.php)(/.+)$;
            fastcgi_pass             unix:/var/run/vtiger-php5-fpm.sock;
            include                  fastcgi_params;
            fastcgi_index           index.php;
            try_files                $uri $uri/;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
            client_body_timeout      3000;
            fastcgi_read_timeout     3000;
            client_max_body_size     32m;
            fastcgi_buffers 8        128k;
            fastcgi_buffer_size      128k;
            send_timeout             600;
            keepalive_timeout        600;
            proxy_connect_timeout    600;
            proxy_send_timeout       600;
            proxy_read_timeout       600;
        }
}

</pre>

### Create a database

#### mysql

The following statements will create the database named `vtiger` and **drop** an exising one with the same name. Also, a corresponding user gets added.

<pre>
DROP DATABASE IF EXISTS vtiger;
CREATE DATABASE vtiger DEFAULT CHARACTER SET utf8 DEFAULT COLLATE utf8_general_ci;
GRANT ALL ON vtiger.* to 'vtiger'@'localhost' IDENTIFIED BY 'MySecretPassword';
</pre>

#### postgresql

The following statements will create the database named `vtiger` and **drop** an exising one with the same name. Also, a corresponding user gets added.

<pre>
DROP DATABASE IF EXISTS vtiger;
CREATE DATABASE vtiger;
CREATE ROLE vtiger WITH LOGIN PASSWORD 'MySecretPassword';
GRANT ALL DATABASE vtiger TO vtiger;
</pre>

### Run the web-installer

Actually, you will have to check your file permissions before. Checkout the `fs_permissions.sh` in the tools folder for help. You can safely ignore PHP warnings about error level and error format for PHP and continue the process. The run `fs_lock.sh` to tighten the security at least a bit. 
