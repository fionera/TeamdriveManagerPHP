# Teamdrive Manager (with extra Features)

## Needs
- PHP 7.2
- GSuite Account (With a ton of permissions)

### Google Setup
- Go to the Dev Console of Google (https://console.developers.google.com/)
- Create a new API Project
    - Name it as you want in this tutorial I name it "TeamdriveManager"
    - After its created select it
- Click on "Enable APIs"
    - Enable the Admin SDK
    - Enable the Identity and Access Management (IAM) API
    - Enable the Google Drive API
- Click on "Credentials"
    - "Create Credentials"
    - "Service Account Key"
    - Create a new Service Account
    - As name you should use "TeamdriveManager-Impersonate"
    - Dont select a Role
    - As Type select JSON
    - When asked say "Create without Role"
    - You will now download a JSON File. DONT LOSE THE JSON FILE!
- Click on "Manage Service Accounts"
    - click on the mail address of the Service Account
    - Click Edit in the Top
    - Click on "Show Domain-wide delegation"
    - Enable "Enable G Suite Domain-wide Delegation"
    - As Product name just use the Project name again
    - Press Save
    - copy the Client ID to some notepad.exe or so
- Go to the Admin Console (admin.google.com/YOURDOMAIN)
    - Go into "Security" (or use the search bar)
    - Select "Show more" and then "Advanced settings"
    - Select "Manage API client access" in the "Authentication" section
    - In the "Client Name" field enter the service account’s "Client ID"
    - In the next field, "One or More API Scopes", enter the following 
    - `https://www.googleapis.com/auth/admin.directory.group,https://www.googleapis.com/auth/cloud-platform,https://www.googleapis.com/auth/drive`

### PHP Setup
- Clone the Git Repo
- go into the Folder
- run composer
    - "composer install"
- copy the config.dist.php file to config.php
- edit the config file to your needs
    - put the downloaded JSON file in the same directory and change the name in the config to it
    - change the subject to your google account email
    - change the domain to your google account domain
    - put all users you want with their corresponding role in the users array
    - you can empty the Blacklist as you mostly dont need it
    - teamDriveNameBegin is the Prefix all your Drives that should be Managed have like "Fionera - "
    - The IAM Section is for Service Account Stuff and currently not needed
- Now you can use "php app.php assign:mail" for assigning all Email Addresses with their roles to the Teamdrives that match the prefix 
