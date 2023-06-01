                           <?php
    session_start();
    include("includes/config.php");
    include("includes/head.php");
?>

<!--********************************************* Main start *********************************************-->

            <!-- Full page wrapper Start -->
            <!-- Left wrapper Start -->
            <div id="left_wrapper">
                <div class="header">
                	<h2><span><?php echo CMS_SERV_NAME; ?> //</span> Admin Panel</h2>
                </div>
                <div id="dark_wrapper">


                    <!-- Body Start -->
                    <div id="body">
                        <?php
                            if(!$login)
                            {
                                echo '<p>Du hast keinen Zugriff zu diesen Bereich! Bitte logge dich ein!<br /><a href="./login.php">&raquo; Zum Login</a></p>';
                            }
                            else
                            {
                                if($session_right == 0)
                                {
                                    echo '<p>Du hast keinen Zugriff auf diesen Bereich!<br /><a href="./index.php">&raquo; Zur&uuml;ck zur Startseite</a></p>';
                                }
                                if($session_right > 0)
                                {
                                if(!isset($_GET['mode']))
                                {
                                    $sql = "SELECT hash, title, date, solved FROM tickets WHERE Solved = 0 AND AnswerID = 0 AND title != ''";
                                    $q = mysql_query($sql);
                                    $count = mysql_num_rows($q);
                                    if($count > 1 || $count == 0) $count = "($count neue Tickets)"; else $count = "($count neues Ticket)";


                                    $sql = "SELECT hash, title, date, solved FROM tickets WHERE Solved = 0 AND AnswerID = ".$session_id." AND title != ''";
                                    $q = mysql_query($sql);
                                    $count0 = mysql_num_rows($q);
                                    if($count0 > 1 || $count0 == 0) $count0 = "($count0 Tickets)"; else $count0 = "($count0 Ticket)";

                                    //--------------//

                                    if($session_right == 4)
                                    {
                                         echo '<h4>Website</h4>';
                                         echo '<p><a href="./admin_cp.php?mode=1">&raquo; News erstellen</a> | <a href="./admin_cp.php?mode=13">News bearbeiten</a> | <a href="./admin_cp.php?mode=4">News l&ouml;schen</a></p>';
                                         echo '<p><b>&raquo; (Slider)</b> <a href="./admin_cp.php?mode=2">News erstellen</a> | <a href="./admin_cp.php?mode=14">News bearbeiten</a> | <a href="./admin_cp.php?mode=3">News l&ouml;schen</a></p>';
                                         echo '<p><a href="./admin_cp.php?mode=10">&raquo; Cashshop bearbeiten</a></p>';
										 
										 echo '<h4>Website</h4>';
										 echo '<p><a href="http://www.silent4story.de/Launcher/admin.php">&raquo; Launcher News</a></p>';
										 
                                    }
                                    echo '<h4>Support</h4>';
                                    echo '<p><a href="./admin_cp.php?mode=9&action=1">&raquo; Meine Tickets anzeigen</a> <b>'.$count0.'</b></p>';
                                    echo '<p><a href="./admin_cp.php?mode=9">&raquo; Tickets beantworten</a> <b>'.$count.'</b></p>';
                                    echo '<h4>Charakter</h4>';
                                    echo '<p><a href="./admin_cp.php?mode=8">&raquo; Charakter suchen</a></p>';
                                    if($session_right > 2)
                                    {
                                        echo '<p><a href="./admin_cp.php?mode=11">&raquo; Item verschicken</a></p>';
                                    }
                                    if($session_right > 3)
                                    {
                                        echo '<p><a href="./admin_cp.php?mode=12">&raquo; GM-Equipment vergeben</a></p>';
                                    }
                                    echo '<h4>Accounts</h4>';
                                    if($session_right > 3)
                                    {
                                        echo '<p><a href="./admin_cp.php?mode=5">&raquo; Account erstellen</a></p>';
                                    }
                                    echo '<p><a href="./admin_cp.php?mode=7">&raquo; Account ansehen/bearbeiten</a></p>';
                                   // echo '<p><a href="./admin_cp.php?mode=6">&raquo; Account bannen/entbannen</a></p>';
                                }


                                else if($_GET['mode'] == 1 && $session_right == 4)
                                {
                                    $form = true;

                                    if(isset($_POST['title']))
                                    {
                                        $title = $_POST['title'];
                                        $type = $_POST['type'];
                                        $img = $_POST['img'];
                                        $text = $_POST['text'];

                                        $title = mysql_real_escape_string($title);
                                        $img = mysql_real_escape_string($img);
                                        $text = mysql_real_escape_string($text);

                                        $img = $img . ".png";

                                        $date = date('Y-m-d');

                                        $text = htmlentities($text);

                                        $sql = "INSERT INTO news (Hot, Title, Text, Image, Date)
                                        VALUES(".$type.", '".$title."', '".$text."', '".$img."', '".$date."')";
                                        $q = mysql_query($sql);

                                        echo '<p>Die News wurden erfolgreich erstellt!<br /><a href="./admin_cp.php">&raquo; Zur&uuml;ck zum Admin-Panel</a></p>';
                                        echo '<meta http-equiv="refresh" content="1; URL=./admin_cp.php">';
                                        $form = false;
                                    }

                                    if($form)
                                    {
                                        echo '<h3><a href="admin_cp.php">&laquo; Zur&uuml;ck zum Admin-Panel</a></h3><br />';
                                        echo '<h4>News erstellen</h4>';
                                        echo '
                                        <form action="admin_cp.php?mode=1" method="post">
                                        <label>Titel <small><em>(required)</em></small></label>
                                        <input type="text" name="title" id="title" />
                                        <label>Typ <small><em>(required)</em></small></label>
                                        <input type="radio" name="type" id="type" value="0" checked="true" /> General<br />
                                        <input type="radio" name="type" id="type" value="1" /> Hot
                                        <label>Bild-ID <small><em>(required)</em></small></label>
                                        <input type="text" name="img" id="img" />
                                        <label>Text <small><em>(required)</em></small></label>
                                        <textarea cols="65" name="text" id="text"></textarea><br /><br />
                                        <input type="submit" value="News erstellen" class="read_more2" />
                                        </form>
                                        ';
                                    }
                                }

                                else if($_GET['mode'] == 2 && $session_right == 4)
                                {
                                    $form = true;

                                    if(isset($_POST['title']))
                                    {
                                        $title = $_POST['title'];
                                        $text = $_POST['text'];
                                        $img = $_POST['img'];

                                        $title = mysql_real_escape_string($title);
                                        $text = mysql_real_escape_string($text);
                                        $img = mysql_real_escape_string($img);

                                        $img = $img . ".png";

                                        $text = htmlentities($text);

                                        $sql = "INSERT INTO hottest_news (Title, Text, Image)
                                        VALUES('".$title."', '".$text."', '".$img."')";
                                        $q = mysql_query($sql);

                                        echo '<p>Die News wurden erfolgreich erstellt!<br /><a href="./admin_cp.php">&raquo; Zur&uuml;ck zum Admin-Panel</a></p>';
                                        echo '<meta http-equiv="refresh" content="1; URL=./admin_cp.php">';
                                        $form = false;
                                    }

                                    if($form)
                                    {
                                        echo '<h3><a href="admin_cp.php">&laquo; Zur&uuml;ck zum Admin-Panel</a></h3><br />';
                                        echo '<h4>(Slider) News erstellen</h4>';
                                        echo '
                                        <form action="admin_cp.php?mode=2" method="post">
                                        <label>Titel <small><em>(required)</em></small></label>
                                        <input type="text" name="title" id="title" />
                                        <label>Bild-ID <small><em>(required)</em></small></label>
                                        <input type="text" name="img" id="img" />
                                        <label>Text <small><em>(required)</em></small></label>
                                        <textarea cols="65" name="text" id="text"></textarea><br /><br />
                                        <input type="submit" value="News erstellen" class="read_more2" />
                                        </form>
                                        ';
                                    }
                                }

                                else if($_GET['mode'] == 3 && $session_right == 4)
                                {
                                    $form = true;

                                    if(isset($_GET['id']))
                                    {
                                        $id = $_GET['id'];

                                        echo '
                                        <form action="admin_cp.php?mode=3" method="post">
                                        <label>Entg&uuml;ltig l&ouml;schen?</label>
                                        <input type="hidden" name="id" id="id" value="'.$id.'" /><br /><br />
                                        <input type="submit" value="L&ouml;schen" class="read_more2" />
                                        </form>
                                        ';
                                        $form = false;
                                    }

                                    if(isset($_POST['id']))
                                    {
                                        $id = $_POST['id'];

                                        $id = mysql_real_escape_string($id);

                                        $sql = "DELETE FROM hottest_news WHERE id = '".$id."'";
                                        $q = mysql_query($sql);

                                        //$sql0 = "ALTER TABLE `hottest_news` AUTO_INCREMENT = 0";
                                        //$q0 = mysql_query($sql0);

                                        echo '<p>Die News wurden erfolgreich gel&ouml;scht!<br /><a href="./admin_cp.php">&raquo; Zur&uuml;ck zum Admin-Panel</a>
                                        <br /><a href="./admin_cp.php?mode=3">&raquo; Zur&uuml;ck zur L&ouml;sch-Ansicht</a></p>';
                                        echo '<meta http-equiv="refresh" content="1; URL=./admin_cp.php?mode=3.php">';
                                        $form = false;
                                    }

                                    if($form)
                                    {
                                        echo '<h3><a href="admin_cp.php">&laquo; Zur&uuml;ck zum Admin-Panel</a></h3><br />';
                                        echo '<h4>Objekt zum L&ouml;schen w&auml;hlen</h4>';
                                        echo '<ul>';

                                        $sql =  "SELECT id, title FROM hottest_news";
                                        $q = mysql_query($sql);
                                        while($dat = mysql_fetch_assoc($q))
                                        {
                                            $id = $dat['id'];
                                            $title = $dat['title'];

                                            echo '<li><a href="admin_cp.php?mode=3&id='.$id.'">'.$title.'</a></li><hr />';
                                        }
                                        echo '</ul>';
                                    }
                                }

                                else if($_GET['mode'] == 4 && $session_right == 4)
                                {
                                    $form = true;

                                    if(isset($_GET['id']))
                                    {
                                        $id = $_GET['id'];

                                        echo '
                                        <form action="admin_cp.php?mode=4" method="post">
                                        <label>Entg&uuml;ltig l&ouml;schen?</label>
                                        <input type="hidden" name="id" id="id" value="'.$id.'" /><br /><br />
                                        <input type="submit" value="L&ouml;schen" class="read_more2" />
                                        </form>
                                        ';
                                        $form = false;
                                    }

                                    if(isset($_POST['id']))
                                    {
                                        $id = $_POST['id'];

                                        $sql = "DELETE FROM news WHERE id = '".$id."'";
                                        $q = mysql_query($sql);

                                        //$sql0 = "ALTER TABLE `news` AUTO_INCREMENT = 0";
                                        //$q0 = mysql_query($sql0);

                                        echo '<p>Die News wurden erfolgreich gel&ouml;scht!<br /><a href="./admin_cp.php">&raquo; Zur&uuml;ck zum Admin-Panel</a>
                                        <br /><a href="./admin_cp.php?mode=4">&raquo; Zur&uuml;ck zur L&ouml;sch-Ansicht</a></p>';
                                        echo '<meta http-equiv="refresh" content="1; URL=./admin_cp.php?mode=4.php">';
                                        $form = false;
                                    }

                                    if($form)
                                    {
                                        echo '<h3><a href="admin_cp.php">&laquo; Zur&uuml;ck zum Admin-Panel</a></h3><br />';
                                        echo '<h4>Objekt zum L&ouml;schen w&auml;hlen</h4>';
                                        echo '<ul>';

                                        $sql =  "SELECT id, title, date FROM news";
                                        $q = mysql_query($sql);
                                        while($dat = mysql_fetch_assoc($q))
                                        {
                                            $id = $dat['id'];
                                            $title = $dat['title'];
                                            $date = $dat['date'];

                                            $date_array = explode("-",$date);
                                            $date = $date_array[2].".".$date_array[1].".".$date_array[0];

                                            echo '<li><a href="admin_cp.php?mode=4&id='.$id.'">'.$title.'</a><div class="date_n_author"> Premium, '.$date.'</div></li><hr />';
                                        }
                                        echo '</ul>';
                                    }
                                }

                                else if($_GET['mode'] == 5 && $session_right == 4)
                                {
                                    $form = true;

                                    if(isset($_POST['username']))
                                    {
                                        $user_ip = $_SERVER['REMOTE_ADDR'];
                                        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
                                        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
                                        $password2 = isset($_POST['password2']) ? trim($_POST['password2']) : '';
                                        $errors = array();
                                        $success = false;

                                        if($pw == $pw2)
                                        {
                                            $pw = ($pw);
                                            $sql1 = "SELECT dwUserID FROM TGLOBAL_GSP.dbo.TACCOUNT WHERE szUserID = '".$user."'";
                                            $q1 = odbc_exec($gcon, $sql1);
                                            if(odbc_num_rows(�q1) == 0)
                                            {
                                                $sql0 = "SELECT MAX(dwUserID) AS Result FROM TGLOBAL_GSP.dbo.TACCOUNT";
                                                $q0 = odbc_exec($gcon, $sql0);
                                                $count0 = odbc_fetch_array($q0);
                                                $count = $count0['Result'];
                                                $sql = "INSERT INTO TGLOBAL_GSP.dbo.TACCOUNT(dwUserID, szUserID, szPasswd, bCheck, szMail)
                                                VALUES($count + 1, '".$user."', '".$pw."', '1', '".$mail."')";
                                                $q = odbc_exec($gcon, $sql);

                                                echo '<p>Der Account wurde erfolgreich erstellt!<br /><br /><a href="./admin_cp.php">Zur&uuml;ck zum Admin-Panel</a></p>';
                                                echo '<meta http-equiv="refresh" content="1; URL=./admin_cp.php">';
                                                $form = false;
                                            }
                                            else
                                            {
                                                $error = 'Der Accountname existiert bereits!';
                                            }
                                        }
                                        else
                                        {
                                            $error = 'Das Passwort war nicht korrekt!';
                                        }
                                    }

                                    if($form)
                                    {
                                        echo '<h3><a href="admin_cp.php">&laquo; Zur&uuml;ck zum Admin-Panel</a></h3><br />';
                                        echo '<h4>Account erstellen</h4>';
                                        echo '
                                        <form action="admin_cp.php?mode=5" method="post">
                                        <label>Accountname <small><em>(required)</em></small></label>
                                        <input type="text" name="username" id="username" />
                                        <label>eMail-Adresse </label>
                                        <label>Passwort <small><em>(required)</em></small></label>
                                        <input type="password" name="password" id="password" />
                                        <label>Passwort wiederholen <small><em>(required)</em></small></label>
                                        <input type="password" name="password2" id="password2" /><br /><br />
                                        <input type="submit" value="Account erstellen" class="read_more2" />
                                        </form>
                                        ';

                                        if(isset($error))
                                        {
                                            echo '<br /><p>'.$error.'</p>';
                                        }
                                    }
                                }

                                else if($_GET['mode'] == 6)
                                {
                                    $form = true;

                                    if(isset($_POST['banname']))
                                    {
                                        $banname = $_POST['banname'];
										$banreason = $_POST['banreason'];

                                        $sql = "SELECT dwUserID FROM TGLOBAL_GSP.dbo.TACCOUNT WHERE szUserID = '".$banname."'";
                                        $q = odbc_exec($gcon, $sql);
                                        if(odbc_num_rows($q) != 0)
                                        {
                                            $dat = odbc_fetch_array($q);
                                            $banID = $dat['dwUserID'];

                                            $sql1 = "SELECT Ban FROM TGLOBAL_GSP.dbo.TACCOUNT WHERE dwUserID = '".$banID."'";
                                            $q1 = odbc_exec($gcon, $sql1);
                                            $banned = odbc_num_rows($q1);

                                            if($banned == 0)
                                            {
                                                $sql0 = "UPDATE TGLOBAL_GSP.dbo.TACCOUNT SET Ban = '1', BanReason = '$banreason' WHERE dwUserID = $banID";
                                                $q0 = odbc_exec($gcon, $sql0);

                                                echo '<p>Der Account von <i>'.$banname.'</i> wurde erfolgreich <b>gebannt</b>!<br /><a href="./admin_cp.php">Zur&uuml;ck zum Admin-Panel</a></p>';
                                                echo '<meta http-equiv="refresh" content="3; URL=./admin_cp.php">';

                                                echo '<form action="admin_cp.php?mode=6" method="post">
                                                <input type="hidden" id="banname" name="banname" value="'.$banname.'" />
                                                <input type="submit" value="Account entbannen" class="read_more2" />
                                                </form>';
                                            }
                                            else
                                            {
                                                $sql0 = "UPDATE TGLOBAL_GSP.dbo.TACCOUNT SET Ban = '0', BanReason = '' WHERE dwUserID = $banID";
                                                $q0 = odbc_exec($gcon, $sql0);

                                                echo '<p>Der Account von <i>'.$banname.'</i> wurde erfolgreich <b>entbannt</b>!<br /><a href="./admin_cp.php">Zur&uuml;ck zum Admin-Panel</a></p>';
                                                echo '<meta http-equiv="refresh" content="3; URL=./admin_cp.php">';

                                                echo '<form action="admin_cp.php?mode=6" method="post">
                                                <input type="hidden" id="banname" name="banname" value="'.$banname.'" />
                                                <input type="submit" value="Account bannen" class="read_more2" />
                                                </form>';
                                            }

                                            $form = false;
                                        }
                                        else
                                        {
                                            $error = 'Der angegebene Account existiert nicht!';
                                        }
                                    }

                                    if($form)
                                    {
                                        echo '<h3><a href="admin_cp.php">&laquo; Zur&uuml;ck zum Admin-Panel</a></h3><br />';
                                        echo '<h4>Account bannen/entbannen</h4>';
                                        echo '
                                        <form action="admin_cp.php?mode=6" method="post">
                                        <label>Account-Name <small><em>(required)</em></small></label>
                                        <input type="text" id="banname" name="banname" /><br /><br />
										<label>Banreason</label>
										<textarea id="banreason" name="banreason" cols="28"></textarea><br /><br />
                                        <input type="submit" value="Account bannen/entbannen" class="read_more2" />
                                        </form>
                                        ';

                                        if(isset($error))
                                        {
                                            echo '<br /><p>'.$error.'</p>';
                                        }
                                    }
                                }
                                else if($_GET['mode'] == 7)
                                {
                                    $form = true;

                                    if((isset($_POST['username']) || (isset($_GET['id']))) && !isset($_GET['action']))
                                    {
                                        if(!isset($_GET['id']))
                                        {
                                            $userName = $_POST['username'];
                                            $userName = mysql_real_escape_string($userName);

                                            $sql = "SELECT dwUserID FROM TGLOBAL_GSP.dbo.TACCOUNT WHERE szUserID = '".$userName."'";
                                            $q = odbc_exec($gcon, $sql);
                                        }
                                        else
                                        {
                                            $userID = $_GET['id'];
                                            $userID = mysql_real_escape_string($userID);

                                            $sql = "SELECT szUserID FROM TGLOBAL_GSP.dbo.TACCOUNT WHERE dwUserID = ".$userID."";
                                            $q = odbc_exec($gcon, $sql);
                                            $dat = odbc_fetch_array($q);
                                            $userName = $dat['szUserID'];
                                        }

                                        if(odbc_num_rows($q) != 0 || isset($_GET['id']))
                                        {
                                            if(!isset($_GET['id']))
                                            {
                                                $dat = odbc_fetch_array($q);
                                                $userID = $dat['dwUserID'];
                                            }

                                            $sql0 = "SELECT dwUserID FROM TGLOBAL_GSP.dbo.TUSERPROTECTED WHERE dwUserID = ".$userID."";
                                            $q0 = odbc_exec($gcon, $sql0);
                                            $banned = odbc_num_rows($q0);

                                            $sql0 = "SELECT dwCash, dwBonus FROM TGLOBAL_GSP.dbo.TCASHTESTTABLE WHERE dwUserID = ".$userID."";
                                            $q0 = odbc_exec($gcon, $sql0);
                                            $dat = odbc_fetch_array($q0);
                                            $userMS = $dat['dwCash'];
                                            $userBP = $dat['dwBonus'];

                                            $sql0 = "SELECT szName FROM TGAME_GSP.dbo.TCHARTABLE WHERE dwUserID = ".$userID."";
                                            $q0 = odbc_exec($gcon, $sql0);
                                            $userChar = odbc_num_rows($q0);

                                            echo '<ul>';
                                            echo '<h3><a href="admin_cp.php">&laquo; Zur&uuml;ck zum Admin-Panel</a></h3><br />';
                                            if(!$banned)
                                            {
                                                echo '<h4>Account&uuml;bersicht: '.$userName.' &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;(
                                                <a href="admin_cp.php?mode=7&action=4&id='.$userID.'">Bannen</a> )</h4><br />';
                                            }
                                            else
                                            {
                                                echo '<h4>Account&uuml;bersicht: '.$userName.' &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;(Gebannt |
                                                <a href="admin_cp.php?mode=7&action=4&id='.$userID.'">Entbannen</a>)</h4><br />';
                                            }

                                            echo '<li>Charaktere: '.$userChar.' (<a href="admin_cp.php?mode=8&id='.$userID.'">einsehen</a>)</li><hr />';
                                            if($session_right > 1)
                                            {
                                                 echo '<li>Mondsteine: '.$userMS.' (<a href="admin_cp.php?mode=7&action=2&id='.$userID.'">bearbeiten</a>)</li>';
                                                 echo '<li>Bonuspunkte: '.$userBP.' (<a href="admin_cp.php?mode=7&action=3&id='.$userID.'">bearbeiten</a>)</li>';
                                            }
                                            else
                                            {
                                                echo '<li>Mondsteine: '.$userMS.'</li>';
                                                echo '<li>Bonuspunkte: '.$userBP.'</li>';
                                            }

                                            echo '</ul>';

                                            $form = false;
                                        }
                                        else
                                        {
                                            $error = 'Der angegebene Account existiert nicht!';
                                        }
                                    }

                                    if(isset($_GET['action']) && $_GET['action'] == 2 && $session_right > 1)
                                    {
                                        $form = false;

                                        $userID = $_GET['id'];
                                        $userID = mysql_real_escape_string($userID);

                                        if(isset($_POST['newms']))
                                        {
                                            $ms = $_POST['newms'];
                                            $ms = mysql_real_escape_string($ms);

                                            $sql = "SELECT dwCash FROM TGLOBAL_GSP.dbo.TCASHTESTTABLE WHERE dwUserID = '".$userID."'";
                                            $q = odbc_exec($gcon, $sql);
                                            $count = odbc_num_rows($q);
                                            if($count == 0)
                                            {
                                                $sql0 = "INSERT INTO TGLOBAL_GSP.dbo.TCASHTESTTABLE (dwUserID, dwCash, dwBonus)
                                                VALUES('".$userID."', '".$ms."', '0')";
                                            }
                                            else
                                            {
                                                $sql0 = "UPDATE TGLOBAL_GSP.dbo.TCASHTESTTABLE SET dwCash = '".$ms."' WHERE dwUserID = '".$userID."'";
                                            }
                                            $q0 = odbc_exec($gcon, $sql0);

                                            echo '<p>Die Mondsteine wurden erfolgreich ge&auml;ndert!<br /><br /><a href="./admin_cp.php?mode=7&id='.$userID.'">Zur&uuml;ck zur Account&uuml;bersicht</a></p>';
                                            echo '<meta http-equiv="refresh" content="1; URL=./admin_cp.php?mode=7&id='.$userID.'">';

                                        }
                                        if(!isset($_POST['newms']))
                                        {
                                            echo '<h3><a href="admin_cp.php?mode=7&id='.$userID.'">&laquo; Zur&uuml;ck zur Account&uuml;bersicht</a></h3><br />';
                                            echo '<h4>Mondsteine bearbeiten</h4>';
                                            echo '
                                            <form action="admin_cp.php?mode=7&action=2&id='.$userID.'" method="post">
                                            <label>Neue Mondstein-Anzahl <small><em>(required)</em></small></label>
                                            <input type="text" name="newms" id="newms" /><br /><br />
                                            <input type="submit" value="Mondsteine &uuml;bernehmen" class="read_more2" />
                                            </form>
                                            ';
                                        }
                                    }
                                    if(isset($_GET['action']) && $_GET['action'] == 3 && $session_right > 1)
                                    {
                                        $form = false;

                                        $userID = $_GET['id'];
                                        $userID = mysql_real_escape_string($userID);

                                        if(isset($_POST['newbp']))
                                        {
                                            $bp = $_POST['newbp'];
                                            $bp = mysql_real_escape_string($bp);

                                            $sql = "SELECT dwBonus FROM TGLOBAL_GSP.dbo.TCASHTESTTABLE WHERE dwUserID = '".$userID."'";
                                            $q = odbc_exec($gcon, $sql);
                                            $count = odbc_num_rows($q);
                                            if($count == 0)
                                            {
                                                $sql0 = "INSERT INTO TGLOBAL_GSP.dbo.TCASHTESTTABLE (dwUserID, dwCash, dwBonus)
                                                VALUES('".$userID."', '0', '".$bp."')";
                                            }
                                            else
                                            {
                                                $sql0 = "UPDATE TGLOBAL_GSP.dbo.TCASHTESTTABLE SET dwBonus = '".$bp."' WHERE dwUserID = '".$userID."'";
                                            }
                                            $q0 = odbc_exec($gcon, $sql0);

                                            echo '<p>Die Bonuspunkte wurden erfolgreich ge&auml;ndert!<br /><br /><a href="./admin_cp.php?mode=7&id='.$userID.'">Zur&uuml;ck zur Account&uuml;bersicht</a></p>';
                                            echo '<meta http-equiv="refresh" content="1; URL=./admin_cp.php?mode=7&id='.$userID.'">';

                                        }
                                        if(!isset($_POST['newbp']))
                                        {
                                            echo '<h3><a href="admin_cp.php?mode=7&id='.$userID.'">&laquo; Zur&uuml;ck zur Account&uuml;bersicht</a></h3><br />';
                                            echo '<h4>Bonuspunkte bearbeiten</h4>';
                                            echo '
                                            <form action="admin_cp.php?mode=7&action=3&id='.$userID.'" method="post">
                                            <label>Neue Bonuspunkt-Anzahl <small><em>(required)</em></small></label>
                                            <input type="text" name="newbp" id="newbp" /><br /><br />
                                            <input type="submit" value="Bonuspunkte &uuml;bernehmen" class="read_more2" />
                                            </form>
                                            ';
                                        }
                                    }
                                    if(isset($_GET['action']) && $_GET['action'] == 4)
                                    {
                                        $form = false;

                                        $userID = $_GET['id'];

                                        $sql = "SELECT szUserID FROM TGLOBAL_GSP.dbo.TACCOUNT WHERE dwUserID = '".$userID."'";
                                        $q = odbc_exec($gcon, $sql);
                                        $dat = odbc_fetch_array($q);
                                        $userName = $dat['szUserID'];

                                        $sql1 = "SELECT dwSeq FROM TGLOBAL_GSP.dbo.TUSERPROTECTED WHERE dwUserID = '".$userID."'";
                                        $q1 = odbc_exec($gcon, $sql1);
                                        $banned = odbc_num_rows($q1);

                                        if($banned == 0)
                                        {
                                            $sql0 = "INSERT INTO TGLOBAL_GSP.dbo.TUSERPROTECTED (dwUserID, bBlockType, bEternal)VALUES('".$userID."', '1', '1')";
                                            $q0 = odbc_exec($gcon, $sql0);

                                            echo '<p>Der Account von <i>'.$userName.'</i> wurde erfolgreich <b>gebannt</b>!<br />
                                            <a href="./admin_cp.php?mode=7&id='.$userID.'">Zur&uuml;ck zur Account&uuml;bersicht</a></p>';
                                            echo '<meta http-equiv="refresh" content="2; URL=./admin_cp.php?mode=7&id='.$userID.'">';

                                            echo '<form action="admin_cp.php?mode=7&action=4&id='.$userID.'" method="post">
                                            <input type="submit" value="Account entbannen" class="read_more2" />
                                            </form>';
                                        }
                                        else
                                        {
                                            $sql0 = "DELETE FROM TGLOBAL_GSP.dbo.TUSERPROTECTED WHERE dwUserID = '".$userID."'";
                                            $q0 = odbc_exec($gcon, $sql0);

                                            echo '<p>Der Account von <i>'.$userName.'</i> wurde erfolgreich <b>entbannt</b>!<br />
                                            <a href="./admin_cp.php?mode=7&id='.$userID.'">Zur&uuml;ck zur Account&uuml;bersicht</a></p>';
                                            echo '<meta http-equiv="refresh" content="2; URL=./admin_cp.php?mode=7&id='.$userID.'">';

                                            echo '<form action="admin_cp.php?mode=7&action=4&id='.$userID.'" method="post">
                                            <input type="submit" value="Account bannen" class="read_more2" />
                                            </form>';
                                        }
                                    }

                                    if($form)
                                    {
                                        echo '<h3><a href="admin_cp.php">&laquo; Zur&uuml;ck zum Admin-Panel</a></h3><br />';
                                        echo '<h4>Account bearbeiten</h4>';
                                        echo '
                                        <form action="admin_cp.php?mode=7" method="post">
                                        <label>Accountname <small><em>(required)</em></small></label>
                                        <input type="text" name="username" id="username" /><br /><br />
                                        <input type="submit" value="Account bearbeiten" class="read_more2" />
                                        </form>
                                        ';

                                        if(isset($error))
                                        {
                                            echo '<br /><p>'.$error.'</p>';
                                        }
                                    }
                                }
                                else if($_GET['mode'] == 8)
                                {
                                    $form = true;
                                    if(isset($_GET['cid']))
                                    {
                                        $form = false;

                                        $cID = $_GET['cid'];
                                        $cID = mysql_real_escape_string($cID);

                                        if(isset($_GET['action']) && $_GET['action'] == 1 && $session_right > 1)
                                        {
                                            if(isset($_POST['newtp']))
                                            {
                                                $newtp = $_POST['newtp'];
                                                $newtp = mysql_real_escape_string($newtp);

                                                $sql = "UPDATE TGAME_GSP.dbo.TPVPOINTTABLE SET dwTotalPoint = '".$newtp."' WHERE dwCharID = '".$cID."'";
                                                $q = odbc_exec($gcon, $sql);

                                                echo '<p>Die Ehre wurde erfolgreich ge&auml;ndert!<br />
                                                <a href="admin_cp.php?mode=8&cid='.$cID.'">&raquo; Zur&uuml;ck zur Charakter&uuml;bersicht</a></p>';
                                                echo '<meta http-equiv="refresh" content="1; URL=./admin_cp.php?mode=8&cid='.$cID.'">';
                                            }
                                            if(!isset($_POST['newtp']))
                                            {
                                                echo '<h3><a href="admin_cp.php?mode=8&cid='.$cID.'">&laquo; Zur&uuml;ck zur Charakter&uuml;bersicht</a></h3><br />';
                                                echo '
                                                 <form action="admin_cp.php?mode=8&action=1&cid='.$cID.'" method="post">
                                                 <label>Ehre <small><em>(required)</em></small></label>
                                                 <input type="text" name="newtp" id="newtp" /><br /><br />
                                                 <input type="submit" value="Ehre &uuml;bernehmen" class="read_more2" />
                                                 </form>
                                                ';
                                            }
                                        }
                                        if(isset($_GET['action']) && $_GET['action'] == 2 && $session_right > 1)
                                        {
                                            if(isset($_POST['newup']))
                                            {
                                                $newup = $_POST['newup'];
                                                $newup = mysql_real_escape_string($newup);

                                                $sql = "UPDATE TGAME_GSP.dbo.TPVPOINTTABLE SET dwUseablePoint = '".$newup."' WHERE dwCharID = '".$cID."'";
                                                $q = odbc_exec($gcon, $sql);

                                                echo '<p>Die Leistungspunkte wurden erfolgreich ge&auml;ndert!<br />
                                                <a href="admin_cp.php?mode=8&cid='.$cID.'">&raquo; Zur&uuml;ck zur Charakter&uuml;bersicht</a></p>';
                                                echo '<meta http-equiv="refresh" content="1; URL=./admin_cp.php?mode=8&cid='.$cID.'">';
                                            }
                                            if(!isset($_POST['newup']))
                                            {
                                                echo '<h3><a href="admin_cp.php?mode=8&cid='.$cID.'">&laquo; Zur&uuml;ck zur Charakter&uuml;bersicht</a></h3><br />';
                                                echo '
                                                 <form action="admin_cp.php?mode=8&action=2&cid='.$cID.'" method="post">
                                                 <label>Leistungspunkte <small><em>(required)</em></small></label>
                                                 <input type="text" name="newup" id="newup" /><br /><br />
                                                 <input type="submit" value="Leistungspunkte &uuml;bernehmen" class="read_more2" />
                                                 </form>
                                                ';
                                            }
                                        }
                                        if(isset($_GET['action']) && $_GET['action'] == 3 && $session_right > 1)
                                        {
                                            if(isset($_POST['newexp']))
                                            {
                                                $newexp = $_POST['newexp'];
                                                $newexp = mysql_real_escape_string($newexp);

                                                $sql = "UPDATE TGAME_GSP.dbo.TCHARTABLE SET dwExp = '".$newexp."' WHERE dwCharID = '".$cID."'";
                                                $q = odbc_exec($gcon, $sql);

                                                echo '<p>Die Erfahrungspunkte wurden erfolgreich ge&auml;ndert!<br />
                                                <a href="admin_cp.php?mode=8&cid='.$cID.'">&raquo; Zur&uuml;ck zur Charakter&uuml;bersicht</a></p>';
                                                echo '<meta http-equiv="refresh" content="1; URL=./admin_cp.php?mode=8&cid='.$cID.'">';
                                            }
                                            if(!isset($_POST['newexp']))
                                            {
                                                echo '<h3><a href="admin_cp.php?mode=8&cid='.$cID.'">&laquo; Zur&uuml;ck zur Charakter&uuml;bersicht</a></h3><br />';
                                                echo '
                                                 <form action="admin_cp.php?mode=8&action=3&cid='.$cID.'" method="post">
                                                 <label>Erfahrungspunkte <small><em>(required)</em></small></label>
                                                 <input type="text" name="newexp" id="newexp" /><br /><br />
                                                 <input type="submit" value="Erfahrungspunkte &uuml;bernehmen" class="read_more2" />
                                                 </form>
                                                ';
                                            }
                                        }
                                        if(isset($_GET['action']) && $_GET['action'] == 4 && $session_right > 1)
                                        {
                                            if(isset($_POST['newsp']))
                                            {
                                                $newsp = $_POST['newsp'];
                                                $newsp = mysql_real_escape_string($newsp);

                                                $sql = "UPDATE TGAME_GSP.dbo.TCHARTABLE SET wSkillPoint = '".$newsp."' WHERE dwCharID = '".$cID."'";
                                                $q = odbc_exec($gcon, $sql);

                                                echo '<p>Die Skillpunkte wurden erfolgreich ge&auml;ndert!<br />
                                                <a href="admin_cp.php?mode=8&cid='.$cID.'">&raquo; Zur&uuml;ck zur Charakter&uuml;bersicht</a></p>';
                                                echo '<meta http-equiv="refresh" content="1; URL=./admin_cp.php?mode=8&cid='.$cID.'">';
                                            }
                                            if(!isset($_POST['newsp']))
                                            {
                                                echo '<h3><a href="admin_cp.php?mode=8&cid='.$cID.'">&laquo; Zur&uuml;ck zur Charakter&uuml;bersicht</a></h3><br />';
                                                echo '
                                                 <form action="admin_cp.php?mode=8&action=4&cid='.$cID.'" method="post">
                                                 <label>Skillpunkte <small><em>(required)</em></small></label>
                                                 <input type="text" name="newsp" id="newsp" /><br /><br />
                                                 <input type="submit" value="Skillpunkte &uuml;bernehmen" class="read_more2" />
                                                 </form>
                                                ';
                                            }
                                        }
                                        if(isset($_GET['action']) && $_GET['action'] == 5 && $session_right > 1)
                                        {
                                            if(isset($_POST['newlvl']))
                                            {
                                                $newlvl = $_POST['newlvl'];
                                                $newlvl = mysql_real_escape_string($newlvl);

                                                $sql = "UPDATE TGAME_GSP.dbo.TCHARTABLE SET bLevel = '".$newlvl."' WHERE dwCharID = '".$cID."'";
                                                $q = odbc_exec($gcon, $sql);

                                                echo '<p>Das Level wurde erfolgreich ge&auml;ndert!<br />
                                                <a href="admin_cp.php?mode=8&cid='.$cID.'">&raquo; Zur&uuml;ck zur Charakter&uuml;bersicht</a></p>';
                                                echo '<meta http-equiv="refresh" content="1; URL=./admin_cp.php?mode=8&cid='.$cID.'">';
                                            }
                                            if(!isset($_POST['newlvl']))
                                            {
                                                echo '<h3><a href="admin_cp.php?mode=8&cid='.$cID.'">&laquo; Zur&uuml;ck zur Charakter&uuml;bersicht</a></h3><br />';
                                                echo '
                                                 <form action="admin_cp.php?mode=8&action=5&cid='.$cID.'" method="post">
                                                 <label>Neues Level <small><em>(required)</em></small></label>
                                                 <input type="text" name="newlvl" id="newlvl" /><br /><br />
                                                 <input type="submit" value="Level &uuml;bernehmen" class="read_more2" />
                                                 </form>
                                                ';
                                            }
                                        }
                                        if(isset($_GET['action']) && $_GET['action'] == 6 && $session_right > 1)
                                        {
                                            if(isset($_POST['newrace']))
                                            {
                                                $newrace = $_POST['newrace'];
                                                $newrace = mysql_real_escape_string($newrace);

                                                $sql = "UPDATE TGAME_GSP.dbo.TCHARTABLE SET bRace = '".$newrace."' WHERE dwCharID = '".$cID."'";
                                                $q = odbc_exec($gcon, $sql);

                                                echo '<p>Die Rasse wurde erfolgreich ge&auml;ndert!<br />
                                                <a href="admin_cp.php?mode=8&cid='.$cID.'">&raquo; Zur&uuml;ck zur Charakter&uuml;bersicht</a></p>';
                                                echo '<meta http-equiv="refresh" content="1; URL=./admin_cp.php?mode=8&cid='.$cID.'">';
                                            }
                                            if(!isset($_POST['newrace']))
                                            {
                                                echo '<h3><a href="admin_cp.php?mode=8&cid='.$cID.'">&laquo; Zur&uuml;ck zur Charakter&uuml;bersicht</a></h3><br />';
                                                echo '
                                                 <form action="admin_cp.php?mode=8&action=6&cid='.$cID.'" method="post">
                                                 <label>Neue Rasse <small><em>(required)</em></small></label>
                                                 Mensch<input type="radio" name="newrace" id="newrace" value="0" /><br />
                                                 Feline&nbsp;&nbsp;&nbsp;<input type="radio" name="newrace" id="newrace" value="1" /><br />
                                                 Fee&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type="radio" name="newrace" id="newrace" value="2" /><br /><br />
                                                 <input type="submit" value="Rasse &uuml;bernehmen" class="read_more2" />
                                                 </form>
                                                ';
                                            }
                                        }
                                        if(isset($_GET['action']) && $_GET['action'] == 8 && $session_right > 1)
                                        {
                                            if(isset($_POST['newsex']))
                                            {
                                                $newsex = $_POST['newsex'];
                                                $newsex = mysql_real_escape_string($newsex);

                                                $sql = "UPDATE TGAME_GSP.dbo.TCHARTABLE SET bSex = '".$newsex."' WHERE dwCharID = '".$cID."'";
                                                $q = odbc_exec($gcon, $sql);

                                                echo '<p>Das Geschlecht wurde erfolgreich ge&auml;ndert!<br />
                                                <a href="admin_cp.php?mode=8&cid='.$cID.'">&raquo; Zur&uuml;ck zur Charakter&uuml;bersicht</a></p>';
                                                echo '<meta http-equiv="refresh" content="1; URL=./admin_cp.php?mode=8&cid='.$cID.'">';
                                            }
                                            if(!isset($_POST['newsex']))
                                            {
                                                echo '<h3><a href="admin_cp.php?mode=8&cid='.$cID.'">&laquo; Zur&uuml;ck zur Charakter&uuml;bersicht</a></h3><br />';
                                                echo '
                                                 <form action="admin_cp.php?mode=8&action=8&cid='.$cID.'" method="post">
                                                 <label>Neues Geschlecht <small><em>(required)</em></small></label>
                                                 M&auml;nnlich<input type="radio" name="newsex" id="newsex" value="0" /><br />
                                                 Weiblich<input type="radio" name="newsex" id="newsex" value="1" /><br /><br />
                                                 <input type="submit" value="Geschlecht &uuml;bernehmen" class="read_more2" />
                                                 </form>
                                                ';
                                            }
                                        }
                                        if(isset($_GET['action']) && $_GET['action'] == 10 && $session_right > 1)
                                        {
                                            if(isset($_POST['newgold']))
                                            {
                                                $newgold = $_POST['newgold'];
                                                $newsilver = $_POST['newsilver'];
                                                $newcooper = $_POST['newcooper'];
                                                $newgold = mysql_real_escape_string($newgold);
                                                $newsilver = mysql_real_escape_string($newsilver);
                                                $newcooper = mysql_real_escape_string($newcooper);

                                                $sql = "UPDATE TGAME_GSP.dbo.TCHARTABLE SET dwGold = '".$newgold."', dwSilver = '".$newsilver."', dwCooper = '".$newcooper."'
                                                WHERE dwCharID = '".$cID."'";
                                                $q = odbc_exec($gcon, $sql);

                                                echo '<p>Das Geld wurde erfolgreich ge&auml;ndert!<br />
                                                <a href="admin_cp.php?mode=8&cid='.$cID.'">&raquo; Zur&uuml;ck zur Charakter&uuml;bersicht</a></p>';
                                                echo '<meta http-equiv="refresh" content="1; URL=./admin_cp.php?mode=8&cid='.$cID.'">';
                                            }
                                            if(!isset($_POST['newgold']))
                                            {
                                                echo '<h3><a href="admin_cp.php?mode=8&cid='.$cID.'">&laquo; Zur&uuml;ck zur Charakter&uuml;bersicht</a></h3><br />';
                                                echo '
                                                 <form action="admin_cp.php?mode=8&action=10&cid='.$cID.'" method="post">
                                                 <label>Gold </label>
                                                 <input type="text" name="newgold" id="newgold" />
                                                 <label>Silber </label>
                                                 <input type="text" name="newsilver" id="newsilver" />
                                                 <label>Bronze </label>
                                                 <input type="text" name="newcooper" id="newcooper" /><br /><br />
                                                 <input type="submit" value="Geld &uuml;bernehmen" class="read_more2" />
                                                 </form>
                                                ';
                                            }
                                        }
                                        if(isset($_GET['action']) && $_GET['action'] == 11 && $session_right > 1)
                                        {
                                            if(isset($_POST['newname']))
                                            {
                                                $newname = $_POST['newname'];

                                                $sql = "UPDATE TGAME_GSP.dbo.TCHARTABLE SET szName = '".$newname."' WHERE dwCharID = '".$cID."'";
                                                $q = odbc_exec($gcon, $sql);

                                                echo '<p>Der Name wurde erfolgreich ge&auml;ndert!<br />
                                                <a href="admin_cp.php?mode=8&cid='.$cID.'">&raquo; Zur&uuml;ck zur Charakter&uuml;bersicht</a></p>';
                                                echo '<meta http-equiv="refresh" content="1; URL=./admin_cp.php?mode=8&cid='.$cID.'">';
                                            }
                                            if(!isset($_POST['newname']))
                                            {
                                                echo '<h3><a href="admin_cp.php?mode=8&cid='.$cID.'">&laquo; Zur&uuml;ck zur Charakter&uuml;bersicht</a></h3><br />';
                                                echo '
                                                 <form action="admin_cp.php?mode=8&action=11&cid='.$cID.'" method="post">
                                                 <label>Charaktername </label>
                                                 <input type="text" name="newname" id="newname" /><br /><br />
                                                 <input type="submit" value="Namen &uuml;bernehmen" class="read_more2" />
                                                 </form>
                                                ';
                                            }
                                        }
                                        if(!isset($_GET['action']))
                                        {
                                            $sql = "SELECT dwUserID, szName, bClass, bRace, bCountry, bSex, bHair, bFace, bLevel, dwExp, wSkillPoint, dwGold, dwSilver, dwCooper, dCreateDate
                                            FROM TGAME_GSP.dbo.TCHARTABLE WHERE dwCharID = '".$cID."'";
                                            $q = odbc_exec($gcon, $sql);
                                            if(odbc_num_rows($q) != 0)
                                            {
                                                $sql0 = "SELECT dwGuildID FROM TGAME_GSP.dbo.TGUILDMEMBERTABLE WHERE dwCharID = '".$cID."'";
                                                $q0 = odbc_exec($gcon, $sql0);
                                                $gID = odbc_fetch_array($q0);
                                                $gID = $gID['dwGuildID'];

                                                $sql1 = "SELECT szName FROM TGAME_GSP.dbo.TGUILDTABLE WHERE dwID = '".$gID."'";
                                                $q1 = odbc_exec($gcon, $sql1);
                                                $gName = odbc_fetch_array($q1);
                                                $gName = $gName['szName'];

                                                if($gName == null)
                                                    $gName = "Keine Gilde";

                                                $sql0 = "SELECT dwUseablePoint, dwTotalPoint FROM TGAME_GSP.dbo.TPVPOINTTABLE WHERE dwCharID = '".$cID."'";
                                                $q0 = odbc_exec($gcon, $sql0);
                                                $dat0 = odbc_fetch_array($q0);
                                                $cUsePoints = $dat0['dwUseablePoint'];
                                                $cTotalPoints = $dat0['dwTotalPoint'];

                                                $sql2 = "SELECT dwCharID FROM TGLOBAL_GSP.dbo.TCURRENTUSER WHERE dwCharID = '".$cID."'";
                                                $q2 = odbc_exec($gcon, $sql2);
                                                $cOnline = odbc_num_rows($q2);

                                                $dat = odbc_fetch_array($q);
                                                $uID = $dat['dwUserID'];

                                                $sql00 = "SELECT szUserID FROM TGLOBAL_GSP.dbo.TACCOUNT WHERE dwUserID = '".$uID."'";
                                                $q00 = odbc_exec($gcon, $sql00);
                                                $dat00 = odbc_fetch_array($q00);

                                                $aName = $dat00['szUserID'];
                                                $cName = $dat['szName'];
                                                $cClass = $dat['bClass'];
                                                $cRace = $dat['bRace'];
                                                $cCountry = $dat['bCountry'];
                                                $cSex = $dat['bSex'];
                                                $cHair = $dat['bHair'];
                                                $cFace = $dat['bFace'];
                                                $cLevel = $dat['bLevel'];
                                                $cExp = $dat['dwExp'];
                                                $cSkillPoints = $dat['wSkillPoint'];
                                                $cGold = $dat['dwGold'];
                                                $cSilver = $dat['dwSilver'];
                                                $cCooper = $dat['dwCooper'];
                                                $cCreation = $dat['dCreateDate'];

                                                $crea_array = explode("-", $cCreation);
                                                $crea_del = explode(" ", $crea_array[2]);
                                                $cCreation = $crea_del[0] .".". $crea_array[1] .".". $crea_array[0];

                                                $cLevelPx = null;

                                                if($cClass == 0)
                                                    $cLevelPx = 33;
                                                else if($cClass == 1)
                                                    $cLevelPx = 68;
                                                else if($cClass == 2)
                                                    $cLevelPx = 65;
                                                else if($cClass == 3)
                                                    $cLevelPx = 33;
                                                else if($cClass == 4)
                                                    $cLevelPx = 35;
                                                else if($cClass == 5)
                                                    $cLevelPx = 52;

                                                $cClassImg = null;

                                                if($cClass == 0)
                                                    $cClassImg = "warrior";
                                                else if($cClass == 1)
                                                    $cClassImg = "assassin";
                                                else if($cClass == 2)
                                                    $cClassImg = "archer";
                                                else if($cClass == 3)
                                                    $cClassImg = "wizard";
                                                else if($cClass == 4)
                                                    $cClassImg = "priest";
                                                else if($cClass == 5)
                                                    $cClassImg = "summoner";

                                                if($cClass == 0)
                                                    $cClass = "Krieger";
                                                else if($cClass == 1)
                                                    $cClass = "Schattenl&auml;ufer";
                                                else if($cClass == 2)
                                                    $cClass = "Bogensch&uuml;tze";
                                                else if($cClass == 3)
                                                    $cClass = "Magier";
                                                else if($cClass == 4)
                                                    $cClass = "Priester";
                                                else if($cClass == 5)
                                                    $cClass = "Beschw&ouml;rer";

                                                if($cCountry == 4 || $cCountry == 2)
                                                    $cCountry = "gor";
                                                else if($cCountry == 1)
                                                    $cCountry = "deri";
                                                else if($cCountry == 0)
                                                    $cCountry = "valo";

                                                $playFace = $cRace . $cSex . $cHair . $cFace;

                                                if($cSex == 0)
                                                    $cSex = "m&auml;nnlich";
                                                else if($cSex == 1)
                                                    $cSex = "weiblich";

                                                if($cRace == 0)
                                                    $cRace = "Mensch";
                                                else if($cRace == 1)
                                                    $cRace = "Feline";
                                                else if($cRace == 2)
                                                    $cRace = "Fee";

                                                $cOnlineTxt = null;

                                                if($cOnline == 1)
                                                    $cOnlineTxt = "<span class='green_bg'>Online</span>";
                                                else if($cOnline == 0)
                                                    $cOnlineTxt = "<span class='red_bg'>Offline</span>";

                                                /*<img style="float: left; margin-right: 10px;" src="images/char/face/'.$playFace.'.png" />
                                                <img style="float: right; margin-right: -5px;" src="images/char/class/'.$cClassImg.'.png" />
                                                <img style="float: right; margin-right: -70px; margin-top: 5px;" src="images/char/'.$cCountry.'.png" />
                                                <small style="float: right; margin-right: 5px;">'.$cClass.'</small>
                                                <small style="float: right; margin-right: -33px; margin-top: 15px;">Level '.$cLevel.'</small>
                                                <a href="admin_cp.php?mode=8&cid='.$cID.'">'.$cName.'</a>&nbsp;&nbsp;&nbsp;&nbsp;'.$cOnlineTxt.'<br /><small>['.$gName.']</small>*/

                                                echo '<h3><a href="admin_cp.php">&laquo; Zur&uuml;ck zum Admin-Panel</a></h3><br />';
                                                echo '
                                                <h1>Charakter&uuml;bersicht &nbsp;&nbsp; | &nbsp;&nbsp; <a href="admin_cp.php?mode=7&id='.$uID.'">'.$aName.'</a></h1>
                                                <p>
                                                <img style="float: left; margin-right: 30px;" src="images/char/face/'.$playFace.'.png" />
                                                <img style="float: right; margin-right: 60px;" src="images/char/class/'.$cClassImg.'.png" />
                                                <img style="float: right; margin-right: -70px; margin-top: 5px;" src="images/char/'.$cCountry.'.png" />
                                                <small style="float: right; margin-right: 5px;">'.$cClass.'</small>
                                                <small style="float: right; margin-right: -'.$cLevelPx.'px; margin-top: 15px;">Level '.$cLevel.'</small>
                                                <a href="admin_cp.php?mode=8&cid='.$cID.'">'.$cName.'</a>&nbsp;&nbsp;&nbsp;&nbsp;'.$cOnlineTxt.'<br /><small>['.$gName.']</small>
                                                </p>
                                                <p>
                                                <table class="table" cellpadding="0" cellspacing="0">
                                                <thead>
                                                <tr>
                                                <th>&nbsp;</th>
                                                <th>&nbsp;</th>
                                                <th>&nbsp;</th>
                                                </tr>
                                                </thead>
                                                <tbody>';
                                                if($cOnline == 0 && $session_right > 1)
                                                {
                                                    echo '<tr>
                                                    <td class="cinfo"><span class="gold_bg">&raquo;</span> Charaktername</td>
                                                    <td class="ctext">'.$cName.'</td>
                                                    <td><a href="admin_cp.php?mode=8&action=11&cid='.$cID.'">Umbenennen</td>
                                                    </tr>
                                                    <tr>
                                                    <td class="cinfo"><span class="gold_bg">&raquo;</span> Erstellt am</td>
                                                    <td class="ctext">'.$cCreation.'</td>
                                                    <td><span>unm&ouml;glich</span></td>
                                                    </tr>
                                                    <tr>
                                                    <td class="cinfo"><span class="gold_bg">&raquo;</span> Ehre</td>
                                                    <td class="ctext">'.$cTotalPoints.'</td>
                                                    <td><a href="admin_cp.php?mode=8&action=1&cid='.$cID.'">Bearbeiten</a></td>
                                                    </tr>
                                                    <tr>
                                                    <td class="cinfo"><span class="gold_bg">&raquo;</span> Leistungspunkte</td>
                                                    <td class="ctext">'.$cUsePoints.'</td>
                                                    <td><a href="admin_cp.php?mode=8&action=2&cid='.$cID.'">Bearbeiten</a></td>
                                                    </tr>
                                                    <tr>
                                                    <td class="cinfo"><span class="gold_bg">&raquo;</span> Erfahrungspunkte</td>
                                                    <td class="ctext">'.$cExp.'</td>
                                                    <td><a href="admin_cp.php?mode=8&action=3&cid='.$cID.'">Bearbeiten</a></td>
                                                    </tr>
                                                    <tr>
                                                    <td class="cinfo"><span class="gold_bg">&raquo;</span> Skillpunkte</td>
                                                    <td class="ctext">'.$cSkillPoints.'</td>
                                                    <td><a href="admin_cp.php?mode=8&action=4&cid='.$cID.'">Bearbeiten</a></td>
                                                    </tr>
                                                    <tr>
                                                    <td class="cinfo"><span class="gold_bg">&raquo;</span> Level</td>
                                                    <td class="ctext">'.$cLevel.'</td>
                                                    <td><a href="admin_cp.php?mode=8&action=5&cid='.$cID.'">Bearbeiten</a></td>
                                                    </tr>
                                                    <tr>
                                                    <td class="cinfo"><span class="gold_bg">&raquo;</span> Rasse</td>
                                                    <td class="ctext">'.$cRace.'</td>
                                                    <td><a href="admin_cp.php?mode=8&action=6&cid='.$cID.'">Bearbeiten</a></td>
                                                    </tr>
                                                    <tr>
                                                    <td class="cinfo"><span class="gold_bg">&raquo;</span> Klasse</td>
                                                    <td class="ctext">'.$cClass.'</td>
                                                    <td><span>kritisch</span</td>
                                                    </tr>
                                                    <tr>
                                                    <td class="cinfo"><span class="gold_bg">&raquo;</span> Geschlecht</td>
                                                    <td class="ctext">'.$cSex.'</td>
                                                    <td><a href="admin_cp.php?mode=8&action=8&cid='.$cID.'">Bearbeiten</a></td>
                                                    </tr>
                                                    <tr>
                                                    <td class="cinfo"><span class="gold_bg">&raquo;</span> Land</td>
                                                    <td class="ctext" style="text-transform: capitalize;">'.$cCountry.'</td>
                                                    <td><span>kritisch</span></td>
                                                    </tr>
                                                    <tr>
                                                    <td class="cinfo"><span class="gold_bg">&raquo;</span> Geld</td>
                                                    <td class="ctext">'.$cGold.' Gold, '.$cSilver.' Silber, '.$cCooper.' Bronze</td>
                                                    <td><a href="admin_cp.php?mode=8&action=10&cid='.$cID.'">Bearbeiten</a></td>
                                                    </tr>';
                                                }
                                                else
                                                {
                                                    echo '<tr>
                                                    <td class="cinfo"><span class="gold_bg">&raquo;</span> Charaktername</td>
                                                    <td class="ctext">'.$cName.'</td>
                                                    <td><span>unm&ouml;glich</span></td>
                                                    </tr>
                                                    <tr>
                                                    <td class="cinfo"><span class="gold_bg">&raquo;</span> Erstellt am</td>
                                                    <td class="ctext">'.$cCreation.'</td>
                                                    <td><span>unm&ouml;glich</span></td>
                                                    </tr>
                                                    <tr>
                                                    <td class="cinfo"><span class="gold_bg">&raquo;</span> Ehre</td>
                                                    <td class="ctext">'.$cTotalPoints.'</td>
                                                    <td><span>unm&ouml;glich</span></td>
                                                    </tr>
                                                    <tr>
                                                    <td class="cinfo"><span class="gold_bg">&raquo;</span> Leistungspunkte</td>
                                                    <td class="ctext">'.$cUsePoints.'</td>
                                                    <td><span>unm&ouml;glich</span></td>
                                                    </tr>
                                                    <tr>
                                                    <td class="cinfo"><span class="gold_bg">&raquo;</span> Erfahrungspunkte</td>
                                                    <td class="ctext">'.$cExp.'</td>
                                                    <td><span>unm&ouml;glich</span></td>
                                                    </tr>
                                                    <tr>
                                                    <td class="cinfo"><span class="gold_bg">&raquo;</span> Skillpunkte</td>
                                                    <td class="ctext">'.$cSkillPoints.'</td>
                                                    <td><span>unm&ouml;glich</span></td>
                                                    </tr>
                                                    <tr>
                                                    <td class="cinfo"><span class="gold_bg">&raquo;</span> Level</td>
                                                    <td class="ctext">'.$cLevel.'</td>
                                                    <td><span>unm&ouml;glich</span></td>
                                                    </tr>
                                                    <tr>
                                                    <td class="cinfo"><span class="gold_bg">&raquo;</span> Rasse</td>
                                                    <td class="ctext">'.$cRace.'</td>
                                                    <td><span>unm&ouml;glich</span></td>
                                                    </tr>
                                                    <tr>
                                                    <td class="cinfo"><span class="gold_bg">&raquo;</span> Klasse</td>
                                                    <td class="ctext">'.$cClass.'</td>
                                                    <td><span>kritisch</span</td>
                                                    </tr>
                                                    <tr>
                                                    <td class="cinfo"><span class="gold_bg">&raquo;</span> Geschlecht</td>
                                                    <td class="ctext">'.$cSex.'</td>
                                                    <td><span>unm&ouml;glich</span></td>
                                                    </tr>
                                                    <tr>
                                                    <td class="cinfo"><span class="gold_bg">&raquo;</span> Land</td>
                                                    <td class="ctext" style="text-transform: capitalize;">'.$cCountry.'</td>
                                                    <td><span>kritisch</span></td>
                                                    </tr>
                                                    <tr>
                                                    <td class="cinfo"><span class="gold_bg">&raquo;</span> Geld</td>
                                                    <td class="ctext">'.$cGold.' Gold, '.$cSilver.' Silber, '.$cCooper.' Bronze</td>
                                                    <td><span>unm&ouml;glich</span></td>
                                                    </tr>';
                                                }

                                                echo'</tbody>
                                                </table></p>';
                                            }
                                            else
                                            {
                                                echo '<br /><p>Der Charakter existiert nicht!</p>';
                                            }
                                        }
                                    }
                                    if(isset($_GET['id']) && !isset($_GET['cid']))
                                    {
                                        $form = false;

                                        $userID = $_GET['id'];
                                        $userID = mysql_real_escape_string($userID);

                                        $sql = "SELECT dwCharID, szName, bSex, bCountry, bRace, bClass, bHair, bFace, bLevel, dCreateDate FROM TGAME_GSP.dbo.TCHARTABLE WHERE dwUserID = ".$userID."";
                                        $q = odbc_exec($gcon, $sql);

                                        echo '
                                        <h3><a href="admin_cp.php?mode=7&id='.$userID.'">&laquo; Zur&uuml;ck zur Account&uuml;bersicht</a></h3><br />
                                        <table class="table" cellspacing="0"><thead>
                                        <tr>
                                        <th></th>
                                        <th>Spieler</th>
                                        <th>Level</th>
                                        <th>Erstellt am</th>
                                        <th>Land</th>
                                        </tr>
                                        </thead><tbody>';

                                        if(odbc_num_rows($q) != 0)
                                        {
                                            while($dat = odbc_fetch_array($q))
                                            {
                                                $cID = $dat['dwCharID'];
                                                $cName = $dat['szName'];
                                                $cCountry = $dat['bCountry'];
                                                $cSex = $dat['bSex'];
                                                $cRace = $dat['bRace'];
                                                $cClass = $dat['bClass'];
                                                $cHair = $dat['bHair'];
                                                $cFace = $dat['bFace'];
                                                $cLevel = $dat['bLevel'];
                                                $cCreation = $dat['dCreateDate'];

                                                $crea_array = explode("-", $cCreation);
                                                $crea_del = explode(" ", $crea_array[2]);
                                                $cCreation = $crea_del[0] .".". $crea_array[1] .".". $crea_array[0];

                                                if($cClass == 0)
                                                    $cClass = "Krieger";
                                                else if($cClass == 1)
                                                    $cClass = "Schattenl&auml;ufer";
                                                else if($cClass == 2)
                                                    $cClass = "Bogensch&uuml;tze";
                                                else if($cClass == 3)
                                                    $cClass = "Magier";
                                                else if($cClass == 4)
                                                    $cClass = "Priester";
                                                else if($cClass == 5)
                                                    $cClass = "Beschw&ouml;rer";

                                                if($cCountry == 4 || $cCountry == 2)
                                                    $cCountry = "gor";
                                                else if($cCountry == 1)
                                                    $cCountry = "deri";
                                                else if($cCountry == 0)
                                                    $cCountry = "valo";

                                                $playFace = $cRace . $cSex . $cHair . $cFace;

                                                echo '<tr>
                                                <td><img src="images/char/face/'.$playFace.'.png" /></td>
                                                <td><a href="admin_cp.php?mode=8&cid='.$cID.'">'.$cName.'</a><br />
                                                <small>'.$cClass.'</small></td>
                                                <td>'.$cLevel.'</td>
                                                <td>'.$cCreation.'</td>
                                                <td><img src="images/char/'.$cCountry.'.png" /></td>
                                                </tr>';
                                            }
                                        }
                                        else
                                        {
                                            echo '
                                            <tr><td></td>
                                            <td>Keine Ergebnisse!</td>
                                            <td></td><td></td>
                                            </tr>
                                            ';
                                        }

                                        echo '</tbody></table>';
                                    }
                                    if(isset($_GET['s']) && !isset($_GET['id']))
                                    {
                                        $form = false;

                                        $page = 1;
                                        if(isset($_GET['p']))
                                        {
                                            $page = $_GET['p'];
                                        }
                                        $entry = 15;
                                        $start = $page * $entry - $entry;

                                        $charName = $_GET['s'];

                                        $charName = mysql_real_escape_string($charName);

                                        $sql = "SELECT TOP ".$entry." * FROM
                                        (SELECT TOP ".($start + $entry)." dwCharID, szName, bSex, bCountry, bRace, bClass, bHair, bFace, bLevel FROM TGAME_GSP.dbo.TCHARTABLE WHERE szName LIKE '%".$charName."%' ORDER BY dwCharID ASC)
                                        AS TChar ORDER BY dwCharID DESC";
                                        $q = odbc_exec($gcon, $sql);

                                        echo '<h3><a href="admin_cp.php?mode=8">&laquo; Zur&uuml;ck zur Suche</a></h3><br />
                                        <table class="table" cellspacing="0"><thead>
                                        <tr>
                                        <th></th>
                                        <th>Charakter</th>
                                        <th>Level</th>
                                        <th>Land</th>
                                        </tr>
                                        </thead><tbody>';

                                        if(odbc_num_rows($q) != 0)
                                        {
                                            while($dat = odbc_fetch_array($q))
                                            {
                                                $cID = $dat['dwCharID'];
                                                $cName = $dat['szName'];
                                                $cCountry = $dat['bCountry'];
                                                $cSex = $dat['bSex'];
                                                $cRace = $dat['bRace'];
                                                $cClass = $dat['bClass'];
                                                $cHair = $dat['bHair'];
                                                $cFace = $dat['bFace'];
                                                $cLevel = $dat['bLevel'];

                                                if($cClass == 0)
                                                    $cClass = "Krieger";
                                                else if($cClass == 1)
                                                    $cClass = "Schattenl&auml;ufer";
                                                else if($cClass == 2)
                                                    $cClass = "Bogensch&uuml;tze";
                                                else if($cClass == 3)
                                                    $cClass = "Magier";
                                                else if($cClass == 4)
                                                    $cClass = "Priester";
                                                else if($cClass == 5)
                                                    $cClass = "Beschw&ouml;rer";

                                                if($cCountry == 4 || $cCountry == 2)
                                                    $cCountry = "gor";
                                                else if($cCountry == 1)
                                                    $cCountry = "deri";
                                                else if($cCountry == 0)
                                                    $cCountry = "valo";

                                                $playFace = $cRace . $cSex . $cHair . $cFace;

                                                echo '<tr>
                                                <td><img src="images/char/face/'.$playFace.'.png" /></td>
                                                <td><a href="admin_cp.php?mode=8&cid='.$cID.'">'.$cName.'</a><br />
                                                <small>'.$cClass.'</small></td>
                                                <td>'.$cLevel.'</td>
                                                <td><img src="images/char/'.$cCountry.'.png" /></td>
                                                </tr>';
                                            }
                                            odbc_free_result($q);
                                        }
                                        else
                                        {
                                            echo '
                                            <tr><td></td>
                                            <td>Keine Ergebnisse!</td>
                                            <td></td><td></td>
                                            </tr>
                                            ';
                                        }

                                        echo '</tbody></table>';
                                    }

                                    if($form)
                                    {
                                        echo '<h3><a href="admin_cp.php">&laquo; Zur&uuml;ck zum Admin-Panel</a></h3><br />';
                                        echo '<h4>Charakter suchen</h4>';
                                        echo '
                                        <form action="admin_cp.php" method="get">
                                        <label>Charaktername <small><em>(required)</em></small></label>
                                        <input type="text" name="s" id="charname" />
                                        <input type="hidden" name="mode" id="hidden" value="8" /><br /><br />
                                        <input type="submit" value="Charakter suchen" class="read_more2" />
                                        </form>
                                        ';
                                    }
                                }

                                else if($_GET['mode'] == 9)
                                {
                                    $form = true;
                                    if(isset($_GET['h']))
                                    {
                                        $hash = $_GET['h'];

                                        $form = false;
                                    }

                                    if(isset($_GET['h']) && isset($_GET['action']) && $_GET['action'] == 2)
                                    {
                                        $hash = $_GET['h'];

                                        $sql = "UPDATE tickets SET Solved = 1 WHERE hash = '".$hash."'";
                                        $q = mysql_query($sql);

                                        echo '<p>Das Ticket wurde als gel&ouml;st markiert!<br />
                                        <a href="./admin_cp.php">&raquo; Zur&uuml;ck zum Admin-Panel</a></p>';
                                        echo '<meta http-equiv="refresh" content="1; URL=./admin_cp.php">';
                                        $form = false;
                                    }

                                    if(isset($_GET['h']) && isset($_POST['text']))
                                    {
                                        $text = $_POST['text'];
                                        $md5 = $_GET['h'];

                                        if($text != null)
                                        {
                                            $date = date('Y-m-d H:i:s');

                                            $text = htmlentities($text);

                                            $sql = "INSERT INTO tickets (UserID, UserName, Text, Hash, Date)
                                            VALUES(".$session_id.", '".$session_user."', '".$text."', '".$md5."', '".$date."')";
                                            $q = mysql_query($sql);

                                            $sql0 = "UPDATE tickets SET AnswerID = '".$session_id."' WHERE hash = '".$md5."'";
                                            $q0 = mysql_query($sql0);

                                            echo '<p>Die Antwort wurde erfolgreich erstellt!<br />
                                            <a href="./admin_cp.php?mode=9&h='.$md5.'">&raquo; Zur&uuml;ck zum Ticket</a><br />
                                            <a href="./admin_cp.php">&raquo; Zur&uuml;ck zum Admin-Panel</a></p>';
                                            echo '<meta http-equiv="refresh" content="1; URL=./admin_cp.php?mode=9&h='.$md5.'">';
                                            $form = false;
                                        }

                                        else
                                        {
                                            $error = 'Es wurde kein Text eingegeben!';
                                        }
                                    }

                                    if($form)
                                    {
                                        echo '<h3><a href="admin_cp.php">&laquo; Zur&uuml;ck zum Admin-Panel</a></h3><br />';
                                        echo '<h4>Support-Tickets</h4>';
                                        echo '<ul>';

                                        if(isset($_GET['id']) && $_GET['action'] == 1)
                                        {
                                            $sql = "SELECT hash, title, date, solved FROM tickets WHERE Solved = 0 AND AnswerID = ".$session_id."";
                                        }
                                        else
                                        {
                                            $sql = "SELECT hash, title, date, solved FROM tickets WHERE Solved = 0 AND (AnswerID = 0 OR AnswerID = ".$session_id.")";
                                        }

                                        $q = mysql_query($sql);
                                        while($dat = mysql_fetch_assoc($q))
                                        {
                                            $hash = $dat['hash'];
                                            $title = $dat['title'];
                                            $date = $dat['date'];

                                            $date_array = explode("-", $date);
                                            $time_array = explode(" ", $date_array[2]);
                                            $sec_array = explode(":", $time_array[1]);
                                            $date = $time_array[0].".".$date_array[1].".".$date_array[0]." ".$sec_array[0].":".$sec_array[1];

                                            if($title != null)
                                            {
                                                echo '<li><a href="admin_cp.php?mode=9&h='.$hash.'">'.$title.'</a> vom '.$date.'</li><hr />';
                                            }

                                        }
                                        if(mysql_num_rows($q) == 0)
                                        {
                                            echo '<p>Keine Tickets vorhanden!<br /><a href="admin_cp.php">&raquo; Zur&uuml;ck zum Admin-Panel</a></p>';
                                        }

                                        echo '</ul>';
                                    }
                                }
                                else if($_GET['mode'] == 10 && $session_right == 4)
                                {
                                    $form = true;

                                    if(isset($_GET['action']) && $_GET['action'] == 1)
                                    {
                                        if(isset($_GET['id']))
                                        {
                                            $id = $_GET['id'];

                                            if(isset($_POST['itemID']))
                                            {
                                                $itemID = $_POST['itemID'];
                                                $itemName = $_POST['itemName'];
                                                $amount = $_POST['itemAmount'];
                                                $text = $_POST['itemText'];
                                                $price = $_POST['itemPrice'];

                                                $itemID = mysql_real_escape_string($itemID);
                                                $itemName = mysql_real_escape_string($itemName);
                                                $amount = mysql_real_escape_string($amount);
                                                $text = mysql_real_escape_string($text);
                                                $price = mysql_real_escape_string($price);

                                                $sql = "UPDATE cashshop SET ItemID = '".$itemID."', ItemName = '".$itemName."', Amount = '".$amount."', Text = '".$text."',
                                                Price = '".$price."' WHERE ID = '".$id."'";
                                                $q = mysql_query($sql);

                                                echo '<p>Das Item wurde erfolgreich ge&auml;ndert!<br /><a href="admin_cp.php">&raquo; Zur&uuml;ck zum Admin-Panel</a>
                                                <br /><a href="admin_cp.php?mode=10">&raquo; Zur&uuml;ck zur Cashshop-&Uuml;bersicht</a></p>';

                                                $form = false;
                                            }
                                            else
                                            {
                                                $id = mysql_real_escape_string($id);

                                                $sql = "SELECT itemID, itemName, amount, text, price FROM cashshop WHERE ID = '".$id."'";
                                                $q = mysql_query($sql);
                                                $dat = mysql_fetch_assoc($q);

                                                $itemID = $dat['itemID'];
                                                $itemName = $dat['itemName'];
                                                $amount = $dat['amount'];
                                                $text = $dat['text'];
                                                $price = $dat['price'];

                                                echo '<h3><a href="admin_cp.php?mode=10&action=1">&laquo; Zur&uuml;ck zur Item-&Uuml;bersicht</a></h3><br />';
                                                echo '<h4>Item bearbeiten</h4>
                                                <form action="admin_cp.php?mode=10&action=1&id='.$id.'" method="post">
                                                <label>ItemID <small><em>(required)</em></small></label>
                                                <input type="text" name="itemID" id="itemID" value="'.$itemID.'" />
                                                <label>Itemname <small><em>(required)</em></small></label>
                                                <input type="text" name="itemName" id="itemName" value="'.$itemName.'" />
                                                <label>Anzahl <small><em>(required)</em></small></label>
                                                <input type="text" name="itemAmount" id="itemAmount" value="'.$amount.'" />
                                                <label>Preis <small><em>(required)</em></small></label>
                                                <input type="text" name="itemPrice" id="itemPrice" value="'.$price.'" />
                                                <label>Text <small><em>(required)</em></small></label>
                                                <textarea cols="65" name="itemText" id="itemText">'.$text.'</textarea><br /><br />
                                                <input type="submit" value="&Auml;nderungen speichern" class="read_more2" />
                                                </form>
                                                ';

                                                $form = false;
                                            }
                                        }

                                        if($form)
                                        {
                                            $sql = "SELECT ID, itemName, amount, text, price FROM cashshop";
                                            $q = mysql_query($sql);

                                            echo '<h3><a href="admin_cp.php?mode=10">&laquo; Zur&uuml;ck zur Cashshop-&Uuml;bersicht</a></h3><br />';
                                            echo '
                                            <table border="0">
                                            <tr>
                                            <th></th>
                                            <th></th>
                                            </tr>';

                                            $tCounter = 1;

                                            while($dat = mysql_fetch_assoc($q))
                                            {
                                                $tCounter = $tCounter + 1;

                                                $csID = $dat['ID'];
                                                $itemName = $dat['itemName'];
                                                $amount = $dat['amount'];
                                                $text = $dat['text'];
                                                $price = $dat['price'];

                                                $text = wordwrap($text);

                                                if($tCounter == 2)
                                                {
                                                    echo '</tr>';
                                                    echo '<tr>';
                                                    $tCounter = 0;
                                                }

                                                echo '
                                                <td><div id="itembox"><b>'.$amount.'x</b> '.$itemName.'<small>'.$text.'</small>
                                                <small id="price">Preis: '.$price.' MS</small>
                                                <form action="admin_cp.php?mode=10" method="get">
                                                <input type="hidden" name="id" value="'.$csID.'" />
                                                <input type="hidden" name="mode" value="10" />
                                                <input type="hidden" name="action" value="1" />
                                                <input type="submit" value="Bearbeiten" />
                                                </form>
                                                </div></td>
                                                ';
                                            }
                                            echo '</table>';
                                        }
                                    }
                                    else if(isset($_GET['action']) && $_GET['action'] == 2)
                                    {
                                        if(isset($_POST['itemID']))
                                        {
                                            $itemID = $_POST['itemID'];
                                            $itemName = $_POST['itemName'];
                                            $amount = $_POST['itemAmount'];
                                            $text = $_POST['itemText'];
                                            $price = $_POST['itemPrice'];

                                            $itemID = mysql_real_escape_string($itemID);
                                            $itemName = mysql_real_escape_string($itemName);
                                            $amount = mysql_real_escape_string($amount);
                                            $text = mysql_real_escape_string($text);
                                            $price = mysql_real_escape_string($price);

                                            $sql = "INSERT INTO cashshop (ItemID, ItemName, Amount, Text, Price) VALUES (
                                            '".$itemID."', '".$itemName."', '".$amount."', '".$text."', '".$price."')";
                                            $q = mysql_query($sql);

                                            echo '<p>Das Item wurde erfolgreich hinzugef&uuml;gt!<br /><a href="admin_cp.php">&raquo; Zur&uuml;ck zum Admin-Panel</a>
                                            <br /><a href="admin_cp.php?mode=10">&raquo; Zur&uuml;ck zur Cashshop-&Uuml;bersicht</a></p>';

                                            $form = false;
                                        }

                                        if($form)
                                        {
                                            echo '<h3><a href="admin_cp.php?mode=10&action=1">&laquo; Zur&uuml;ck zur Cashshop-&Uuml;bersicht</a></h3><br />';
                                            echo '<h4>Item hinzuf&uuml;gen</h4>
                                            <form action="admin_cp.php?mode=10&action=2" method="post">
                                            <label>ItemID <small><em>(required)</em></small></label>
                                            <input type="text" name="itemID" id="itemID" />
                                            <label>Itemname <small><em>(required)</em></small></label>
                                            <input type="text" name="itemName" id="itemName" />
                                            <label>Anzahl <small><em>(required)</em></small></label>
                                            <input type="text" name="itemAmount" id="itemAmount" />
                                            <label>Preis <small><em>(required)</em></small></label>
                                            <input type="text" name="itemPrice" id="itemPrice" />
                                            <label>Text <small><em>(required)</em></small></label>
                                            <textarea cols="65" name="itemText" id="itemText"></textarea><br /><br />
                                            <input type="submit" value="&Auml;nderungen speichern" class="read_more2" />
                                            </form>
                                            ';
                                        }
                                    }
                                    else if(isset($_GET['action']) && $_GET['action'] == 3)
                                    {
                                        if(isset($_GET['id']))
                                        {
                                            $id = $_GET['id'];

                                            $id = mysql_real_escape_string($id);

                                            echo '
                                            <form action="admin_cp.php?mode=10&action=3" method="post">
                                            <label>Entg&uuml;ltig l&ouml;schen?</label>
                                            <input type="hidden" name="id" id="id" value="'.$id.'" /><br /><br />
                                            <input type="submit" value="L&ouml;schen" class="read_more2" />
                                            </form>
                                            ';

                                            $form = false;
                                        }

                                        if(isset($_POST['id']))
                                        {
                                            $id = $_POST['id'];

                                            $id = mysql_real_escape_string($id);

                                            $sql = "DELETE FROM cashshop WHERE ID = '".$id."'";
                                            $q = mysql_query($sql);

                                            echo '
                                            <p>Das Item wurde erfolgreich gel&ouml;scht!<br /><a href="admin_cp.php">&raquo; Zur&uuml;ck zum Admin-Panel</a><br />
                                            <a href="admin_cp.php?mode=10">&raquo; Zur&uuml;ck zur Cashshop-&Uuml;bersicht</a></p>
                                            ';

                                            $form = false;
                                        }

                                        if($form)
                                        {
                                            $sql = "SELECT ID, itemName, amount, text, price FROM cashshop";
                                            $q = mysql_query($sql);

                                            echo '<h3><a href="admin_cp.php?mode=10">&laquo; Zur&uuml;ck zur Cashshop-&Uuml;bersicht</a></h3><br />';
                                            echo '
                                            <table border="0">
                                            <tr>
                                            <th></th>
                                            <th></th>
                                            </tr>';

                                            $tCounter = 1;

                                            while($dat = mysql_fetch_assoc($q))
                                            {
                                                $tCounter = $tCounter + 1;

                                                $csID = $dat['ID'];
                                                $itemName = $dat['itemName'];
                                                $amount = $dat['amount'];
                                                $text = $dat['text'];
                                                $price = $dat['price'];

                                                $text = wordwrap($text);

                                                if($tCounter == 2)
                                                {
                                                    echo '</tr>';
                                                    echo '<tr>';
                                                    $tCounter = 0;
                                                }

                                                echo '
                                                <td><div id="itembox"><b>'.$amount.'x</b> '.$itemName.'<small>'.$text.'</small>
                                                <small id="price">Preis: '.$price.' MS</small>
                                                <form action="admin_cp.php?mode=10" method="get">
                                                <input type="hidden" name="id" value="'.$csID.'" />
                                                <input type="hidden" name="mode" value="10" />
                                                <input type="hidden" name="action" value="3" />
                                                <input type="submit" value="L&ouml;schen" />
                                                </form>
                                                </div></td>
                                                ';
                                            }
                                            echo '</table>';
                                        }
                                    }
                                    else
                                    {
                                        echo '<h3><a href="admin_cp.php">&laquo; Zur&uuml;ck zum Admin-Panel</a></h3><br />';
                                        echo '
                                        <h4>Cashshop bearbeiten</h4>
                                        <p><a href="admin_cp.php?mode=10&action=2">&raquo; Item hinzuf&uuml;gen</a></p>
                                        <p><a href="admin_cp.php?mode=10&action=1">&raquo; Item bearbeiten</a></p>
                                        <p><a href="admin_cp.php?mode=10&action=3">&raquo; Item l&ouml;schen</a></p>
                                        ';
                                    }
                                }
                                else if($_GET['mode'] == 11 && $session_right > 2)
                                {
                                    $form = true;

                                    if(isset($_POST['username']))
                                    {
                                        $user = $_POST['username'];
                                        $itemID = $_POST['itemID'];
                                        $amount = $_POST['amount'];
                                        $title = $_POST['title'];
                                        $text = $_POST['text'];

                                        $user = mysql_real_escape_string($user);
                                        $itemID = mysql_real_escape_string($itemID);
                                        $amount = mysql_real_escape_string($amount);
                                        $title = mysql_real_escape_string($title);
                                        $text = mysql_real_escape_string($text);

                                        $title = htmlentities($title);
                                        $text = htmlentities($text);

                                        $sql0 = "SELECT dwCharID FROM TGAME_GSP.dbo.TCHARTABLE WHERE szName = '".$user."'";
                                        $q0 = odbc_exec($gcon, $sql0);
                                        if(odbc_num_rows($q0) != 0)
                                        {
                                            $sql = "EXEC TGAME_GSP.dbo.TEventItemGive
                                            @szName = '".$user."' , @wItemID = '".$itemID."', @bCount = '".$amount."', @szMessage = '".$text."', @szTitle = '".$title."'";
                                            $q = odbc_exec($gcon, $sql);

                                            echo '<p>Das Item wurde erfolgreich verschickt!<br /><a href="admin_cp.php">&raquo; Zur&uuml;ck zum Admin-Panel</a></p>';
                                            echo '<meta http-equiv="refresh" content="1; URL=./admin_cp.php">';
                                            $form = false;
                                        }
                                        else
                                        {
                                            $error = 'Der Charakter existiert nicht!';
                                        }
                                    }

                                    if($form)
                                    {
                                        echo '<h3><a href="admin_cp.php">&laquo; Zur&uuml;ck zum Admin-Panel</a></h3><br />';
                                        echo '<h4>Item verschicken</h4>';
                                        echo '
                                        <form action="admin_cp.php?mode=11" method="post">
                                        <label>Charaktername <small><em>(required)</em></small></label>
                                        <input type="text" name="username" id="username" />
                                        <label>Item-ID <small><em>(required)</em></small></label>
                                        <input type="text" name="itemID" id="itemID" />
                                        <label>Menge <small><em>(required)</em></small></label>
                                        <input type="text" name="amount" id="amount" />
                                        <label>Brief-Titel <small><em>(required)</em></small></label>
                                        <input type="text" name="title" id="title" />
                                        <label>Brief-Text <small><em>(required)</em></small></label>
                                        <textarea cols="65" name="text" id="text"></textarea><br /><br />
                                        <input type="submit" value="Item verschicken" class="read_more2" />
                                        </form>
                                        ';

                                        if(isset($error))
                                        {
                                            echo '<br /><p>'.$error.'</p>';
                                        }
                                    }
                                }
                                else if($_GET['mode'] == 12 && $session_right == 4)
                                {
                                    $form = true;
                                    if(isset($_POST['username']))
                                    {
                                        $user = $_POST['username'];

                                        $sql0 = "SELECT dwCharID FROM TGAME_GSP.dbo.TCHARTABLE WHERE szName = '".$user."'";
                                        $q0 = odbc_exec($gcon, $sql0);
                                        if(odbc_num_rows($q0) != 0)
                                        {
                                            $cID = odbc_fetch_array($q0);
                                            $cID = $cID['dwCharID'];

                                            $sql = "SELECT dwCharID FROM TGLOBAL_GSP.dbo.TCURRENTUSER WHERE dwCharID = '".$cID."'";
                                            $q = odbc_exec($gcon, $sql);
                                            $cOnline = odbc_num_rows($q);

                                            if($cOnline == 0)
                                            {
                                                $sql1 = "EXEC TGAME_GSP.dbo.TTESTGmItemGive @szName = '".$user."'";
                                                $q1 = odbc_exec($gcon, $sql1);

                                                echo '<p>Das GM-Equipment wurde erfolgreich vergeben!<br /><a href="admin_cp.php">&raquo; Zur&uuml;ck zum Admin-Panel</a></p>';
                                                echo '<meta http-equiv="refresh" content="1; URL=./admin_cp.php">';
                                                $form = false;
                                            }
                                            else
                                            {
                                                $error = 'Der Charakter muss ausgeloggt sein, um das GM-Equipment zu vergeben.';
                                            }
                                        }
                                        else
                                        {
                                            $error = 'Der Charakter existiert nicht.';
                                        }
                                    }

                                    if($form)
                                    {
                                        echo '<h3><a href="admin_cp.php">&laquo; Zur&uuml;ck zum Admin-Panel</a></h3><br />';
                                        echo '<h4>GM-Equipment vergeben</h4>';
                                        echo '
                                        <form action="admin_cp.php?mode=12" method="post">
                                        <label>Charaktername <small><em>(required)</em></small></label>
                                        <input type="text" name="username" id="username" /><br /><br />
                                        <input type="submit" value="GM-Equipment vergeben" class="read_more2" />
                                        </form>
                                        ';

                                        if(isset($error))
                                        {
                                            echo '<br /><p>'.$error.'</p>';
                                        }
                                    }
                                }
                                else if($_GET['mode'] == 13 && $session_right == 4)
                                {
                                    $form = true;
                                    if(isset($_POST['title']))
                                    {
                                        $id = $_GET['id'];

                                        $title = $_POST['title'];
                                        $type = $_POST['type'];
                                        $img = $_POST['img'];
                                        $text = $_POST['text'];

                                        $title = mysql_real_escape_string($title);
                                        $img = mysql_real_escape_string($img);
                                        $text = mysql_real_escape_string($text);

                                        $img = $img . ".png";

                                        $text = htmlentities($text);

                                        $sql = "UPDATE news SET hot = '".$type."', title = '".$title."', image = '".$img."', text = '".$text."' WHERE id = ".$id."";
                                        $q = mysql_query($sql);

                                        echo '<p>Die News wurden erfolgreich ge&auml;ndert! <br /><a href="admin_cp.php">Zur&uuml;ck zum Admin-CP</a></p>';
                                        echo '<meta http-equiv="refresh" content="1; url=admin_cp.php">';

                                        $form = false;
                                    }

                                    if(isset($_GET['id']) && !isset($_POST['title']))
                                    {
                                        $id = $_GET['id'];

                                        $id = mysql_real_escape_string($id);

                                        $sql = "SELECT hot, title, text, image FROM news WHERE ID = ".$id."";
                                        $q = mysql_query($sql);
                                        $dat = mysql_fetch_assoc($q);

                                        $title = $dat['title'];
                                        $text = $dat['text'];
                                        $img = $dat['image'];
                                        $hot = $dat['hot'];

                                        $img = str_replace('.png', '', $img);
                                        $text = nl2br($text);

                                        echo '<h3><a href="admin_cp.php">&laquo; Zur&uuml;ck zum Admin-Panel</a></h3><br />';
                                        echo '<h4>News bearbeiten</h4>';
                                        echo '
                                        <form action="admin_cp.php?mode=13&id='.$id.'" method="post">
                                        <label>Titel <small><em>(required)</em></small></label>
                                        <input type="text" name="title" id="title" value="'.$title.'" />
                                        <label>Typ <small><em>(required)</em></small></label>';
                                        if($hot == 0)
                                            echo '<input type="radio" name="type" id="type" value="0" checked="true" /> General<br />
                                            <input type="radio" name="type" id="type" value="1" /> Hot';
                                        else if($hot == 1)
                                            echo '<input type="radio" name="type" id="type" value="0" /> General<br />
                                            <input type="radio" name="type" id="type" value="1" checked="true" /> Hot';
                                        echo '
                                        <label>Bild-ID <small><em>(required)</em></small></label>
                                        <input type="text" name="img" id="img" value='.$img.' />
                                        <label>Text <small><em>(required)</em></small></label>
                                        <textarea cols="65" name="text" id="text">'.$text.'</textarea><br /><br />
                                        <input type="submit" value="News &auml;ndern" class="read_more2" />
                                        </form>
                                        ';

                                        $form = false;
                                    }

                                    if($form)
                                    {
                                        echo '<h3><a href="admin_cp.php">&laquo; Zur&uuml;ck zum Admin-Panel</a></h3><br />';
                                        echo '<h4>Objekt zum Bearbeiten w&auml;hlen</h4>';
                                        echo '<ul>';

                                        $sql =  "SELECT id, title FROM news";
                                        $q = mysql_query($sql);
                                        while($dat = mysql_fetch_assoc($q))
                                        {
                                            $id = $dat['id'];
                                            $title = $dat['title'];

                                            echo '<li><a href="admin_cp.php?mode=13&id='.$id.'">'.$title.'</a></li><hr />';
                                        }
                                        echo '</ul>';
                                    }
                                }
                                else if($_GET['mode'] == 14 && $session_right == 4)
                                {
                                    $form = true;
                                    if(isset($_POST['title']))
                                    {
                                        $id = $_GET['id'];

                                        $title = $_POST['title'];
                                        $img = $_POST['img'];
                                        $text = $_POST['text'];

                                        $title = mysql_real_escape_string($title);
                                        $img = mysql_real_escape_string($img);
                                        $text = mysql_real_escape_string($text);

                                        $img = $img . ".png";

                                        $text = htmlentities($text);

                                        $sql = "UPDATE hottest_news SET title = '".$title."', image = '".$img."', text = '".$text."' WHERE id = ".$id."";
                                        $q = mysql_query($sql);

                                        echo '<p>Die News wurden erfolgreich ge&auml;ndert! <br /><a href="admin_cp.php">Zur&uuml;ck zum Admin-CP</a></p>';
                                        echo '<meta http-equiv="refresh" content="1; url=admin_cp.php">';

                                        $form = false;
                                    }

                                    if(isset($_GET['id']) && !isset($_POST['title']))
                                    {
                                        $id = $_GET['id'];

                                        $id = mysql_real_escape_string($id);

                                        $sql = "SELECT title, text, image FROM hottest_news WHERE ID = ".$id."";
                                        $q = mysql_query($sql);
                                        $dat = mysql_fetch_assoc($q);

                                        $title = $dat['title'];
                                        $text = $dat['text'];
                                        $img = $dat['image'];

                                        $img = str_replace('.png', '', $img);
                                        $text = nl2br($text);

                                        echo '<h3><a href="admin_cp.php">&laquo; Zur&uuml;ck zum Admin-Panel</a></h3><br />';
                                        echo '<h4>(Slider) News bearbeiten</h4>';
                                        echo '
                                        <form action="admin_cp.php?mode=14&id='.$id.'" method="post">
                                        <label>Titel <small><em>(required)</em></small></label>
                                        <input type="text" name="title" id="title" value="'.$title.'" />
                                        <label>Bild-ID <small><em>(required)</em></small></label>
                                        <input type="text" name="img" id="img" value='.$img.' />
                                        <label>Text <small><em>(required)</em></small></label>
                                        <textarea cols="65" name="text" id="text">'.$text.'</textarea><br /><br />
                                        <input type="submit" value="News &auml;ndern" class="read_more2" />
                                        </form>
                                        ';

                                        $form = false;
                                    }

                                    if($form)
                                    {
                                        echo '<h3><a href="admin_cp.php">&laquo; Zur&uuml;ck zum Admin-Panel</a></h3><br />';
                                        echo '<h4>Objekt zum Bearbeiten w&auml;hlen</h4>';
                                        echo '<ul>';

                                        $sql =  "SELECT id, title FROM hottest_news";
                                        $q = mysql_query($sql);
                                        while($dat = mysql_fetch_assoc($q))
                                        {
                                            $id = $dat['id'];
                                            $title = $dat['title'];

                                            echo '<li><a href="admin_cp.php?mode=14&id='.$id.'">'.$title.'</a></li><hr />';
                                        }
                                        echo '</ul>';
                                    }
                                }
                            }
                            }
                        ?>
                    </div>
                    <!-- Body end -->

                    <?php

                        if(isset($_GET['mode']) && $_GET['mode'] == 9 && isset($_GET['h']) && !isset($_POST['text']) && !isset($_GET['action']))
                        {
                            $page = 1;
                            if(isset($_GET['p']))
                            {
                                $page = $_GET['p'];
                            }
                            $entry = 4;
                            $start = $page * $entry - $entry;

                            $sql = "SELECT username, text, date, solved FROM tickets WHERE hash = '".$hash."' AND Solved = 0 ORDER BY date DESC LIMIT $start, $entry";
                            $q = mysql_query($sql);

                            $sql0 = "SELECT title FROM tickets WHERE hash = '".$hash."' AND Solved = 0";
                            $q0 = mysql_query($sql0);

                            $dat0 = mysql_fetch_assoc($q0);
                            $title = $dat0['title'];

                            if(mysql_num_rows($q) != 0 || mysql_num_rows($q0) != 0)
                            {
                                echo '<h3><a href="admin_cp.php">&laquo; Zur&uuml;ck zum Admin-Panel</a></h3><br />';
                                echo '
                                <div id="comments">
                                <div class="header">Ticket //<span> <span class="cyan">'.$title.'</span></span></div>
                                ';

                                while($dat = mysql_fetch_assoc($q))
                                {
                                    $username = $dat['username'];
                                    $text = $dat['text'];
                                    $date = $dat['date'];
                                    $solved = $dat['solved'];

                                    $text = nl2br($text);

                                    $date_array = explode("-", $date);
                                    $time_array = explode(" ", $date_array[2]);
                                    $sec_array = explode(":", $time_array[1]);
                                    $date = $time_array[0].".".$date_array[1].".".$date_array[0]." ".$sec_array[0].":".$sec_array[1];

                                    echo '
                                    <ul>
                                	<li>
                                    	<img alt="" class="indent" src="./images/post/indent_dark.png">
                                    	<div class="avatar"><img alt="" src="./images/post/avatar_dark.png"></div>
                                        <div class="comment"><p><strong>'.$username.'</strong> <small>'.$date.'</small></p>
                                        '.$text.'
                                        </div><div class="clear"></div>
                                    </li>
                                    </ul>
                                    ';
                                }
                                echo '</div><div id="response">
                                <div class="header">Antworten // <a href="admin_cp.php?mode=9&h='.$hash.'&action=2">Als gel&ouml;st markieren</a></div>
                                <form action="admin_cp.php?mode=9&h='.$hash.'" method="post">
                                <label>Text <small><em>(required)</em></small></label>
                                <textarea cols="65" name="text" id="text"></textarea><br /><br />
                                <input type="submit" value="Antwort senden" class="read_more2" style="width:200px !important" />
                                </form>
                                </div>
                                ';

                                if(isset($error))
                                {
                                    echo '<br /><p>'.$error.'</p>';
                                }
                            }
                            else
                            {
                                echo '<h3><a href="admin_cp.php">&laquo; Zur&uuml;ck zum Admin-Panel</a></h3><br />';
                                echo '
                                <div id="comments">
                                <div class="header">Das Ticket existiert nicht!</div>
                                ';
                                echo '
                                <ul>
                                	<li>
                                    	<img alt="" class="indent" src="./images/post/indent_dark.png">
                                    	<div class="avatar"><img alt="" src="./images/post/avatar_dark.png"></div>
                                        <div class="comment"><p><strong>4Story</strong> <small>Jetzt</small></p>
                                        Das angegebene Ticket konnte nicht gefunden werden.<br />
                                        Entweder es hat nie existiert oder wurde von einem Admin gel&ouml;scht!
                                        </div><div class="clear"></div>
                                    </li>
                                </ul></div>';
                            }
                        }

                    ?>

                    <div class="clear"></div>
               </div>
               <ul id="pager">
                      <?php
                        if(isset($_GET['mode']) && $_GET['mode'] == 9 && isset($_GET['h']) && !isset($_POST['text']) && !isset($_GET['action']))
                        {
                            $q00 = mysql_query("SELECT UserID FROM tickets WHERE UserID = '".$session_id."' AND hash = '".$hash."'");
                            $count = mysql_num_rows($q00);
                            $pages = $count / $entry;
                            if($page > 1)
                            {
                                $page_back = $page - 1;
                                echo '<li><a href="?p='.$page_back.'&mode=9&h='.$hash.'" ><img alt="" src="./images/left_pager.jpg" border="0"/></a></li>';
                            }
                            for($a=0; $a < $pages; $a++)
                            {
                                $b = $a + 1;
                                if($page == $b)
                                {
                                    echo '<li><a href="#" class="active">'.$page.'</a></li>';
                                }
                                else
                                {
                                    echo '<li><a href="?p='.$b.'&mode=9&h='.$hash.'">'.$b.'</a></li>';
                                }
                            }
                            if($page < $pages)
                            {
                                $page_for = $page + 1;
                                echo '<li><a href="?p='.$page_for.'&mode=9&h='.$hash.'"><img alt="" src="./images/right_pager.jpg" border="0"/></a></li>';
                            }
                        }

                        if(isset($_GET['mode']) && $_GET['mode'] == 8 && !isset($_GET['cid']) && !isset($_GET['id']) && isset($_GET['s']))
                        {
                            $charName = $_GET['s'];
                            $charName = mysql_real_escape_string($charName);

                            $q00 = odbc_exec($gcon, "SELECT dwUserID FROM TGAME_GSP.dbo.TCHARTABLE WHERE szName LIKE '%".$charName."%'");
                            $count = odbc_num_rows($q00);
                            $pages = $count / $entry;
                            if($page > 1)
                            {
                                $page_back = $page - 1;
                                echo '<li><a href="?p='.$page_back.'&mode=8&s='.$charName.'" ><img alt="" src="./images/left_pager.jpg" border="0"/></a></li>';
                            }
                            for($a=0; $a < $pages; $a++)
                            {
                                $b = $a + 1;
                                if($page == $b)
                                {
                                    echo '<li><a href="#" class="active">'.$page.'</a></li>';
                                }
                                else
                                {
                                    echo '<li><a href="?p='.$b.'&mode=8&s='.$charName.'">'.$b.'</a></li>';
                                }
                            }
                            if($page < $pages)
                            {
                                $page_for = $page + 1;
                                echo '<li><a href="?p='.$page_for.'&mode=8&s='.$charName.'"><img alt="" src="./images/right_pager.jpg" border="0"/></a></li>';
                            }
                        }
                      ?>
                   </ul>

            </div>
            <!-- Full page wrapper end -->

            <!-- Right wrapper Start -->
        <div id="right_wrapper">
          <div id="search">
            <input type="text" onblur="if(this.value =='') this.value='search'" onfocus="if (this.value == 'search') this.value=''" value="search" name="s" class="required" id="s" />
            <input type="button" />
          </div>


          <div class="categories">
            <div class="header"><a href="#">Links</a></div>
            <ul>
              <li> <a href="./admin_cp.php">Admin-Panel - Startseite</a> </li>
              <li> <a href="./admin_cp.php?mode=9">Tickets ansehen</a> </li>
              <li> <a href="./admin_cp.php?mode=7">Account bearbeiten</a> </li>
            </ul>
          </div>


      <!-- Right wrapper end -->

        <!--</div>
      	<div class="clear"></div>

        </div>-->
      </div>

        <div class="bottom_shadow"></div>
<!--********************************************* Main end *********************************************-->

<?php
    include("includes/foot.php");
?>
