## Steps to Deploy a Laravel App Using Lightsail LAMP (PHP 8)

### 1. **Connect to your instance via SSH**

```bash
ssh -i your-ssh-key.pem bitnami@your-server-ip
```

### 2. **Update packages**

Update the package lists and upgrade the installed packages:

```bash
sudo apt update && sudo apt upgrade -y
```

### 3. **Clone the repository**

Clone your Laravel repository from GitHub:

```bash
git clone https://<your-pat>@github.com/HeshamNoaman/hekayti_dashboard.git
```

### 4. **Change directory ownership**

Set the correct ownership for the Laravel directory and its `storage` folder:

```bash
sudo chown $USER /home/bitnami/hekayti_dashboard
sudo chown -R daemon:daemon /home/bitnami/hekayti_dashboard/storage
```

### 5. **Install and update Composer dependencies**

Make sure your Laravel app has the necessary PHP dependencies installed:

```bash
composer install
composer update
```

### 6. **Forward port 80 to your local machine (for PhpMyAdmin access)**

#### Using SSH:

```bash
ssh -N -L 8888:127.0.0.1:80 -i your-ssh-key.pem bitnami@your-server-ip
```

#### Alternatively, using VS Code:

In VS Code, add the following configuration to your `~/.ssh/config` file:

```bash
Host hekayti_bitnami
  HostName your-server-ip
  IdentityFile D:/github_project/hekayti-resources/hekayti.pem
  User bitnami
  LocalForward 127.0.0.1:8888 127.0.0.1:80
```

This will forward port 80 from the server to port 8888 on your local machine.

### 7. **Access PhpMyAdmin**

In your browser, navigate to:

```
http://localhost:8888/phpmyadmin/
```

Log in with the following credentials:

- **Username**: `root`
- **Password**: Retrieve it from the Bitnami `bitnami_application_password` file.

### 8. **Create the database**

In PhpMyAdmin, create a new database:

- **Database Name**: `hekayti`
- **Collation**: `utf8mb4_unicode_ci`

### 9. **Run Laravel migrations**

Run the following Artisan command to migrate the database:

```bash
php artisan migrate
```

### 10. **Import database data**

Import the `database_data_only.sql` file into the newly created `hekayti` database in PhpMyAdmin.

### 11. **Copy and link storage files**

- Copy all images to the storage directory:

```bash
cp -R images/* storage/app/
```

- Create a symbolic link for storage:

```bash
php artisan storage:link
```

### 12. **Replace files in `public/storage/upload`**

- Copy the `upload.zip` file.
- Extract the contents in the `hekayti_dashboard/public/storage/upload` directory:

```bash
unzip upload.zip
```

### 13. **Set Up Apache for Laravel**

#### 13.1 **Edit Apache virtual host configuration**

Open the Apache virtual host configuration file:

```bash
sudo nano /opt/bitnami/apache/conf/vhosts/hekayti-vhost.conf
```

#### 13.2 **Configure the virtual host**

If you have SSL (for production):

```bash
<VirtualHost *:443>
    ServerName your-domain-or-subdomain
    SSLEngine on
    SSLCertificateFile "/opt/bitnami/apache/conf/bitnami/certs/server.crt"
    SSLCertificateKeyFile "/opt/bitnami/apache/conf/bitnami/certs/server.key"

    DocumentRoot "/home/bitnami/hekayti_dashboard/public"

    <Directory "/home/bitnami/hekayti_dashboard/public">
        DirectoryIndex index.php index.html
        Require all granted
        AllowOverride All
    </Directory>
</VirtualHost>

<VirtualHost *:80>
    ServerName your-domain-or-subdomain
    DocumentRoot "/home/bitnami/hekayti_dashboard/public"

    <Directory "/home/bitnami/hekayti_dashboard/public">
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog "logs/laravel-error_log"
    CustomLog "logs/laravel-access_log" common
</VirtualHost>
```

If you do **not** have SSL or a domain (for local development or testing):

