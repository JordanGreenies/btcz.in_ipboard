# About IP.Board Nexus module
BTCz.in module for IP.Board Nexus with pingback


## Requirements
* Requires IP.Board v4.x and IP.Nexus already installed
* PHP 5.3 or later
* PHP-cURL
* A store currency listed here: https://api.fixer.io/latest?base=USD

## Installation
 1. Copy all files to the relative location
 2. Go to the database where your forum is installed (using phpmyadmin or other)
 3. Import bitcoinz_lang.sql to the database
 4. Inside admin panel goto [Commerce -> Payments -> Settings] -> [Payment Methods -> Create New]
 5. Enter all the information and test gateway

## Preview
#### Settings
![settings](https://i.imgur.com/h8WLpGZ.png)
#### Checkout Selection
![gateway](https://i.imgur.com/0DO8OVV.png)
#### Checkout iFrame
![checkout](https://i.imgur.com/mSvnLRb.png)
