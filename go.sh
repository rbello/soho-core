#!/bin/sh

SCRIPT="`readlink -e $0`"
SCRIPT="`dirname $SCRIPT`"

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
        ;;
        
    update)
        echo "Update dependencies..."
        composer update
        ;;
        
    install)
        echo "Install required applications...";
        gem install sass
        gem install compass
        echo "Update client dependencies...";
        #bower install ng-table
        bower install --save GoogleWebComponents/google-map
        echo "Update server dependencies..."
        composer update
        echo "Generate CSS and JavaScripts..."
        ./go.sh compile
        #echo "Generate entities..."
        #php $SCRIPT/system/lib/doctrine/orm/bin/doctrine orm:generate-entities --regenerate-entities=true --verbose --generate-annotations=true -- system
        echo "Prepare SQL database..."
        php $SCRIPT/system/lib/doctrine/orm/bin/doctrine orm:schema-tool:create
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
        chmod +x $SCRIPT/system/cli.php
        $SCRIPT/system/cli.php $2 $3 $4 $5 $6 $7 $8 $9
        ;;
        
    css)
        if [ "$2" = "-watch" ]; then
            compass watch --trace
        else
            compass compile
        fi
        ;;
        
    js)
        if [ "$2" = "-watch" ]; then
            php $SCRIPT/system/install/watch-js.php
        else
            cat www/js/utils.js www/js/main.js www/js/auth.js www/js/AppCtrl.js > www/res/ent.js
            echo "    write www/res/ent.js"
            java -jar system/lib/bin/yuicompressor.jar www/res/ent.js -o www/res/ent-min.js
            echo "    write www/res/ent-min.js"
        fi
        ;;
        
    compile)
        ./go.sh js
        ./go.sh css
        ;;
        
    *)
        echo "Usage: go.sh <option>"
        echo "Setup:"
        echo "   install                 Installation du système."
        echo "   update                  Mettre à jour les dépendances."
        echo "   clean-files             Nettoyer tous les fichiers propres à l'installation."
        echo "Data:"
        echo "   mapinfo <entity>        Affiche les informations de mapping de l'entité."
        echo "   data <dataset>          Installer les données samples."
        echo "   data truncate           Nettoyer toutes les données de la base."
        echo "   data rebuild [dataset]  Reconstruit la structure de la BDD à partir des entités."
        echo "Generation:"
        echo "   css [-watch]            Générer les fichiers CSS à partir des fichiers SASS."
        echo "   js [-watch]             Générer les fichiers JS minifiés."
        echo "   compile                 Genérer both CSS + JavaScripts."
        echo "Tools:"
        echo "   cli <cmd>               Executer une commande sur le système via l'interface CLI."
        ;;
esac