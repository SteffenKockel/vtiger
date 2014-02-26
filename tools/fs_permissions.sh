
if [ -z $1 ]; then 
    echo "Usage: "$1" /path/to/vtiger"
    exit 1
fi

cd $1

#chown -R :www-data *
#chmod -R g-w *
#chmod -R g+x,g+r *
mkdir -p modules/Webmails/tmp
chown root:www-data modules/Webmails/tmp

chmod -R g+w config.inc.php \
tabdata.php \
install.php \
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
