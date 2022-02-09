# PHPOpenMailbox

Early Alpha

Usage

		$mymail = new php_mailbox; // Create New Mailbox class
		
		$mymail->setup( $server, $username, $password, $rport, $options); // Setup connection details
		$mymail->connect(); // Connect to mailbox
						
		$mailboxes = $mymail->getMailboxes(); // Get list of mailboxes
    
  	$mymail->getMailbox(); // Get mailbox contents
