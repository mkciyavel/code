<?php
/**
 * Ticket replies and the solved action must not break SQL on quotes.
 * Run: php tests/ticket_reply_test.php
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

    $dir = sys_get_temp_dir() . '/ticketreply_' . bin2hex(random_bytes(4));
    mkdir($dir . '/includes', 0700, true);
    $log = $dir . '/sql.log';
    file_put_contents($log, '');

    $config = <<<'PHP'
<?php
$login = true;
$session_right = 1;
$session_id = 1;
$session_user = 'admin';
$gcon = 'stub';
if (!defined('CMS_SERV_NAME')) {
    define('CMS_SERV_NAME', 'Test');
}
function mysql_real_escape_string($string, $link = null) {
    $string = (string)$string;
    $map = array(
        "\\" => "\\\\",
        "'" => "\\'",
        "\"" => "\\\"",
        "\n" => "\\n",
        "\r" => "\\r",
        "\x00" => "\\0",
        "\x1a" => "\\Z",
    );
    return strtr($string, $map);
}
function mysql_query($sql, $link = null) {
    $log = getenv('SQL_LOG');
    file_put_contents($log, $sql . "\n----\n", FILE_APPEND);
    if (getenv('TEST_INSERT_FAIL') === '1' && stripos($sql, 'INSERT INTO tickets') !== false) {
        return false;
    }
    if (getenv('TEST_SOLVE_FAIL') === '1' && stripos($sql, 'SET Solved') !== false) {
        return false;
    }
    $key = md5($sql . '|' . uniqid('', true));
    $GLOBALS['tq'][$key] = array(
        array(
            'username' => 'player',
            'text' => 'hello',
            'date' => '2026-10-01 12:00:00',
            'solved' => '0',
            'title' => 'Cannot login',
            'UserID' => '50',
        ),
    );
    $GLOBALS['ti'][$key] = 0;
    return $key;
}
function mysql_fetch_assoc($result) {
    if (!isset($GLOBALS['tq'][$result])) {
        return false;
    }
    $index = $GLOBALS['ti'][$result];
    if ($index >= count($GLOBALS['tq'][$result])) {
        return false;
    }
    $GLOBALS['ti'][$result] = $index + 1;
    return $GLOBALS['tq'][$result][$index];
}
function mysql_num_rows($result) {
    if (!isset($GLOBALS['tq'][$result])) {
        return 0;
    }
    return count($GLOBALS['tq'][$result]);
}
PHP;
    file_put_contents($dir . '/includes/config.php', $config);
    file_put_contents($dir . '/includes/head.php', "<?php\n");
    file_put_contents($dir . '/includes/foot.php', "<?php\n");
    symlink($admin, $dir . '/admin.php');

    $runner = $dir . '/runner.php';
    file_put_contents($runner, <<<'PHP'
<?php
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
file_put_contents(getenv('TEST_OUT'), ob_get_clean());
PHP
    );

    $env = array();
    foreach (array_merge($_SERVER, $_ENV) as $key => $value) {
        if (is_string($key) && is_string($value)) {
            $env[$key] = $value;
        }
    }
    $env['SQL_LOG'] = $log;
    $env['TEST_OUT'] = $dir . '/out.html';
    $env['TEST_GET'] = json_encode($get);
    $env['TEST_POST'] = json_encode($post);
    $env['TEST_INSERT_FAIL'] = !empty($expect['insert_fail']) ? '1' : '0';
    $env['TEST_SOLVE_FAIL'] = !empty($expect['solve_fail']) ? '1' : '0';

    $cmd = 'php -d auto_prepend_file= ' . escapeshellarg($runner);
    $descriptors = array(
        1 => array('pipe', 'w'),
        2 => array('pipe', 'w'),
    );
    $proc = proc_open($cmd, $descriptors, $pipes, $dir, $env);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($proc);
    $html = file_exists($dir . '/out.html') ? file_get_contents($dir . '/out.html') : '';
    $sqlLog = file_get_contents($log);

    assert_true($code === 0, "$name exits 0 (got $code) stderr=" . trim($stderr) . ' stdout=' . trim($stdout));

    if (is_dir($dir)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            if ($file->isLink() || $file->isFile()) {
                unlink($file->getPathname());
            } else {
                rmdir($file->getPathname());
            }
        }
        rmdir($dir);
    }

    return array($html, $sqlLog);
}

list($html, $sqlLog) = run_case(
    'apostrophe reply is stored',
    array('mode' => '9', 'h' => 'ticket1'),
    array('text' => "Das gibt's nicht"),
    array()
);
assert_true(strpos($html, 'erfolgreich erstellt') !== false, 'apostrophe reply reports success, html=' . json_encode(substr($html, 0, 400)));
assert_true(strpos($sqlLog, 'gibt&#039;s') !== false, 'apostrophe is encoded before it is placed in SQL, log=' . json_encode($sqlLog));
assert_true(strpos($sqlLog, "gibt's nicht") === false, 'raw apostrophe does not terminate the insert, log=' . json_encode($sqlLog));
assert_true(strpos($sqlLog, "INSERT INTO tickets") !== false, 'reply insert runs');
assert_true(strpos($sqlLog, "SET AnswerID") !== false, 'ticket is claimed after the reply is stored');
$insertPos = strpos($sqlLog, 'INSERT INTO tickets');
$claimPos = strpos($sqlLog, 'SET AnswerID');
assert_true($insertPos !== false && $claimPos !== false && $insertPos < $claimPos, 'claim update follows a successful insert');

list($html, $sqlLog) = run_case(
    'german reply keeps html entities',
    array('mode' => '9', 'h' => 'ticket1'),
    array('text' => 'Größe'),
    array()
);
assert_true(strpos($sqlLog, 'Gr&ouml;&szlig;e') !== false, 'umlauts stay html-encoded for the ticket page, log=' . json_encode($sqlLog));
assert_true(strpos($html, 'erfolgreich erstellt') !== false, 'german reply reports success');

list($html, $sqlLog) = run_case(
    'failed insert is not success and does not claim the ticket',
    array('mode' => '9', 'h' => 'ticket1'),
    array('text' => "Das gibt's nicht"),
    array('insert_fail' => true)
);
assert_true(strpos($html, 'erfolgreich') === false, 'failed insert does not report success, html=' . json_encode(substr($html, 0, 500)));
assert_true(strpos($html, 'konnte nicht gespeichert') !== false, 'failed insert explains that the reply was not saved');
assert_true(strpos($sqlLog, 'SET AnswerID') === false, 'failed insert does not claim the ticket, log=' . json_encode($sqlLog));

list($html, $sqlLog) = run_case(
    'quote in the hash cannot close every ticket',
    array('mode' => '9', 'action' => '2', 'h' => "x' OR '1'='1"),
    array(),
    array()
);
assert_true(strpos($sqlLog, "hash = 'x' OR '1'='1'") === false, 'solved update does not close the quote early, log=' . json_encode($sqlLog));
assert_true(strpos($sqlLog, "hash = 'x\\' OR \\'1\\'=\\'1'") !== false, 'solved hash is escaped, log=' . json_encode($sqlLog));
assert_true(strpos($html, 'wurde als gel') !== false, 'a literal hash is still marked solved, html=' . json_encode(substr($html, -400)));
assert_true(strpos($html, 'konnte nicht') === false, 'escaped hash is not reported as a failed update');

list($html, $sqlLog) = run_case(
    'failed solved update is not reported as success',
    array('mode' => '9', 'action' => '2', 'h' => 'ticket1'),
    array(),
    array('solve_fail' => true)
);
assert_true(strpos($html, 'erfolgreich') === false, 'failed solved update does not report success');
assert_true(strpos($html, 'konnte nicht als') !== false, 'failed solved update shows an error');

list($html, $sqlLog) = run_case(
    'trailing backslash does not escape the closing quote',
    array('mode' => '9', 'h' => 'ticket1'),
    array('text' => 'C:\\4story\\'),
    array()
);
assert_true(strpos($html, 'erfolgreich erstellt') !== false, 'path reply reports success');
assert_true(preg_match('/4story\\\\\\\\\'/', $sqlLog) === 1, 'trailing backslash is doubled so the quote stays a delimiter, log=' . json_encode($sqlLog));

list($html, $sqlLog) = run_case(
    'thread view escapes the hash',
    array('mode' => '9', 'h' => "ab'c"),
    array(),
    array()
);
assert_true(strpos($sqlLog, "hash = 'ab'c'") === false, 'thread select does not close the quote early, log=' . json_encode($sqlLog));
assert_true(substr_count($sqlLog, "hash = 'ab\\'c'") >= 2, 'thread select and pager both escape the hash, log=' . json_encode($sqlLog));

if ($failures > 0) {
    fwrite(STDERR, "$failures assertion(s) failed\n");
    exit(1);
}

fwrite(STDOUT, "all ticket reply assertions passed\n");
exit(0);
