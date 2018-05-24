# The Recruiter Block List (Website)

The recruiter block list is a open source website and API for blocking recruiter spam emails (and soon phone numbers).

The website is located here: www.recruiterblocklist.com

## How to contribute
There are two ways to contribute to the project:
 1. Users can improve the recruiter block list database by adding recruiter's information using the website www.recruiterblocklist.com.
 2. Developers can improve the website by checking out the website's repo and submitting a change.

## User contributions to the block list database
To improve the recruiter block list, simply go to www.recruiterblocklist.com and submit information about a recruiter.


## Developer contributions to the website
To help improve the recruiter block list website, simply check out the Git repo of the website, make an improvement, run/test your changes locally using XAMPP (or similar) and submit a pull request.

Repo location:
  - https://github.com/mitchchauvin/recruiterblocklist_website

### Minimum requirements for developers
 - XAMPP (or similar).
 - Git
 - Knowledge of JS, JQuery, PHP and some frameworks
 - Google reCaptcha public/private key
 
### Instructions for developers
1. Install XAMPP (or similar) to run/test your Recruiter Block List website changes locally.
 
2. Check out the git repo of the website into your XAMPP's installation directory, under xampp/htdocs directory.
  `$ git clone https://github.com/mitchchauvin/recruiterblocklist_website.git rbl`
  
3. Get an reCaptcha public/private key from Google. (Note: Needed for testing. Don't commit these).
  - https://www.google.com/recaptcha
  
4. Open the recruiter block list website's main index page with a text editor and find the "sitekey" for "recaptchaWidgetId1".
  gedit <REPO>/publix/index.html
  
5. Replace the value of the site key with the public site key you get from Google's reCaptcha website. Then save and close the index.html file.

6. Replace the values defined for the following constants in the <REPO>/config/db_config.php file with your own values.
  - DB_HOST = 'localhost'
  - DB_NAME = 'recruiterblocklist'
  - DB_USER = 'root'
  - DB_PASS = ''
  - RECAPTCHA_PRIVATE_KEY = '<YOUR_RECAPTCHA_SECRET_KEY'
  
7. Open your XAMPP application/control panel.
   1. Start your MySQL server by clicking "Start" next to "MySQL" on the XAMPP control panel.
   2. Open the phpMyAdmin panel by clicking "Admin" next to "MySQL" on the XAMPP control panel.
   3. In the phpMyAdmin web page, create a database called "recruiterblocklist".
  
8. Add a table called "domains".
   1. In table "domains" add columns "id" and "domain".
   2. Set column "id" to INT set to auto increment.
   3. Set colum "domain" to a VARCHAR of size 61.
    

