# General info
This document contains information on the Open State Foundation WordPress stack. There are 3 directories:

- `wp-content`, contains the WordPress user generated files (themes, plugins uploads)
- `docker`, contains configuration files for docker and scripts and crontabs for backing up the MySQL database
- `log`, contains Nginx logs, other logs can be retrieved using `docker-compose logs`

We use Docker to create Nginx, WordPress and MySQL containers.


# Installation
- `git clone git@github.com:openstate/wordpress.git`
- (optional) download MySQL backup and wp-content directories from the server

  Note: `--delete` means that files on your pc not on the remote are deleted
  ```  
  rsync -avzPh --delete Oxygen:/home/projects/wordpress/docker/docker-entrypoint-initdb.d/backups docker/docker-entrypoint-initdb.d
  cd wp-content
  rsync -avzPh --delete Oxygen:/home/projects/wordpress/wp-content .
  ```
- Custom plugins and themes can be cloned into their respective directories in `wp-content`
- Copy `docker/msmtprc.default` to `docker/msmtprc` and fill in the values for `host`, `user` and `password`
- In the `docker` directory create the files `secrets_db_name.txt`, `secrets_db_user.txt` and `secrets_db_password.txt` and add the database name, user and password (either new values if starting from scratch or existing if importing a database) on the first line of their corresponding file
- Copy `docker/backup.sh.default` to `docker/backup.sh` and change `<DB_USER>` and `<DB_PASSWORD>` to the same values as filled in above
- When importing an existing database (e.g. for local development or when migrating to another server)
  - Copy the latest MySQL backup from `docker/docker-entrypoint-initdb.d/backups` to `docker/docker-entrypoint-initdb.d` to import the database
- Production

  NOTE: when you import a database it may take a few minutes before it is finished and thus before WordPress will connect successfully, see the logs
  ```
  cd docker
  docker-compose up -d
  ```
- Development
  - (optional when not using nginx-load-balancer in development) Edit docker/docker-compose-dev.yml and uncomment the section under `nginx` listing the ports
  - If you want to enable debugging follow these steps
    - uncomment the lines containing `WORDPRESS_DEBUG` in `docker/docker-compose-dev.yml`
    - uncomment the debug sections in `Dockerfile_app`
    - in `docker/nginx/nginx.conf`, add `debug` to the end of the `error_log` statement in line 5
    - see the 'Useful commands' section below to display PHP error messages on webpages
    - in the `wordpress_app_1` container edit `wp-config.php` and replace `define( 'WP_DEBUG', false );` with the following lines (the output will be in `wp-content/debug.log`; really usefull to run every once in a while and visit all websites to find hidden errors and deprecations):

      ```
      define( 'WP_DEBUG', true );
      define( 'WP_DEBUG_LOG', true );
      define( 'WP_DEBUG_DISPLAY', false );
      ```

  
  NOTE: if you're developing on a Mac, edit the `sendfile` option in `docker/nginx/nginx.conf` as described there
  ```
  cd docker
  docker-compose -f docker-compose.yml -f docker-compose-dev.yml start
  ```
- When you just installed without importing an existing database
  - Run `docker-compose logs` and look for the line with 'GENERATED ROOT PASSWORD:' to find the randomly generated root database password and save it somewhere safe

- Retrieve the Nginx load balancer: http://github.com/openstate/nginx-load-balancer/
  - Follow the instructions to start Nginx load balancer
  - Development: see the section about mkcert in INSTALL_HTTPS.txt on how to obtain local certificates
  - Add the required website blocks in `nginx-load-balancer/docker/nginx/conf.d/default.conf`
  - run `./reload.sh` from `nginx-load-balancer/docker/`

- Development: make sure to edit your `hosts` file and add the domains you want to load locally (replace the `X` with the IP address found using `docker inspect wordpress_nginx_1`)
  ```
  172.17.0.X  openstate.eu
  172.17.0.X  www.openstate.eu
  ```


# Create backups
- To run manually
  ```
  cd docker
  sudo ./backup.sh
  ```
- To set a daily cronjob at 05:52
  - `sudo crontab -e` and add the following line (change the path below to your `wordpress/docker` directory path)
    ```
    52 5 * * * (cd <PATH_TO_wordpress/docker> && sudo ./backup.sh)
    ```


# Note on permissions
All files in this repository should be owned by `www-data:coders`. This allows the developers to edit the files, but also allows the files to be served.

To create a `coders` group and add yourself on your local machine:
```
addgroup coders
adduser <name> coders
```

To set the correct permissions run:
```
cd ..
sudo apt-get install acl
sudo setfacl -R -d -m group:coders:rwx wordpress/
sudo chown -R www-data:coders wordpress/
sudo find wordpress/ -type d -exec chmod 775 {} +
sudo find wordpress/ -type f -exec chmod 664 {} +
```


# Useful commands
- Enter a running container (by default, always use `exec` and never `attach` as you can kill the container when you press CTRL+D instead cleanly detaching using `CTRL+p CTRL+q`)

  After you entered, you can exit using CTRL+D
  ```
  docker exec -it <CONTAINER ID> bash
  ```

- Reload Nginx config, without disrupting the service (after you entered the container first of course)
  ```
  nginx -s reload
  ```

- Reload PHP-FPM config, without disrupting the service (after you entered the container first of course)

  Retrieve the PID of the PHP-FPM service using `ps aux`, normally this should just be 1
  ```
  kill -USR2 <PID>
  ```

- To display PHP error messages on webpages

  Enter the `wordpress_app_1` container
  ```
  sed -i 's/;php_flag\[display_errors\] = off/php_flag[display_errors] = on/' /usr/local/etc/php-fpm.d/www.conf
  kill -USR2 1
  ```

  To change it back
  sed -i 's/php_flag\[display_errors\] = on/;php_flag[display_errors] = off/' /usr/local/etc/php-fpm.d/www.conf

- To view all logs of all containers
  ```
  cd docker
  docker-compose logs -f --tail 100
  ```

- The entire log file of a container can be found here, where the two long strings should be replaced
by the ID of the container
  ```
  /var/lib/docker/containers/dbeed771e6b44017b84c944fd0845679f682269df812046c2f3e4d97185a9312/dbeed771e6b44017b84c944fd0845679f682269df812046c2f3e4d97185a9312-json.log

NOTE NORMALLY YOU JUST UPDATE WORDPRESS VIA ITS WEBINTERFACE mu.openstate.eu/wp-admin INSTEAD OF USING ALL THE STEPS BELOW. These steps are required though if you want to change `wp-config.php`.
- Update WordPress using Docker
  ```
  docker-compose build --pull app
  docker-compose down
  docker volume rm wordpress_app_volume
  docker-compose up -d
  ```
  NB: `wordpress_app_volume` needs to be removed because the WordPress Docker image only regenerates its files (and thus its version) if it doesn't detect its files (which are the only files that are stored in this volume and is thus safe to remove)

- Update PHP
  ```
  docker-compose build --pull app
  docker-compose up -d app
  ```

- Update Nginx
  ```
  docker-compose build --pull nginx
  docker-compose up -d nginx
  ```

- Update MySQL
  ```
  docker-compose pull mysql
  docker-compose up -d mysql
  ```

- Enter MySQL database
  ```
  docker run -it --rm --network wordpress_network mysql bash
  mysql -h wordpress_mysql_1 -u <DB_USER> -p
  ```
  
  Retrieve DB_USER from `wordpress/docker/secrets_db_user.txt` and database password from `wordpress/docker/secrets_db_password.txt`
