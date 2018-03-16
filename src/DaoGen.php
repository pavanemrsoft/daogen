<?php
/**
 * Database Entity & Dao class generator
 *
 * Input
 *   POST body or $ddl variable in source
 *
 * @package    DaoGen
*/

##############################################################################################################

$daoGenVersion = '0.5.9';

require_once 'class.database.php';
require_once 'class.entity.php';
require_once 'class.dao.php';
require_once 'class.controller.php';
require_once 'class.test.php';

#####################################################################################################

/**
 * str_pad for multi-byte strings
 *
 * @param      [type]  $str       [description]
 * @param      [type]  $pad_len   [description]
 * @param      string  $pad_str   [description]
 * @param      [type]  $dir       [description]
 * @param      [type]  $encoding  [description]
 *
 * @return     string
 */
function mb_str_pad($str, $pad_len, $pad_str = ' ', $dir = STR_PAD_RIGHT, $encoding = NULL)
{
    $encoding = $encoding === NULL ? mb_internal_encoding() : $encoding;
    $padBefore = $dir === STR_PAD_BOTH || $dir === STR_PAD_LEFT;
    $padAfter = $dir === STR_PAD_BOTH || $dir === STR_PAD_RIGHT;
    $pad_len -= mb_strlen($str, $encoding);
    $targetLen = $padBefore && $padAfter ? $pad_len / 2 : $pad_len;
    $strToRepeatLen = mb_strlen($pad_str, $encoding);
    $repeatTimes = ceil($targetLen / $strToRepeatLen);
    $repeatedString = str_repeat($pad_str, max(0, $repeatTimes)); // safe if used with valid utf-8 strings
    $before = $padBefore ? mb_substr($repeatedString, 0, floor($targetLen), $encoding) : '';
    $after = $padAfter ? mb_substr($repeatedString, 0, ceil($targetLen), $encoding) : '';

    return $before . $str . $after;
}

/**
 * Return $namespace prefixed with a / if it is given
 *
 * Removes any \ chars from the string
 *
 * @param      string  $namespace  [description]
 *
 * @return     string
 */
function namespaceFilename(string $namespace)
{
  if (!empty($namespace)) {
    $namespace = DIRECTORY_SEPARATOR . '/' . trim($namespace,'\\/');
  }
  return $namespace;
}

/**
 * Format Namespace with / in front
 *
 * @param      string  $namespace  The namespace
 */
function formatNamespace(string $namespace)
{
  if (!empty($namespace)) {
    $namespace = '/' . trim($namespace,'\\/');
  }

  return $namespace;
}

#####################################################################################################

  # Output format (depricated)
  $format = strtolower($_GET['format'] ?? 'html');
  if (empty($format)) $format = 'html';

  # The DDL as POST
  $ddl = file_get_contents('php://input');
  ($ddl) ?? $ddl = $_POST["ddl"];

  # Get the Namespace (GET, POST order)
  #   This is used to prefix all MODEL & ENTITY names.
  #   Controllers : "\App\Controllers\v1\" . $namespace . "\"
  #   Models      : "\App\Models\" . $namespace . "\"
  #   DB          : "\App\Models\" . $namespace . "\Db"
  #   Tests       : "\App\Tests\" . $namespace . "\"
  $namespace = $_GET['namespace'] ?? $_POST['namespace'] ?? '';
  $namespace = trim($namespace,'\\/'); // Remove leading+trailing \ and /

  # Package name
  $package = $_GET['package'] ?? $_POST['package'] ?? '';

  # Show HTML form if no DDL sent via POST
  if (empty($ddl)) {
      header('Content-Type: text/html');

      echo '<!DOCTYPE html>';
      echo '<html>';
      echo '<head>';
      echo  '<title>Entity & Dao class generator</title>';
      echo '</head>';
      echo '<body>';
      echo '<h1>DaoGen for Spin-Framework</h1>';

      echo '<h3>Database or Table DDL (MySql & Firebird accepted)<h3>';
      echo '<p>';
      echo '<form method="post" action="DaoGen.php">';
      echo 'Namespace </b><input type="text" name="namespace" value="'.$namespace.'"></br>';
      echo 'Package </b><input type="text" name="package" value="'.$package.'"></br>';
      echo '<textarea name="ddl" id="ddl" rows="30" cols="160" placeholder="Put DDL here">'.$ddl.'</textarea>';
      echo '<br/><input type="submit" value="Submit">';
      echo '</form>';
      echo '</p>';
      echo '</body></html>';

      die;
  }

