<?php
// SSO Domain Admin
if (!empty($_GET['sso_token'])) {
  $username = domain_admin_sso('check', $_GET['sso_token']);

  if ($username !== false) {
    session_regenerate_id(true);
    $_SESSION['mailcow_cc_username'] = $username;
    $_SESSION['mailcow_cc_role'] = 'domainadmin';
    header('Location: /mailbox');
  }
}

if (isset($_POST["pw_reset_request"]) && !empty($_POST['username'])) {
  reset_password("issue", $_POST['username']);
  header("Location: /");
  exit;
}
if (isset($_POST["pw_reset"])) {
  $username = reset_password("check", $_POST['token']);
  $reset_result = reset_password("reset", array(
    'new_password' => $_POST['new_password'],
    'new_password2' => $_POST['new_password2'],
    'token' => $_POST['token'],
    'username' => $username,
    'check_tfa' => True
  ));

  if ($reset_result){
    header("Location: /");
    exit;
  }
}
if (isset($_POST["verify_tfa_login"])) {
  if (verify_tfa_login($_SESSION['pending_mailcow_cc_username'], $_POST)) {
    if (isset($_SESSION['pending_mailcow_cc_username']) && isset($_SESSION['pending_pw_reset_token']) && isset($_SESSION['pending_pw_new_password'])) {
      reset_password("reset", array(
        'new_password' => $_SESSION['pending_pw_new_password'],
        'new_password2' => $_SESSION['pending_pw_new_password'],
        'token' => $_SESSION['pending_pw_reset_token'],
        'username' => $_SESSION['pending_mailcow_cc_username']
      ));
      unset($_SESSION['pending_pw_reset_token']);
      unset($_SESSION['pending_pw_new_password']);
      unset($_SESSION['pending_mailcow_cc_username']);
      unset($_SESSION['pending_tfa_methods']);

      header("Location: /");
      exit;
    } else {
      $_SESSION['mailcow_cc_username'] = $_SESSION['pending_mailcow_cc_username'];
      $_SESSION['mailcow_cc_role'] = $_SESSION['pending_mailcow_cc_role'];
      unset($_SESSION['pending_mailcow_cc_username']);
      unset($_SESSION['pending_mailcow_cc_role']);
      unset($_SESSION['pending_tfa_methods']);

      header("Location: /user");
    }
  } else {
    unset($_SESSION['pending_pw_reset_token']);
    unset($_SESSION['pending_pw_new_password']);
    unset($_SESSION['pending_mailcow_cc_username']);
    unset($_SESSION['pending_mailcow_cc_role']);
    unset($_SESSION['pending_tfa_methods']);
  }
}

if (isset($_GET["cancel_tfa_login"])) {
  unset($_SESSION['pending_pw_reset_token']);
  unset($_SESSION['pending_pw_new_password']);
  unset($_SESSION['pending_mailcow_cc_username']);
  unset($_SESSION['pending_mailcow_cc_role']);
  unset($_SESSION['pending_tfa_methods']);

  header("Location: /");
}

if (isset($_POST["quick_release"])) {
	quarantine('quick_release', $_POST["quick_release"]);
}

if (isset($_POST["quick_delete"])) {
	quarantine('quick_delete', $_POST["quick_delete"]);
}

