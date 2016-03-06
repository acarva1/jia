<?php
require './includes/config.inc.php';
require './includes/login.inc.php';
include './includes/header.html';
//show a login form if user isn't logged in.
    if (empty($_SESSION['id'])) { 
?>
<div class="bodyDiv">
    <div id="registerForm">
        <form action="<?php echo $_SERVER['PHP_SELF'];?>" method="post">
            <div class="email">
                <label for="email">Email: </label>
                <input type="text" id="email" class="loginInput" name="login[email]" value="<?php if (isset($loginEmail)) {echo $loginEmail;} ?>"/>
            </div>
            <div class="password">
                <label for="password">Password: </label>
                <input type="password" id="password" class="loginInput" name="login[pass]"/>
                <?php if (isset($loginError) && $loginError === true) {
                echo '<span class="error">Invalid Email or Password</span>';
                }?>
            </div>  
            <div class="utilities">
                <label for="remember">Remember Me: </label>
                <input type="checkbox" name="login[remember]"/>
                <input type="submit" id="login" value="Log-in"/>
                <a href="#" title="Forgot your password?" class="forgot">Forgot your password?</a>
            </div>
        </form>
    </div>
</div>
                <?php
                }else{ 
                    echo '<span class="loginSuccess">You\'re logged in as ' . $_SESSION['name'] . '! <a href="myaccount.php">My Account</a></span>';
                }
include './includes/footer.html';
