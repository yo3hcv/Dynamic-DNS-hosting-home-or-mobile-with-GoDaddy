Using home or mobile dynamic IP with your GoDaddy domain name
---------------------------------------------------

Let's suppose you have a domain name registered through GoDaddy service (http://godaddy.com) and you want to host your website at home, free, via your cable provider or even via your mobile phone connection. Fine :)  

But the problem is that most likely, your ISP will assign a dynamic IP address so basically your DNS records (from GoDaddy) will have to be updated every time your (home) router will reset itself or your GSM modem will connect to service.

Of course, you can either buy a static IP from your provider, use a dynamic DNS service (usually not for free), use some embedded DNS service from you home router (usually horrible slow or not working anymore because they are provided by manufacturers) or last resort... just edit manually your DNS records every time you need to!

What this script does basically automates all this process for you, meaning:
- check current server IP through several online services (like What's my IP services)
- double check with reverse DNS (actual every IP shall have one and this is assigned by your ISP)
- confirm current server IP with your DNS records (from GoDaddy)
- in case of changed, update those records
- send you a email report (using https://github.com/PHPMailer/PHPMailer)

Nice!

Actually this is posible due to API provided by GoDaddy but I'm positive that other domain name companies will have something similar too. Now this script was quick written in PHP and not optimized in any way... just works.

I use it on Linux, added in crontab for every minute but shall work on Windows too with minimum modifications (just install PHP first)

Find useful? You can donate some here. Thanks!

[![paypal](https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif)](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=7C3H5EVHXPPEA)

Usage
------

Install php and curl if they are not installed yet
Make this script executable

[code]chmode +x pip.php[/code]

On Windows, scrpit can be called by (anyhow, have installed php and curl):

php.exe pip.php

Install https://github.com/PHPMailer/PHPMailer and provide (in script) correct path to it

Fill in (the script) all your email delatils like: user, pass, server, etc

Insert into crontab by:

crontab -e

* /10 * * * * /root/pip.php    <-- every 10 minutes
* * * * * /root/pip.php   <-- every minute

Done!