```bash
<VirtualHost 127.0.0.1:80 _default_:80>
    ServerAlias *
    DocumentRoot /home/bitnami/hekayti_dashboard/public
    <Directory "/home/bitnami/hekayti_dashboard/public">
      Options -Indexes +FollowSymLinks -MultiViews
      AllowOverride All
      Require all granted
    </Directory>
</VirtualHost>

<VirtualHost 127.0.0.1:443 _default_:443>
    ServerAlias *
    DocumentRoot /home/bitnami/hekayti_dashboard/public
    SSLEngine on
    SSLCertificateFile "/opt/bitnami/apache/conf/bitnami/certs/server.crt"
    SSLCertificateKeyFile "/opt/bitnami/apache/conf/bitnami/certs/server.key"
    <Directory "/home/bitnami/hekayti_dashboard/public">
      Options -Indexes +FollowSymLinks -MultiViews
      AllowOverride All
      Require all granted
    </Directory>
</VirtualHost>
```

### 14. **Test Apache configuration for syntax errors**

```bash
apachectl configtest
```

### 15. **Restart Apache to apply changes**

```bash
sudo /opt/bitnami/ctlscript.sh restart apache
```

### 16. **Clear Laravel cache and optimize**

```bash
php artisan cache:clear
php artisan view:cache
php artisan optimize
```

### 17. **Fix file access errors**

If you encounter file access issues, ensure the `storage` folder has the correct ownership:

```bash
sudo chown -R daemon:daemon /home/bitnami/hekayti_dashboard/storage
```

### 18. **Access the site**

Visit the site using your server's IP address in the browser.

**Login Credentials:**

- **Email**: `mward@example.com`
- **Password**: `password`

----
----
- note: fix copying files problem

```bash
# First, temporarily change the ownership to bitnami user
sudo chown -R bitnami:daemon /home/bitnami/hekayti_dashboard/storage

# Now copy your files
# cp -R your_images/* storage/app/public/
cp -R ai_stories_tmp storage/app/public/

# After copying, restore the daemon ownership
sudo chown -R daemon:daemon /home/bitnami/hekayti_dashboard/storage
```

---
---

## steps to run the jobs in lightsail using supervisor

### 1. Install Supervisor if not installed:

```bash
sudo apt-get install supervisor -y
```

### 2. Run the following commands to set up queue tables
```bash
php artisan queue:table
php artisan migrate
```
- note: Make sure your laravel app is configured to use the correct queue driver in `.env` file
```bash
QUEUE_CONNECTION=database
```

### 3. Create a supervisor service to manage the queue:
Create a Supervisor configuration file:

```bash
sudo nano /etc/supervisor/conf.d/laravel-queue.conf
```

Add the following content to the file:

```bash
[program:laravel-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /home/bitnami/hekayti_dashboard/artisan queue:work --tries=3
autostart=true
autorestart=true
user=daemon
numprocs=1
redirect_stderr=true
stdout_logfile=/home/bitnami/hekayti_dashboard/storage/logs/queue.log
stopwaitsecs=3600
```

### 3. Restart Supervisor to apply the new configuration:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-queue
```

### 4. Verify the queue is running:

```bash
sudo supervisorctl status laravel-queue
```

### 5. Check the queue logs:

```bash
tail -f /home/bitnami/hekayti_dashboard/storage/logs/queue.log
```

## steps to run the jobs in lightsail using systemD

### 1. Create a systemd service file:

```bash
sudo nano /etc/systemd/system/laravel-queue.service
```

Add the following content to the file:

```bash
[Unit]
Description=Laravel Queue Worker
After=mysql.service

[Service]
User=bitnami
Group=daemon
WorkingDirectory=/home/bitnami/hekayti_dashboard
Environment="PATH=/opt/bitnami/php/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"
ExecStart=/opt/bitnami/php/bin/php /home/bitnami/hekayti_dashboard/artisan queue:work --tries=3
Restart=always

[Install]
WantedBy=multi-user.target
```

### 2. Enable and start the service:

```bash
sudo systemctl enable laravel-queue
sudo systemctl start laravel-queue
```

### 3. Verify the service is running:

```bash
sudo systemctl status laravel-queue
```

### 4. Check the queue logs:

```bash
journalctl -u laravel-queue -f
```

---

### Useful Links:
- [How to forward an SSH port in VSCode](https://stackoverflow.com/questions/74375282/how-to-forward-an-ssh-port-in-vscode-without-the-ssh-window)
- [Bitnami Laravel Setup Guide](https://docs.bitnami.com/general/infrastructure/lamp/get-started/use-laravel/)
