# mysql-schema
MySQL schema export


* Export table schemas as XSD (XML-Schema), HTML and CSV
* Optional append mapping columns
 
# URL Parameter

* format: html (default), csv
* mapping: 0 (default, 1

Example:

http://localhost/mysql-schema/?format=html&mapping=1  
http://localhost/mysql-schema/?format=csv&mapping=1  
http://localhost/mysql-schema/?format=csv&mapping=0  

# Setup

* Open config.php and change dsn parameter
