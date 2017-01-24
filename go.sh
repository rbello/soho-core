#!/bin/sh

VERSION="4.0.31"

SCRIPT="`readlink -e $0`"
SCRIPT="`dirname $SCRIPT`"

# Mode debug
# En mode debug, la génération du JAVASCRIPT n'est pas compressée (packed)
DEBUG=true

case "$1" in

    clean-files)
        rm -rf src-php/system/lib/3rdParty
        mkdir src-php/system/lib/3rdParty
        rm -f composer.lock
        rm -f php_errors.log
        rm -rf src-php/data/cache/wsdl
        mkdir src-php/data/cache/wsdl
        rm -rf src-php/data/cache/sass
        mkdir src-php/data/cache/sass
        rm -rf src-php/www/css
        mkdir src-php/www/css
        echo "Nothing here..." > src-php/www/css/index.html
        rm -rf src-php/www/js
        mkdir src-php/www/js
        echo "Nothing here..." > src-php/www/js/index.html
        rm -f .htaccess
        ;;

    update)
        echo "Update dependencies..."
        composer update
        ;;

    setup)
    
        echo "Install required applications..."
        #gem install sass
        #gem install compass
        
        echo "Update server dependencies..."
        #composer update
        
        #echo "Create root .htaccess file..."
        #cp $SCRIPT/src-php/.htaccess $SCRIPT/.htaccess
        #BASE="\/src-php"
        #sed -i "s/ error.html/ $BASE\/www\/error.html/g" $SCRIPT/.htaccess
        #sed -i "s/ \/system\// $BASE\/system\//g" $SCRIPT/.htaccess
        
        echo "Generate CSS and JavaScripts..."
        #./go.sh compile
        
        #echo "Generate entities..."
        #php $SCRIPT/system/lib/doctrine/orm/bin/doctrine orm:generate-entities --regenerate-entities=true --verbose --generate-annotations=true -- system
        
        echo "Prepare SQL database..."
        #php $SCRIPT/system/lib/doctrine/orm/bin/doctrine orm:schema-tool:create
        ;;

    pkg)
        if [ "$2" = "reload" ]; then
            php $SCRIPT/src-php/system/app/soho.core/reload-context.php
        else
            php $SCRIPT/src-php/system/app/soho.core/print-context.php
        fi
        ;;

    data)
        if [ "$2" = "truncate" ]; then
            php $SCRIPT/system/install/truncate-db.php
        elif [ "$2" = "rebuild" ]; then
            # Drop and recreate database
            php $SCRIPT/system/install/rebuild-db.php
            # Setup tables
            php $SCRIPT/system/lib/doctrine/orm/bin/doctrine orm:schema-tool:create
            # Install data
            if [ ! -z "$3" ]; then
                ./go.sh data $3
            fi
        else
            echo "Install dataset '$2'..."
            php $SCRIPT/system/install/installer.php $2
        fi
        ;;

    mapinfo)
        echo "ORM mapping info for: $2"
        php $SCRIPT/system/lib/doctrine/orm/bin/doctrine orm:mapping:describe $2
        ;;

    cli)
        chmod +x $SCRIPT/src-php/system/lib/soho.core/cli.php
        $SCRIPT/src-php/system/lib/soho.core/cli.php $2 $3 $4 $5 $6 $7 $8 $9
        ;;

    css)
        if [ "$2" = "-watch" ]; then
            compass watch --trace
        else
            compass compile src-css/soho.scss
        fi
        ;;

    js)
        if [ "$2" = "-watch" ]; then
            php $SCRIPT/system/install/watch-js.php
        else
            cat src-js/core.js src-js/util.js src-js/security.js src-js/ui.js src-js/ui.view.js src-js/ui.trayicon.js src-js/live.js src-js/search.js  > build/WG-$VERSION.js
            echo "    write build/WG-$VERSION.js"
            java -jar src-js/yuicompressor.jar build/WG-$VERSION.js -o build/WG-$VERSION.pack.js
            echo "    write build/WG-$VERSION.pack.js"
    	    sed -i '1s;^;/**\n * JavaScript application for SoHo web application container\n * $Id: soho.js\n */\n/*global jQuery*/\n;' build/WG-$VERSION.pack.js
    	    if [ "$DEBUG" = true ] ; then
    		    cp build/WG-$VERSION.js src-php/system/packages/ui/js/soho.js
    		    echo "    copy -> src-php/www/js/soho.js (debug)"
    	    else
    		    cp build/WG-$VERSION.pack.js src-php/system/packages/ui/js/soho.js
    		    echo "    copy -> src-php/www/js/soho.js (production)"
    	    fi
        fi
        ;;

    compile)
        ./go.sh js
        ./go.sh css
        ;;

    *)
        echo "Usage: go.sh <option>"
        echo "Setup:"
        echo "   setup                   Setup environment"
        echo "   update                  Update library dependencies"
        echo "   clean-files             Clean up all temporary or generated files"
        echo "Packages"
        echo "   pkg                     Print current application context"
        echo "   pkg reload              Reload application context"
        echo "Generation:"
        echo "   css [-watch]            Generate CSS files"
        echo "   js [-watch]             Generate JAVASCRIPT files"
        echo "   compile                 Generate both CSS + JS files"
        echo "Tools:"
        echo "   cli <cmd>               Send a CLI command to the Soho instance"
        ;;
esac