#####################################################################################################

  #
  # Create the files
  #

  #
  $database = new \Database( 'Unknown', $ddl, ['namespace'=>$namespace] );

  echo 'DaoGen v'.$daoGenVersion.PHP_EOL;
  echo PHP_EOL;
  echo 'Generating files from Database `'.$database->getName().'`, '.count($database->getTables()).' tables'.PHP_EOL;
  echo PHP_EOL;

  $t1 = microtime(true);

  header('Content-Type: text/plain');

  # Make dirs
  if (!file_exists('output')) mkdir('output');
  if (!file_exists('output/src')) mkdir('output/src');
  if (!file_exists('output/src/app')) mkdir('output/src/app');
  if (!file_exists('output/src/app/Models')) mkdir('output/src/app/Models');
  if (!file_exists('output/src/app/Models'.namespaceFilename($namespace))) mkdir('output/src/app/Models'.namespaceFilename($namespace));
  if (!file_exists('output/src/app/Models'.namespaceFilename($namespace).'/Db')) mkdir('output/src/app/Models'.namespaceFilename($namespace).'/Db');
  if (!file_exists('output/src/app/Controllers')) mkdir('output/src/app/Controllers');
  if (!file_exists('output/src/app/Controllers/v1')) mkdir('output/src/app/Controllers/v1');
  if (!file_exists('output/src/app/Controllers/v1'.namespaceFilename($namespace))) mkdir('output/src/app/Controllers/v1'.namespaceFilename($namespace));
  if (!file_exists('output/tests')) mkdir('output/tests');
  if (!file_exists('output/tests'.namespaceFilename($namespace))) mkdir('output/tests'.namespaceFilename($namespace));

  if (count($database->getTables())>0) {
    # Options array
    $options['namespace'] = $namespace;
    $options['package'] = $package;

    # For each table ...
    foreach ($database->getTables() as $table)
    {
      echo 'Table '.$table->getTableName().PHP_EOL;

      # Generate Entity files
      $entity = new \Entity($table, $options);
      $filename = $table->getClassName().'Entity.php';
      echo ' > Entity:     /src/app/Models'.formatNamespace($namespace).'/'.$filename.PHP_EOL;
      $source = $entity->getPhpSource();
      file_put_contents('Output/src/app/Models'.namespaceFilename($namespace).'/'.$filename, $source );

      # Generate DAO files
      $dao = new \Dao($table, $options);
      $filenameDao = $table->getClassName().'Dao.php';
      echo ' > Dao:        /src/app/Models'.formatNamespace($namespace).'/Db/'.$filenameDao.PHP_EOL;
      $source = $dao->getPhpSource();
      file_put_contents('Output/src/app/Models'.namespaceFilename($namespace).'/Db/'.$filenameDao, $source );

      # Generate Conrollers
      $controller = new \Controller($table, $options);
      $filenameController = $table->getClassName().'Controller.php';
      echo ' > Controller: /src/app/Controllers/v1'.formatNamespace($namespace).'/'.$filenameController.PHP_EOL;
      $source = $controller->getPhpSource();
      file_put_contents('Output/src/app/Controllers/v1'.namespaceFilename($namespace).'/'.$filenameController, $source );

      # Generate tests
      $test = new \Test($table, $options);
      $filenameTest = $table->getClassName().'EntityTest.php';
      echo ' > Test:       /tests'.formatNamespace($namespace).'/'.$filenameTest.PHP_EOL;
      $source = $test->getPhpSource();
      file_put_contents('Output/tests'.namespaceFilename($namespace).'/'.$filenameTest, $source );
    }

    # Copy AbstractBase* files to output
    copy ('AbstractBaseDao.php',    'Output/src/app/Models/AbstractBaseDao.php');
    copy ('AbstractBaseEntity.php', 'Output/src/app/Models/AbstractBaseEntity.php');
  }

  $t2 = microtime(true);
  $dur = $t2-$t1;

  # Estimated work neede to produce the same
  $hours = count($database->getTables()) * 5;        // 5 hours/table
  $days  = $hours/6;                                 // 6h effective workdays

  echo PHP_EOL;

  echo 'Operation took '.number_format($dur,3,'.','').' seconds'.PHP_EOL;
  echo 'Estimated saving '.$hours.' man-hours ('.number_format($days,1,'.','').' man-days)'.PHP_EOL;
  echo '> This was done ~'.number_format($hours*60*60 / $dur,3,'.','').' times faster than manually coding it'.PHP_EOL;
