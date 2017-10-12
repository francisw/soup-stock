<?php
/**
 * Created by PhpStorm.
 * User: francisw
 * Date: 12/10/2017
 * Time: 14:47
 */

namespace Stock;

/**
 * Class SoupStock
 * @package Stock
 *
 * This must only be installed on a virgin Wordpress installation, and it will replace it with
 * Vacation Soup's Stock version and database, and it will therefore no longer exist.
 */

class SoupStock extends Singleton
{
    const RELEASE_DOMAIN = "freevacationrentalwebsite.com";
    const RELEASE_RRL = "wp-content/plugins/soup-release-master/current";
    const RELEASE_PFX = "vsoup_";

    /**
     * @var string[] $results of commands run
     */
    protected $results;

    public function __construct(){
        $this->results=[];
    }

    public function init(){
    	global $table_prefix,$wpdb;
        // We can't have to follow normal Wordpress conventions here because we are
        // replacing the Wordpress installation with the Soup, so we run it all from within init

        // Firstly prevent multiple calls. Have observed WP calling activation 3 times
        if (true===get_option('vs-stock-installing')){
            throw new \Exception("Already installing Vacation Soup stock");
        }
        update_option('vs-stock-installing',true);

        try {
            $this->checkForNewInstallation();

	        $stock='stock.tar';
            $file_url="https://".self::RELEASE_DOMAIN."/".self::RELEASE_RRL."/{$stock}.gz";
            $old_table_prefix = $table_prefix;
            $new_table_prefix = 'vsoup_';
            $sql_foreign = ABSPATH.self::RELEASE_RRL.'/'.'stock.sql';
            $sql_local = "/tmp/stock.sql";
            if (true) { //(@copy($file_url, $soup)) { //Grab the latest archive <-- now doing it from commands below

	            set_time_limit(30);
                if (TRUE || $_SERVER['HTTP_HOST'] != self::RELEASE_DOMAIN) {
                    // Save the wp-config, we will only change the $table_prefix
                    // Save the logged in User record, incl Password etc
                    // Save site urls (possibly, depending if dom

                    // OK, we're up - NO MORE wp commands from here, only raw php / unix
                    try {
                    	$this->exec_array([
                        	// Get the stock
                            "curl -sk {$file_url} > /tmp/{$stock}.gz",
	                        //  move our website to a safe folder
                            "mkdir pre-vs",
                            "mv *  pre-vs || true", // To allow the error on moving pre-vs to itself
                            // Unpack the archive into this location
                            "gunzip --stdout /tmp/{$stock} | tar -xf -",
                            // Install the saved wp-config, changing the table prefix
                            "sed -e 's|\$table_prefix|\$table_prefix = \"vsoup_\"; // \$table_prefix|' < pre-vs/wp-config.php > wp-config.php",
		                    // And change any VacationSoup urls to local ones in the SQL
							"sed -e 's|".self::RELEASE_DOMAIN."|".$_SERVER['HTTP_HOST']."|g' < {$sql_foreign} > {$sql_local}",
                            "rm /tmp/{$stock}.gz"
                        ]);

                        $this->mySQL($sql_local);
	                    $wpdb->query("DELETE FROM {$new_table_prefix} WHERE ID=1");
	                    $wpdb->query("INSERT INTO {$new_table_prefix} SELECT * FROM {$old_table_prefix} WHERE ID=1");

                    } catch (\Exception $e){
                        throw new \Exception("Fatal Error during installation, this Wordpress is now likely to be corrupted, a new Installation is necessary. ".$e->getMessage() );
                    }
                }
            } else {
                throw new \Exception("Failed to import ".$file_url);
            }
        } catch (\Exception $e){
            wp_die("<h1>Vacation Soup Stock Installation Failed</h1><br />".$e->getMessage());
        }
        header('Location: wp-admin');
    }

    /**
     * @param $cmds string[] The array of shell commands to run
     * @return string[] The Array of results
     * @throws \Exception if command fails
     */
    function exec_array($cmds) {
        foreach ($cmds as $command){
            $return = $output = 'Not run';
            $when = date("D M j G:i:s T Y");
            $ex = 'cd '.ABSPATH.' && '.$command.' 2>&1';
            exec($ex, $output, $return);
            $this->results[] = compact('when','command','ex','output','return');
            if (0!=$return){ // Oops
                throw new \Exception("Error running shell command: ".$command);
            }
        }
    }


