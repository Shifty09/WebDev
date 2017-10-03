<?php
    error_reporting( E_ALL );
    ini_set('display_errors', '1');
    class MySQLConnection{
        private static $username = "username";
        private static $password = "password";
        private static $db = "user_database";
        private static $host = "localhost";

        static function getNewConnection()
        {
            $con = mysqli_connect(self::$host, self::$username, self::$password, self::$db);
            if ($con->connect_errno || $con->connect_errno) {
                header("Location: error.php");
                exit();
            }
            return $con;
        }

        static function verifySID($con, $sid, $ip){ // I made this script a couple months ago, and can't remember if I completed this function because it is actually used
            return true;
        }

        static function register($email, $password, $name){
            $con = self::getNewConnection();
            $query = "SELECT * FROM `users` WHERE email='$email'";
            $verify = null;
            $result = $con->query($query);
            
            if($result->num_rows === 0) {
                $verify = verifyHash();
                $options = ['cost' => 11];
                $passwordhash = password_hash($password, PASSWORD_BCRYPT, $options);
                $query = "INSERT INTO `users` (email, password, name, verify) VALUES ('$email', '$passwordhash', '$name', '$verify')";

                $con = self::getNewConnection();
                if (!$con->query($query)) {
                    echo(mysqli_errno($con) . " -> " . mysqli_error($con));
                    die("<br />Unable to submit request");
                }
            }
            $con->close();
            return $verify;
        }

        static function login($email, $password, $ip){
            $code = "Uh oh";
            /**
             * Error codes
             * ______________________________________________
             * |   0    |        EVERY THING IS OK          |
             * |--------+-----------------------------------|
             * |   1    |          No Such User             |
             * |--------+-----------------------------------|
             * |   2    |         Incorrect Pass            |
             * |--------+-----------------------------------|
             * |   3    |        Error Signing in           |
             * |--------------------------------------------|
             * |   4    |         Database Error            |
             * |--------------------------------------------|
             *
             */
            $con = self::getNewConnection();

            $query = "SELECT password FROM `users` WHERE email='$email'";

            $result = $con->query($query);
            if(mysqli_num_rows($result) > 0){
                while($row = mysqli_fetch_assoc($result)){
                    $verify = $row["password"];
                    $pass = password_verify($verify, $password);
                    if($pass){
                        $sid = session_id();
                        $query = "UPDATE `users` set ip='$ip', sid='$sid' WHERE email='$email'";
                        if(!$con->query($query)){
                            $code = "There was an error connecting to the login server. Please try again later";
                        } else {
                            $_SESSION["id"] = $row["id"];
                            $code = "SUCCESS";
                        }
                    } else {
                        $code = "Incorrect email / password";
                    }
                }
            } else {
                $code = "Invalid email / password";   
            }

            $con->close();
            return $code;
        }

        static function editGroups($con, $sid, $groups, $preferred){
            $g = new Groups();
            $g->groups = $groups;
            $g->preferred = $preferred;
            $enc = json_encode($g);

            $con->query("INSERT INTO `users` (groups) VALUES ('$enc') WHERE sid='$sid'");
        }
    }
    class Groups {
        public $groups;
        public $preferred;
    }

    function verifyHash()
    {
        $length = 20; 
        $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $str = '';
        $max = mb_strlen($keyspace, '8bit') - 1;
        for ($i = 0; $i < $length; ++$i) {
            $str .= $keyspace[random_int(0, $max)];
        }
        return $str;
    }

    /*function crypto_rand_secure($min, $max)
    {
        echo "getting crypto_rand_secure <br />";
        $range = $max - $min;
        if ($range < 1) return $min; // not so random...
        $log = ceil(log($range, 2));
        $bytes = (int) ($log / 8) + 1; // length in bytes
        $bits = (int) $log + 1; // length in bits
        $filter = (int) (1 << $bits) - 1; // set all lower bits to 1
        echo "All vars setup <br />Range: ".$range."<br/>Log: ".$log."<br/>Bytes: ".$bytes."<br/>Bits: ".$bits."<br/>Filter: ".$filter."<br />";
        do {
            try {
                $rnd = hexdec(bin2hex(openssl_random_pseudo_bytes($bytes)));
                $rnd = $rnd & $filter; // discard irrelevant bits
            } catch (Exception $e){
                echo "Exception: ".$e->getMessage()."<br />";
            }
        } while ($rnd > $range);
        echo "crypto_rando_secure ".$min." " . $rnd ."<br />";
        return $min + $rnd;
    }

    function getToken($length = 20)
    {
        echo "getting token <br />";
        $token = "";
        $codeAlphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $codeAlphabet.= "abcdefghijklmnopqrstuvwxyz";
        $codeAlphabet.= "0123456789";
        $max = strlen($codeAlphabet); // edited
        echo "Code Alphabet: ".$codeAlphabet."<br />Length: ".$max."<br />";
        echo "starting for loop <br />";
        for ($i=0; $i < $length; $i++) {
            echo "Token: ".$token."<br />";
            $token .= $codeAlphabet[crypto_rand_secure(0, $max-1)];
        }

        echo "returning ".$token."<br />";
        return $token;
    }*/

    function checkSession()
    {
        if(isset($_SESSION['email'])){
            $email = $_SESSION['email'];
            $connection = MySQLConnection::getNewConnection();
            $query = "SELECT 'sid' FROM `users` WHERE email='$email'";
            $result = $connection->query($query);
            if(mysqli_num_rows($result) != 0){
                while($row = $result->fetch_assoc()){
                    $sid = $row['sid'];
                    if($sid === session_id()){
                        $connection->close();
                        return true;
                    }
                }
            }
            $connection->close();
            unset($_SESSION['email']);
        }
        return false;
    }

    function sendVerificationEmail($email, $verify)
    {
        $message = file_get_contents("email.php");
        $message = str_replace("&name", "\<Name Here\>", $message);
        $message = str_replace("&verify", $verify, $message);

        $subject = "Reumie ~ Verify Email";
        $headers = "From: registration@reumie.com";

        if(!mail($email, $subject, $message, $headers)){
            $connection = MySQLConnection::getNewConnection();
            $query = "UPDATE verify='$verify' WHERE email='$email'";
            if($con->query($query)){
                header("Location: http://localhost/error.php");
                exit();
            }
            $connection->close();
        }
    }
?>