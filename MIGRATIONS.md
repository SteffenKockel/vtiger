# Migration

## 5.1.0 to 5.4.0

* Backup. Do it!
* clone repo into a new installation dir
* change vhost to new installation dir
  * optional: dump db and create a new
* run web-installer and chose "migrate"

## 5.4.0 to 6.0.0

The installer won't migrate as prior installer did. If you just extract the new source into your existing installation, the migration will fail. 

During Upgrade from 5.4.0 to 6.0.0 the installer extracts all the new files from a zip archive. Therefore the whole Directory must be writeable for the webserver. A generous 

    chown -R :www-data /var/www/apps/vtiger
    
plus

    chmod -R g+w /var/www/apps/vtiger

will do. Cleanup this mess after the migration by running

<pre>
cd /var/www/apps 
chown root:root vtiger
chmod 750 vtiger
cd vtiger
find -type d | xargs chmod 750
find -type f | xargs chmod 740
</pre>

Then correct the permissions on the needed pathes listed in the end of this document.

#### given you are on git

* Backup. Do it!
* delete any existing migration folders
* merge the `6.0.0_migrate` branch in your existing installation
* run the web installer


## General file permissions

The following files need to be writable for the webserver user in order to run vTiger CRM. 

<pre>
config.inc.php \				# really? The editor seems to be dead in V 5.4+
tabdata.php \
parent_tabdata.php \
jscalendar \
cache \
cache/images \
cache/import \
logs \
storage \
user_privileges \
Smarty/cache \
Smarty/templates_c \
Smarty/templates/modules \
modules/Emails/templates \
modules cron/modules \
test/vtlib backup \
test/templates_c \
test/wordtemplatedownload \
test/product \
test/user \
test/contact \
test/logo \
modules/Webmails/tmp
</pre>
