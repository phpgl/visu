#/bin/bash

# get dir of the current file
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# download phpdoc to the current directory
# only wget if the file does not exist
if [ ! -f $DIR/phpDocumentor.phar ]; then
    wget https://phpdoc.org/phpDocumentor.phar -O $DIR/phpDocumentor.phar
fi


# run phpdoc using config file
php $DIR/phpDocumentor.phar run -c $DIR/../phpdoc.xml