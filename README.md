vTiger CRM
======

Yet another unofficial repo for the vTiger CRM

## Why?

I struggled a lot with vTiger upgrades and ended up trying vTigers SVN, which was a bad idea, because it is outdated! So checking out the 6.0.0 branch from there still left me with a 5.4.0 version.  

I have no intention to maintain something here, but I needed a possibility to rollback in my upgrade process. Since I spent a few hours figuring out this process, I thought someone might save some time upgrading a vTiger installation or creating a new one.

I have created a few scripts to make life easier. You can find them in the `tools` folder, that exists in the master branch. It's meant to get checked out in your local branch to customize the toolset for your needs.

## Fresh Install

### Install dependencies

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


### Clone the repo

You're on github. You will get it.

#### Checkout a branch 

The `master` branch won't work here. If you plan an upgrade, check the branches named *`_migrate`. Or check this document for a section that matches your migration intentions. 

Migrations from version 5.x to 6.0.0 may be an often required task, which is covered here in two steps. The upgrade manual from vTiger states, that one should extract the patch into an existing 5.4 installation. This said, a migration from versions prior 5.4 should target version 5.4 before finally migrating to 6.0. This way it is painless. 

### Create a vhost
####Apache
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
	php_admin_value error_reporting E_ALL


	php_admin_value upload_tmp_dir "/tmp"
	php_admin_value sendmail_path "/tmp"
	php_admin_value include_path .:/usr/share/php:/usr/share/pear:/var/www/apps/vtiger/adodb/pear:/var/www/apps/vtiger/adodb
	php_admin_value error_log /var/log/apache2/vtiger.error.log
	php_admin_flag output_buffering on
	php_admin_flag allow_call_time_pass_reference on
	php_admin_flag short_open_tag on
	php_admin_flag expose_php off
#	php_flag  display_errors        on			# for debugging
#   php_value error_reporting       2039		# for debugging

</Directory>
```
####Nginx	

<pre>

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

## Migration

### 5.1.0 to 5.4.0

* Backup. Do it!
* clone repo into a new installation dir
* change vhost to new installation dir
  * optional: dump db and create a new
* run web-installer and chose "migrate"

### 5.4.0 to 6.0.0

#### given you are on git
* Backup. Do it!
* checkout `6.0.0_migrate` branch in your existing installation
* run the web installer

