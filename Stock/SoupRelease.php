<?php
/**
 * Created by PhpStorm.
 * User: francisw
 * Date: 11/10/2017
 * Time: 11:13
 */

namespace Stock;


class SoupRelease extends SitePersistedSingleton
{
    protected $last_run_at;
    protected $last_result;
    protected $last_eresult;
    protected $state;

    public function init(){
        switch ($this->state){
            case 'persisted':
                // We have been restored from a database, so we need to
                // Deactivate ourselves
                deactivate_plugins( plugin_basename( __DIR__ ) );
                $this->soup_release_deactivation(); // Make sure
                break;
            default:
                $this->state='releasing';
                $this->persist();
            case 'releasing':
                if ( current_user_can( 'manage_options' ) ) {
                    if ( ! wp_next_scheduled( 'soup_release_hook' ) ) {
                        wp_schedule_event( time(), 'daily', 'soup_release_hook' );
                        register_deactivation_hook(__FILE__, [$this,'soup_release_deactivation']);
                    }
                    add_action( 'soup_release_hook', [$this,'soup_release_action'] );
                    $this->_release();// Enable release from command line to push an urgent change, append &_release to URL
                    // $this->_extend();// Enable release from command line to push an urgent change, append &_release to URL
                }
        }
    }

    function soup_release_action() {
        GLOBAL $table_prefix;
        $server_name   = DB_HOST;
        $username      = DB_USER;
        $password      = DB_PASSWORD;
        $database_name = DB_NAME;
        $backup_path   = __DIR__ . '/../current';

        // Set our state so a restored copy will know it should die and disable
        $this->state='persisted';
        $this->persist();

        $prog          = 'bin/fail';
        foreach (['/usr/local/bin/mysqldump','/usr/bin/mysqldump','/Applications/MAMP/Library/bin/mysqldump'] as $prog){
            if (file_exists($prog)) {
                break;
            }
        }
        $mysqlparams = " -h {$server_name} -u {$username} \"-p{$password}\" {$database_name}";
        $mysqldump   = "{$prog} --hex-blob --routines --skip-lock-tables"; // --log-error={$backup_path}/mysqldump_error.log";

        $this->last_run_at = time();
        $this->last_result=$this->exec_array([
            "/bin/rm -f {$backup_path}/stock.*",
            "{$mysqldump} {$mysqlparams} | sed -e 's/{$table_prefix}/vsoup_/g' > {$backup_path}/stock.sql ",
            "tar -cf - --exclude='stock.tar*' . | /bin/gzip -c > {$backup_path}/stock.tar.gz",
        ]);
        $this->state='releasing'; // Reset our state so we know we are still running
        $this->persist(); // https://freevacationrentalwebsite.com/wp-content/plugins/soup-release-master/current/stock.tar.gz
    }
    function soup_extend_action() {

        $this->state='releasing';
        $this->persist();

        $this->soup_release_action();
    }

    function exec_array($cmds) {
        $result=[];
        foreach ($cmds as $command){
            $return = $output = 'Not run';
            $when = date("D M j G:i:s T Y");
            $ex = 'cd '.ABSPATH.' && '.$command.' 2>&1';
            exec($ex, $output, $return);
            $result[] = compact('when','command','ex','output','return');
            if (0!=$return){ // Oops
                break;
            }
        }
        return $result;
    }


    private function _release(){
        $last = 'last';
        if (isset($_REQUEST['_release'])){
            if ('last'!=$_REQUEST['_release']) {
                $this->state='releasing';
                $this->persist();
                $this->soup_release_action();
                $last = 'this';
            }
            echo "<h1>Output from {$last} run</h1><pre>";
            echo print_r($this->last_result);
            echo "</pre>";
            wp_die();
        }
    }
    private function _extend()
    {
        $last = 'last';
        if (isset($_REQUEST['_extend'])) {
            if ('last' != $_REQUEST['_extend']) {
                $this->soup_extend_action();
                $last = 'this';
            }
            echo "<h1>Output from {$last} run</h1><pre>";
            echo print_r($this->last_eresult);
            echo "</pre>";
            wp_die();
        }
    }

    function soup_release_deactivation() {
        wp_clear_scheduled_hook('soup_release_hook');
    }
}