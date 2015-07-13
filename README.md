# VirusTotalDaemon

## Required System Packages ##
1. mysql-common
2. mysql-server
2. php5-cli
3. curl
4. libexpat1-dev
5. php5-mysql

## Installation & Configuration ##
### General Configuration ###
All the important configuration options are defined in a file
called config.json ( config.default is an example file ).

The values themselves should be self explanatory, however note
that the apiKey for VirusTotal is available from your
virustotal.com account.

Make sure the MySQL username and password are correct for the MySQL
and that the server is available.

Be aware that the service script and the current config.json files
expect the installation location to be /var/vtd.  Changing that
location will requiring updating those references.

Finally, the vtd system user ( which the daemon runs as ) requires 
group read & write permissions on and under the /var/vtd directory.
You may apply those permissions by running:

    sudo chmod -R g+rw /var/vtd

### MySQL ###
A mysql server is required to store progress and results.
The scripts/data.sql file contains the required database / table
definitions and may be run in the following fashion to
initialize an empty database ( virustotal ) with a table ( jobs ). 

    mysql -u username -ppassword < scripts/data.sql 

Database backups can be made using the mysqldump command line utility.

### Service Configuration ###
The service script used to start & stop the daemon is located under
scripts/virus-total-daemon.  A copy of this script must be placed
under /etc/init.d/ with permissions set to 755.  For example:

    sudo cp scripts/virus-total-daemon /etc/init.d/virus-total-daemon && sudo chmod 755 /etc/init.d/virus-total-daemon

To install the service run:

    sudo update-rc.d virus-total-daemon defaults
    
To remove the service run:

    sudo update-rc.d virus-total-daemon remove

To manually start the service run:

    sudo service virus-total-daemon start

To manually stop the service run:

    sudo service virus-total-daemon stop

Additionally, the daemon is configured to run as a system user called 
vtd ( a non-root user for safety ).  Check the /etc/passwd file to see
if a definiton for the user exists.  If you need to create the user, it
should be a simple matter of running:

    sudo adduser vtd

## Running

The daemon runs completely headless, but the current task of the daemon
is continuously written to logs/application.log.  You can view what the
daemon is currently attempting to do by running:

    tail -f logs/application.log

Error logs are clearly marked under the logs directory.  None should exist,
but if one or more appear, please contact erikdunning@gmail.com if there
are any major questions.