    /**
     * Throw up if this is not a plain new installation
     */
    private function checkForNewInstallation(){
        global $table_prefix;
        if (!empty($when = get_option('vs-released'))){
            throw new \Exception("Vacation Soup Stock Installer was already run. Installed version from ".$when);
        }
        // There must only be 1 plugin installed (this one)
        if (count(get_option('active_plugins'))!=0){
            throw new \Exception("The only active plugin must be Vacation Soup Stock. If your hosting provider automatically installs a plugin on a new installation, it must be deactivated.");
        }
        // There must be only 1 user created
        if (count_users()['total_users'] != 1){
            throw new \Exception("The only user must be the one you created on installation. If your hosting provider automatically adds a user, it must be deleted. There are ".print_r(count_users(),true)." users.");
        }
        // There must be only 1 post created
        if (wp_count_posts()!=1){
            throw new \Exception("There can only be 1 example post on installation. If your hosting provider automatically adds other posts, they must be deleted.");
        }
        // The existing database tables cannot be prefixed vsoup_
        if (self::RELEASE_PFX==$table_prefix){
            throw new \Exception("Your wordpress is configured with a table prefix of '".self::RELEASE_PFX."' which is reserved for Vacation Soup.");
        }
        // The domain must not be where we came from
        if ($_SERVER['HTTP_HOST'] == self::RELEASE_DOMAIN) {
        	throw new \Exception("This plugin is running on a Vacation Soup host, which is not allowed, it must be run on client installations.");
        }
        // The new domain must be https
        $isSecure = false;
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
            $isSecure = true;
        }
        // These checks are for installations behind a load balancer that decodes https for you
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on') {
            $isSecure = true;
        }
        if (!$isSecure){
            throw new \Exception("This site is not secured by https. You must configure https (also known as SSL) for this website before you install Vacation Soup.");
        }
    }

    private function mySQL($filename){
        GLOBAL $wpdb;
        // $maxRuntime = 25; // less then your max script execution limit, as we use set_time_limit may not be needed


        // $deadline = time()+$maxRuntime;
        $progressFilename = $filename.'_filepointer'; // tmp file for progress
        $errorFilename = $filename.'_error'; // tmp file for erro

        ($fp = fopen($filename, 'r')) OR die('failed to open file:'.$filename);

        // check for previous error
        if( file_exists($errorFilename) ){
            wp_die('<pre> previous error: '.file_get_contents($errorFilename));
        }

        // activate automatic reload in browser
        // echo '<html><head> <meta http-equiv="refresh" content="'.($maxRuntime+2).'"><pre>';

        // go to previous file position
        $filePosition = 0;
        if( file_exists($progressFilename) ){
            $filePosition = file_get_contents($progressFilename);
            fseek($fp, $filePosition);
        }

        $queryCount = 0;
        $query = '';
        while( ($line=fgets($fp, 1024000)) ){ // removed: $deadline>time() AND
            set_time_limit(30);
            if(substr($line,0,2)=='--' OR trim($line)=='' ){
                continue;
            }

            $query .= $line;
            if( substr(trim($query),-1)==';' ){
                if( false === $wpdb->query($query) ){
                    $error = 'Error performing query \'<strong>' . $query . '\': ' . $wpdb->last_error;
                    file_put_contents($errorFilename, $error."\n");
                    wp_die($error);
                }
                $query = '';
                file_put_contents($progressFilename, ftell($fp)); // save the current file position for
                $queryCount++;
            }
            $when = date("D M j G:i:s T Y");
            $command = substr($query,0,80).(strlen($query)>80)?'...':'';
            $output = [
                $queryCount.' queries processed',
                ftell($fp).'/'.filesize($filename).' '.(round(ftell($fp)/filesize($filename), 2)*100).'%'."\n"
            ];

            $this->results[] = compact('when','command','output');
        }
    }
}