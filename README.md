# Teamdrive Manager (with extra Features)
## Setup
 - Download
 - ```composer install```
 - Copy config.dist.php to config.php
 - Edit config.php to your needs
 
 You need the following APIs enabled for your API Client:
 - https://www.googleapis.com/auth/admin.directory.group 
 - https://www.googleapis.com/auth/cloud-platform 
 - https://www.googleapis.com/auth/drive 
 
## Usage
### Assigning directly to the TD
 - ```php app.php assign:mail```
### Assigning over Groups
 - ```php app.php assign:group```
### Teamdrive creation
 - ```php app.php td:create```
### ServiceAccount creation
 - ```php app.php iam:serviceaccount:create```