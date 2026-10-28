<?php
/**
 * Mode 8 actions 6 and 8 must only store a real race or sex.
 * Those fields were MySQL-escaped and concatenated into a SQL Server
 * statement. A quote still ends the string, so one submit can update or
 * delete every character, and the page still says the change worked.
 *
 * Run: php tests/character_race_sex_test.php
 */

$root = dirname(__DIR__);
$admin = $root . '/admin.php';
$failures = 0;

function assert_true($cond, $message)
{
    global $failures;
    if (!$cond) {
        $failures++;
        fwrite(STDERR, "FAIL: $message\n");
    } else {
        fwrite(STDOUT, "OK: $message\n");
    }
}

function run_case($name, $get, $post, $expect)
{
    global $admin;

    $dir = sys_get_temp_dir() . '/racesex_' . bin2hex(random_bytes(4));
    mkdir($dir . '/includes', 0700, true);
    $log = $dir . '/odbc.log';
    file_put_contents($log, '');

    $config = <<<'PHP'
<?php
$login = true;
$session_right = (int)getenv('TEST_RIGHT');
$session_id = 1;
$session_user = 'admin';
$gcon = 'stub';
if (!defined('CMS_SERV_NAME')) {
    define('CMS_SERV_NAME', 'Test');
}
function mysql_real_escape_string($string, $link = null) {
    return str_replace(array("\\", "'", "\""), array("\\\\", "\\'", "\\\""), (string)$string);
}
function odbc_exec($con, $sql) {
    $log = getenv('ODBC_LOG');
    file_put_contents($log, $sql . "\n----\n", FILE_APPEND);
    $trim = ltrim($sql);
    if (stripos($trim, 'UPDATE ') === 0) {
        return getenv('TEST_UPDATE_FAIL') === '1' ? false : 'updated';
    }
    return 'other';
}
function odbc_num_rows($result) {
    return 0;
}
function odbc_fetch_array($result) {
    return false;
}
function mysql_query($sql, $link = null) {
    return false;
}
function mysql_num_rows($result) {
    return 0;
}
function mysql_fetch_assoc($result) {
    return false;
}
PHP;
    file_put_contents($dir . '/includes/config.php', $config);
    file_put_contents($dir . '/includes/head.php', "<?php\n");
    file_put_contents($dir . '/includes/foot.php', "<?php\n");
    symlink($admin, $dir . '/admin.php');

    $runner = $dir . '/runner.php';
    file_put_contents($runner, <<<'PHP'
<?php
ini_set('session.save_path', sys_get_temp_dir());
$_GET = json_decode(getenv('TEST_GET'), true);
$_POST = json_decode(getenv('TEST_POST'), true);
if (!is_array($_GET)) {
    $_GET = array();
}
if (!is_array($_POST)) {
    $_POST = array();
}
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = empty($_POST) ? 'GET' : 'POST';
ob_start();
include 'admin.php';
$out = ob_get_clean();
file_put_contents(getenv('TEST_OUT'), $out);
PHP
    );

    $env = array();
    foreach (array_merge($_SERVER, $_ENV) as $key => $value) {
        if (is_string($key) && is_string($value)) {
            $env[$key] = $value;
        }
    }
    $env['ODBC_LOG'] = $log;
    $env['TEST_OUT'] = $dir . '/out.html';
    $env['TEST_GET'] = json_encode($get);
    $env['TEST_POST'] = json_encode($post);
    $env['TEST_RIGHT'] = isset($expect['right']) ? (string)$expect['right'] : '4';
    $env['TEST_UPDATE_FAIL'] = !empty($expect['update_fail']) ? '1' : '0';

    $cmd = 'php -d auto_prepend_file= ' . escapeshellarg($runner);
    $descriptors = array(
        0 => array('pipe', 'r'),
        1 => array('pipe', 'w'),
        2 => array('pipe', 'w'),
    );
    $proc = proc_open($cmd, $descriptors, $pipes, $dir, $env);
    if (!is_resource($proc)) {
        assert_true(false, "$name: runner failed to start");
        return;
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($proc);

    $html = file_exists($dir . '/out.html') ? file_get_contents($dir . '/out.html') : '';
    $sqlLog = file_get_contents($log);
    $updates = array();
    foreach (explode("\n----\n", $sqlLog) as $statement) {
        if (stripos(ltrim($statement), 'UPDATE ') === 0) {
            $updates[] = trim($statement);
        }
    }

    assert_true($exit === 0, "$name exits 0 (stderr: " . trim($stderr) . " stdout: " . trim($stdout) . ")");

    if (!empty($expect['update_fail'])) {
        assert_true(count($updates) === 1, "$name still sends the UPDATE, got " . count($updates) . ": " . implode(' | ', $updates));
        if (count($updates) === 1 && !empty($expect['update'])) {
            assert_true($updates[0] === $expect['update'], "$name UPDATE is [" . $updates[0] . "] expected [" . $expect['update'] . "]");
        }
        assert_true(strpos($html, 'erfolgreich') === false, "$name does not claim success when the write fails");
    } else if (!empty($expect['update'])) {
        assert_true(count($updates) === 1, "$name writes exactly one UPDATE, got " . count($updates) . ": " . implode(' | ', $updates));
        if (count($updates) === 1) {
            assert_true($updates[0] === $expect['update'], "$name UPDATE is [" . $updates[0] . "] expected [" . $expect['update'] . "]");
        }
        assert_true(strpos($html, 'erfolgreich') !== false, "$name reports success");
    } else {
        assert_true(count($updates) === 0, "$name does not UPDATE, got: " . implode(' | ', $updates));
        assert_true(strpos($html, 'erfolgreich') === false, "$name does not claim success");
    }

    if (!empty($expect['html'])) {
        assert_true(strpos($html, $expect['html']) !== false, "$name HTML contains " . $expect['html']);
    }
    if (!empty($expect['html_absent'])) {
        assert_true(strpos($html, $expect['html_absent']) === false, "$name HTML omits " . $expect['html_absent']);
    }
    if (!empty($expect['sql_absent'])) {
        assert_true(strpos($sqlLog, $expect['sql_absent']) === false, "$name SQL omits " . $expect['sql_absent']);
    }

    if (!empty($expect['keep'])) {
        copy($dir . '/out.html', $expect['keep']);
        copy($log, $expect['keep'] . '.sql.log');
    }
    unlink($dir . '/out.html');
    unlink($log);
    unlink($runner);
    unlink($dir . '/admin.php');
    unlink($dir . '/includes/config.php');
    unlink($dir . '/includes/head.php');
    unlink($dir . '/includes/foot.php');
    rmdir($dir . '/includes');
    rmdir($dir);
}

run_case(
    'quoted race is not written',
    array('mode' => '8', 'action' => '6', 'cid' => '7'),
    array('newrace' => "0'; DELETE FROM TGAME_GSP.dbo.TCHARTABLE; --"),
    array(
        'html' => 'Die Rasse konnte nicht ge&auml;ndert werden.',
        'sql_absent' => 'DELETE FROM',
        'keep' => '/opt/cursor/artifacts/race-injection-rejected.html',
    )
);

run_case(
    'race quote in character id is not written',
    array('mode' => '8', 'action' => '6', 'cid' => "7'; DELETE FROM TGAME_GSP.dbo.TCHARTABLE; --"),
    array('newrace' => '1'),
    array(
        'html' => 'Die Rasse konnte nicht ge&auml;ndert werden.',
        'sql_absent' => 'DELETE FROM',
    )
);

run_case(
    'unknown race is not written',
    array('mode' => '8', 'action' => '6', 'cid' => '7'),
    array('newrace' => '9'),
    array('html' => 'Die Rasse konnte nicht ge&auml;ndert werden.')
);

run_case(
    'human race',
    array('mode' => '8', 'action' => '6', 'cid' => '7'),
    array('newrace' => '0'),
    array(
        'update' => "UPDATE TGAME_GSP.dbo.TCHARTABLE SET bRace = '0' WHERE dwCharID = '7'",
        'keep' => '/opt/cursor/artifacts/race-human-saved.html',
    )
);

run_case(
    'feline race',
    array('mode' => '8', 'action' => '6', 'cid' => '7'),
    array('newrace' => '1'),
    array('update' => "UPDATE TGAME_GSP.dbo.TCHARTABLE SET bRace = '1' WHERE dwCharID = '7'")
);

run_case(
    'fairy race',
    array('mode' => '8', 'action' => '6', 'cid' => '15'),
    array('newrace' => '2'),
    array('update' => "UPDATE TGAME_GSP.dbo.TCHARTABLE SET bRace = '2' WHERE dwCharID = '15'")
);

run_case(
    'race form',
    array('mode' => '8', 'action' => '6', 'cid' => '7'),
    array(),
    array('html' => 'name="newrace"')
);

run_case(
    'race form rejects a bad id',
    array('mode' => '8', 'action' => '6', 'cid' => "7' OR '1'='1"),
    array(),
    array('html' => 'Der Charakter existiert nicht!')
);

run_case(
    'low privilege cannot change race',
    array('mode' => '8', 'action' => '6', 'cid' => '7'),
    array('newrace' => '2'),
    array('right' => '1', 'html_absent' => 'erfolgreich')
);

run_case(
    'failed race write is not success',
    array('mode' => '8', 'action' => '6', 'cid' => '7'),
    array('newrace' => '2'),
    array(
        'update_fail' => true,
        'update' => "UPDATE TGAME_GSP.dbo.TCHARTABLE SET bRace = '2' WHERE dwCharID = '7'",
    )
);

run_case(
    'quoted sex is not written',
    array('mode' => '8', 'action' => '8', 'cid' => '7'),
    array('newsex' => "0'; DELETE FROM TGAME_GSP.dbo.TCHARTABLE; --"),
    array(
        'html' => 'Das Geschlecht konnte nicht ge&auml;ndert werden.',
        'sql_absent' => 'DELETE FROM',
        'keep' => '/opt/cursor/artifacts/sex-injection-rejected.html',
    )
);

run_case(
    'unknown sex is not written',
    array('mode' => '8', 'action' => '8', 'cid' => '7'),
    array('newsex' => '2'),
    array('html' => 'Das Geschlecht konnte nicht ge&auml;ndert werden.')
);

run_case(
    'male sex',
    array('mode' => '8', 'action' => '8', 'cid' => '7'),
    array('newsex' => '0'),
    array(
        'update' => "UPDATE TGAME_GSP.dbo.TCHARTABLE SET bSex = '0' WHERE dwCharID = '7'",
        'keep' => '/opt/cursor/artifacts/sex-male-saved.html',
    )
);

run_case(
    'female sex',
    array('mode' => '8', 'action' => '8', 'cid' => '9'),
    array('newsex' => '1'),
    array('update' => "UPDATE TGAME_GSP.dbo.TCHARTABLE SET bSex = '1' WHERE dwCharID = '9'")
);

run_case(
    'sex form',
    array('mode' => '8', 'action' => '8', 'cid' => '7'),
    array(),
    array('html' => 'name="newsex"')
);

run_case(
    'failed sex write is not success',
    array('mode' => '8', 'action' => '8', 'cid' => '7'),
    array('newsex' => '1'),
    array(
        'update_fail' => true,
        'update' => "UPDATE TGAME_GSP.dbo.TCHARTABLE SET bSex = '1' WHERE dwCharID = '7'",
    )
);

if ($failures > 0) {
    fwrite(STDERR, "$failures assertion(s) failed\n");
    exit(1);
}

fwrite(STDOUT, "all character race and sex checks passed\n");
exit(0);
