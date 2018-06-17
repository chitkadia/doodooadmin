# Saleshandy - Development with Slim3, Angular 4

To run the application, follow below given steps to setup your source code:

## Install dependencies of third party packages

```
$ apt-get install php-bcmath
```

## Run composer update

```
$ composer update
```

## Changes in `vendor` directory

Open `vendor/slim/pdo/src/PDO/Statement/StatementContainer.php` file

Find `getPlaceholders` function and comment statement 

`$this->placeholders = array();`

in the function and add following statement below it

`reset($this->placeholders);`

Find `setPlaceholders` function and add statement 

`$this->placeholders = array();`

right before the for loop at the begining of the function body

## Create supportive folders 

```
$ mkdir logs
$ mkdir cache
$ mkdir media
$ mkdir upload
```

## Give write permission to following folders

```
$ chmod 777 -R logs
$ chmod 777 -R cache
$ chmod 777 -R media
$ chmod 777 -R upload
```

## Set database configurations

Update database configurations in `src/database.php` file
