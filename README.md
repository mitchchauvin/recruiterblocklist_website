# The Recruiter Block List (Website)

The recruiter block list website and API! www.recruiterblocklist.com

## How to check out the website for development purposes
1. Install XAMPP (or similar).

2. Check out the git repo of the website into your XAMPP's installation directory, under xampp/htdocs directory.
  $ git clone https://github.com/mitchchauvin/recruiterblocklist_website.git rbl
  
3. Get an reCaptcha from Google.
  https://www.google.com/recaptcha
  
4. Open the recruiter block list website's main index page with a text editor and find the "sitekey" for "recaptchaWidgetId1".
  gedit <REPO>/publix/index.html
  
5. Replace the value of the site key with the public site key you get from Google's reCaptcha website. Then save and close the index.html file.

6. Replace the values defined for the following constants in the <REPO>/config/db_config.php file with your own values.
  - DB_HOST = 'localhost'
  - DB_NAME = 'recruiterblocklist'
  - DB_USER = 'root'
  - DB_PASS = ''
  - RECAPTCHA_PRIVATE_KEY = '<SECRET_KEY_FROM_GOOGLE>'
  
  7. Open your XAMPP application/control panel.
  
  8. Click to start for MySQL.
  
  9. Click "admin" for MySQL to open PHP admin.
  
  10. In the MySQL PHP admin web page, create a database called "recruiterblocklist".
  
  11. 
