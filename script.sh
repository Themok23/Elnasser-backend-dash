#!/bin/bash
(crontab -l | grep -v "/usr/bin/php /Applications/MAMP/htdocs/Backend-ALNASSER/artisan dm:disbursement") | crontab -

(crontab -l | grep -v "/usr/bin/php /Applications/MAMP/htdocs/Backend-ALNASSER/artisan store:disbursement") | crontab -

