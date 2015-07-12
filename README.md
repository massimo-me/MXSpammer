# MXSpammer
VBulletin Spammer for 4.X Version

#Require

- PHP 5.5
- php5-curl

# Install

Run composer install command

If you have composer globaly
```
composer install
```
or
```
curl -sS https://getcomposer.org/installer | php
./composer.phar install
```

#Run Spammer and have fun!!
````
./console spammer:vbulletin:post "http://vbulletinsite.com/" --username yourusername --password yourpassw --message example.txt
````

##More options

- `--last-topic` Your last topic
- `--post-title` Your post title

![Spammer](https://cloud.githubusercontent.com/assets/5167596/8638001/841ee0d0-28ab-11e5-9672-28dbaff792db.png)