if (isset($_POST["login_user"]) && isset($_POST["pass_user"])) {
	$login_user = strtolower(trim($_POST["login_user"]));
	$as = check_login($login_user, $_POST["pass_user"]);

	if ($as == "admin") {
    session_regenerate_id(true);
		$_SESSION['mailcow_cc_username'] = $login_user;
		$_SESSION['mailcow_cc_role'] = "admin";
		header("Location: /debug");
    die();
	}
	elseif ($as == "domainadmin") {
    session_regenerate_id(true);
		$_SESSION['mailcow_cc_username'] = $login_user;
		$_SESSION['mailcow_cc_role'] = "domainadmin";
		header("Location: /mailbox");
    die();
	}
	elseif ($as == "user") {
    session_regenerate_id(true);
		$_SESSION['mailcow_cc_username'] = $login_user;
		$_SESSION['mailcow_cc_role'] = "user";
    $http_parameters = explode('&', $_SESSION['index_query_string']);
    unset($_SESSION['index_query_string']);
    if (in_array('mobileconfig', $http_parameters)) {
      if (in_array('only_email', $http_parameters)) {
        header("Location: /mobileconfig.php?only_email");
        die();
      }
      header("Location: /mobileconfig.php");
      die();
    }
    if (!isset($_SESSION['oauth2_request'])) {
      header("Location: /user");
      die();
    }
	}
	elseif ($as != "pending") {
    unset($_SESSION['pending_mailcow_cc_username']);
    unset($_SESSION['pending_mailcow_cc_role']);
    unset($_SESSION['pending_tfa_methods']);
		unset($_SESSION['mailcow_cc_username']);
		unset($_SESSION['mailcow_cc_role']);
	} else {
    session_regenerate_id(true);
  }
}

if (isset($_SESSION['mailcow_cc_role']) && (isset($_SESSION['acl']['login_as']) && $_SESSION['acl']['login_as'] == "1")) {
	if (isset($_GET["duallogin"])) {
    $duallogin = html_entity_decode(rawurldecode($_GET["duallogin"]));
    if (filter_var($duallogin, FILTER_VALIDATE_EMAIL)) {
      if (!empty(mailbox('get', 'mailbox_details', $duallogin))) {
        $_SESSION["dual-login"]["username"] = $_SESSION['mailcow_cc_username'];
        $_SESSION["dual-login"]["role"]     = $_SESSION['mailcow_cc_role'];
        $_SESSION['mailcow_cc_username']    = $duallogin;
        $_SESSION['mailcow_cc_role']        = "user";
        header("Location: /user");
      }
    }
    else {
      if (!empty(domain_admin('details', $duallogin))) {
        $_SESSION["dual-login"]["username"] = $_SESSION['mailcow_cc_username'];
        $_SESSION["dual-login"]["role"]     = $_SESSION['mailcow_cc_role'];
        $_SESSION['mailcow_cc_username']    = $duallogin;
        $_SESSION['mailcow_cc_role']        = "domainadmin";
        header("Location: /user");
      }
    }
  }
}

if (isset($_SESSION['mailcow_cc_role'])) {
	if (isset($_POST["set_tfa"])) {
		set_tfa($_POST);
	}
	if (isset($_POST["unset_tfa_key"])) {
		unset_tfa_key($_POST);
	}
	if (isset($_POST["unset_fido2_key"])) {
		fido2(array("action" => "unset_fido2_key", "post_data" => $_POST));
	}
}
if (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == "admin" && !isset($_SESSION['mailcow_cc_api'])) {
  // TODO: Move file upload to API?
	if (isset($_POST["submit_main_logo"])) {
    if ($_FILES['main_logo']['error'] == 0) {
      customize('add', 'main_logo', $_FILES);
    }
    if ($_FILES['main_logo_dark']['error'] == 0) {
      customize('add', 'main_logo_dark', $_FILES);
    }
	}
	if (isset($_POST["reset_main_logo"])) {
    customize('delete', 'main_logo');
    customize('delete', 'main_logo_dark');
	}
  // Some actions will not be available via API
	if (isset($_POST["license_validate_now"])) {
		license('verify');
	}
  if (isset($_POST["admin_api"])) {
    if (isset($_POST["admin_api"]["ro"])) {
      admin_api('ro', 'edit', $_POST);
    }
    elseif (isset($_POST["admin_api"]["rw"])) {
      admin_api('rw', 'edit', $_POST);
    }
	}
  if (isset($_POST["admin_api_regen_key"])) {
    if (isset($_POST["admin_api_regen_key"]["ro"])) {
      admin_api('ro', 'regen_key', $_POST);
    }
    elseif (isset($_POST["admin_api_regen_key"]["rw"])) {
      admin_api('rw', 'regen_key', $_POST);
    }
	}
	if (isset($_POST["rspamd_ui"])) {
		rspamd_ui('edit', $_POST);
	}
	if (isset($_POST["mass_send"])) {
		sys_mail($_POST);
	}
}
?>
