vTiger CRM
======

Yet another unofficial repo for the vTiger CRM

## Why?

I struggled a lot with vTiger upgrades and ended up trying vTigers SVN, which was a bad idea, because it is outdated! So checking out the 6.0.0 branch from there still left me with a 5.4.0 version.  

I have no intention to maintain something here, but I needed a possibility to rollback in my upgrade process. Since I spent a few hours figuring out this process, I thought someone might save some time upgrading a vTiger installation or creating a new one.

I have created a few scripts to make life easier. You can find them in the `tools` folder, that exists in 

## Fresh Install

### Install dependencies

On Ubuntu 12.4 you need the following packages. Very common and every distro should have similar packages.

<pre>
git php5-gd php5-adodb php-curl curl php5-dbg php5-pear unzip postfix
</pre>

Furthermore, one of the following webservers,
<pre>
apache2 nginx
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


### clone the repo

You're on github. You will get it.

#### checkout branch 

The `master` branch won't work here. If you plan an upgrade, check the branches named *`_migrate`. Or check this document for a section that matches your migration intentions. 

Migrations from version 5.x to 6.0.0 may be an often required task, which is covered here in two steps. The upgrade manual from vTiger states, that one should extract the patch into an existing 5.4 installation. This said, a migration from versions prior 5.4 should target version 5.4 before finally migrating to 6.0. This way it is painless. 

### Create a vhost
####apache
<pre>
</pre>
####nginx	
<pre>
</pre>

### Create a database

#### mysql

#### postgresql

### run web-installer


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

